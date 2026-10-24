<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Kes\Models\DokumenKes;
use App\Modules\Kes\Models\KesMahkamah;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * SRS-KES-12/13: legal documents live in MOD-DMS (versioning, encryption at
 * rest, antivirus, dynamic watermark on preview/print) as Rahsia records
 * owned by `kes_mahkamah`; this module keeps the case-side index (category,
 * version) and logs every download in the case access log.
 */
class DokumenService
{
    public const OWNER = 'kes_mahkamah';

    public function __construct(private DocumentStore $store, private Parameters $parameters, private LogCapaianKes $log) {}

    public function muatNaik(KesMahkamah $kes, UploadedFile $file, string $kategori, User $by): DokumenKes
    {
        try {
            $id = $this->store->store($file, self::OWNER, $kes->id, (string) $this->parameters->get('kes.kategori_dms', 'lain'), $by, 'rahsia', [
                'kategori_dokumen_kes' => $kategori, 'no_rujukan_kes' => $kes->no_rujukan, 'pautan_induk' => '/admin/kes/kes/'.$kes->id,
            ]);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }
        $versi = DokumenKes::query()->where('kes_mahkamah_id', $kes->id)->where('kategori_dokumen', $kategori)->where('nama_fail', $file->getClientOriginalName())->max('versi');

        return DokumenKes::query()->updateOrCreate(['kes_mahkamah_id' => $kes->id, 'id_dokumen_dms' => $id], [
            'kategori_dokumen' => $kategori, 'nama_fail' => $file->getClientOriginalName(), 'versi' => ((int) $versi) + 1,
            'tarikh_muat_naik' => now(), 'id_pengguna' => $by->id, 'sumber' => 'kes',
        ]);
    }

    /** Link an existing DMS document (e.g. carried over from a complaint, SRS-KES-19). */
    public function paut(KesMahkamah $kes, string $idDokumen, string $kategori, ?string $nama, ?int $userId, string $sumber): DokumenKes
    {
        return DokumenKes::query()->firstOrCreate(['kes_mahkamah_id' => $kes->id, 'id_dokumen_dms' => $idDokumen], [
            'kategori_dokumen' => $kategori, 'nama_fail' => $nama, 'versi' => 1, 'tarikh_muat_naik' => now(), 'id_pengguna' => $userId, 'sumber' => $sumber,
        ]);
    }

    /** Download URL (DMS short-lived signed URL) — logged as "muat_turun" with IP/time. */
    public function muatTurun(KesMahkamah $kes, DokumenKes $dok, User $user): ?string
    {
        $url = $this->store->temporaryUrl($dok->id_dokumen_dms, $user);
        $this->log->rekod($url === null ? 'ditolak' : 'muat_turun', $kes->id, $dok->id_dokumen_dms, $url === null ? 'DMS menolak capaian dokumen' : $dok->nama_fail);

        return $url;
    }

    /** A new version uploaded through the generic DMS panel for a case document. */
    public function handle(DocumentStored $e): void
    {
        if ($e->ownerType !== self::OWNER || $e->ownerId === null) {
            return;
        }
        $row = DokumenKes::query()->where('kes_mahkamah_id', $e->ownerId)->where('id_dokumen_dms', $e->documentId)->first();
        if ($row !== null) {
            if ($e->version > $row->versi) {
                $row->forceFill(['versi' => $e->version, 'tarikh_muat_naik' => now()])->save();
            }

            return;
        }
        if (KesMahkamah::query()->withoutGlobalScope('access')->whereKey($e->ownerId)->exists()) {
            DokumenKes::query()->create(['kes_mahkamah_id' => $e->ownerId, 'id_dokumen_dms' => $e->documentId, 'kategori_dokumen' => 'lain', 'versi' => $e->version, 'tarikh_muat_naik' => now(), 'sumber' => 'dms']);
        }
    }
}
