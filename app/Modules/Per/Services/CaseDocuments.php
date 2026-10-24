<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PergerakanDokumen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Supporting documents of a case (SRS-PER-16/22). Files go to MOD-DMS through
 * DocumentStore (ownerType "pemerolehan", Sulit); the row here links the DMS
 * document to a checklist item and keeps the physical original's location
 * and its issue/return history.
 */
class CaseDocuments
{
    public const OWNER_TYPE = 'pemerolehan';

    public function __construct(private DocumentStore $store, private Checklist $checklist) {}

    /** @param array<string, mixed> $meta tarikh_dokumen, tarikh_terima, kod_peti_besi, no_fail, lokasi_rak, status_dokumen_asal, catatan */
    public function upload(Pemerolehan $case, ?int $semakId, UploadedFile $file, array $meta, User $user): DokumenPemerolehan
    {
        $item = $semakId !== null ? $this->checklist->itemFor($case, $semakId) : null;
        if ($semakId !== null && $item === null) {
            throw ValidationException::withMessages(['id_semak' => __('per::per.semak_tidak_sah')]);
        }

        try {
            $dmsId = $this->store->store($file, self::OWNER_TYPE, $case->id, $item->kategori_dms ?? 'lain', $user, 'sulit', array_filter([
                'tajuk' => ($item->nama_dokumen ?? ($meta['jenis_dokumen'] ?? 'Dokumen pemerolehan')).' — '.$case->no_kes,
                'modul_sumber' => 'per',
                'pautan_induk' => $case->pautan(),
                'kod_daerah' => $case->kod_daerah,
                'kod_mukim' => $case->kod_mukim,
                'tarikh_dokumen' => $meta['tarikh_dokumen'] ?? null,
            ]));
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }

        return $this->attach($case, $item?->id, $item->jenis_dokumen ?? (string) ($meta['jenis_dokumen'] ?? 'lain'), $dmsId, $meta, $user);
    }

    /** Tick a checklist item with a document already attached to the case in DMS. */
    public function link(Pemerolehan $case, int $semakId, string $dmsId, array $meta, User $user): DokumenPemerolehan
    {
        $item = $this->checklist->itemFor($case, $semakId);
        if ($item === null) {
            throw ValidationException::withMessages(['id_semak' => __('per::per.semak_tidak_sah')]);
        }
        $visible = collect($this->store->forOwner(self::OWNER_TYPE, $case->id, $user))->firstWhere('id', $dmsId);
        if ($visible === null) {
            throw ValidationException::withMessages(['rujukan_dms' => __('per::per.dms_tidak_sah')]);
        }

        return $this->attach($case, $item->id, $item->jenis_dokumen, $dmsId, $meta + ['no_versi' => $visible['version']], $user);
    }

    /** @param array<string, mixed> $meta */
    public function attach(Pemerolehan $case, ?int $semakId, string $type, ?string $dmsId, array $meta, User $user): DokumenPemerolehan
    {
        $doc = DokumenPemerolehan::query()->create([
            'id_pemerolehan' => $case->id,
            'id_semak' => $semakId,
            'jenis_dokumen' => $type,
            'rujukan_dms' => $dmsId,
            'no_versi' => $meta['no_versi'] ?? 1,
            'tarikh_dokumen' => $meta['tarikh_dokumen'] ?? null,
            'tarikh_terima' => $meta['tarikh_terima'] ?? now()->toDateString(),
            'id_pegawai_terima' => $user->id,
            'status_dokumen' => 'diterima',
            'kod_peti_besi' => $meta['kod_peti_besi'] ?? null,
            'no_fail' => $meta['no_fail'] ?? null,
            'lokasi_rak' => $meta['lokasi_rak'] ?? null,
            'status_dokumen_asal' => $meta['status_dokumen_asal'] ?? null,
            'catatan' => $meta['catatan'] ?? null,
        ]);
        $this->checklist->refresh($case);

        return $doc;
    }

    /** @param array<string, mixed> $data */
    public function update(Pemerolehan $case, DokumenPemerolehan $doc, array $data): DokumenPemerolehan
    {
        $doc->forceFill(array_intersect_key($data, array_flip([
            'status_dokumen', 'kod_peti_besi', 'no_fail', 'lokasi_rak', 'status_dokumen_asal', 'tarikh_dokumen', 'tarikh_terima', 'catatan',
        ])))->save();
        $this->checklist->refresh($case);

        return $doc;
    }

    /** SRS-PER-22: issue (keluar) or return (pulang) the physical original. */
    public function move(DokumenPemerolehan $doc, string $type, ?string $purpose, User $user): PergerakanDokumen
    {
        $out = $doc->status_dokumen_asal === 'dikeluarkan';
        if ($type === 'keluar' && $out) {
            throw new HttpException(409, __('per::per.dokumen_sudah_dikeluarkan'));
        }
        if ($type === 'pulang' && ! $out) {
            throw new HttpException(409, __('per::per.dokumen_tidak_dikeluarkan'));
        }

        return DB::transaction(function () use ($doc, $type, $purpose, $user) {
            $doc->forceFill(['status_dokumen_asal' => $type === 'keluar' ? 'dikeluarkan' : 'asal_dalam_simpanan'])->save();

            return PergerakanDokumen::query()->create([
                'id_dokumen_pemerolehan' => $doc->id, 'jenis' => $type, 'tarikh' => now(), 'id_pegawai' => $user->id, 'tujuan' => $purpose,
            ]);
        });
    }
}
