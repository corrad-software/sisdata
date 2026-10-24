<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Wang;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-TSU-14: costs per stage and category, with payment status and receipt
 * link; totals with bcmath. Only Bahagian Kewangan (tsu.kewangan.kemas_kini)
 * changes financial data — every change is audited by MOD-AUD's observer.
 */
class KosService
{
    /** @param array<string, mixed> $data */
    public function store(UrusanTanah $urusan, array $data, User $user): KosUrusan
    {
        if ($urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        if (! empty($data['peringkat_id']) && ! UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('id', $data['peringkat_id'])->exists()) {
            throw ValidationException::withMessages(['peringkat_id' => [__('tsu::tsu.kos.peringkat_bukan_urusan')]]);
        }
        $k = new KosUrusan;
        $k->forceFill([
            'urusan_id' => $urusan->id, 'peringkat_id' => $data['peringkat_id'] ?? $this->defaultStage($urusan, (string) $data['kategori_kos']),
            'kategori_kos' => $data['kategori_kos'], 'keterangan' => $data['keterangan'] ?? null, 'amaun' => Wang::norm((string) $data['amaun']),
            'status_bayaran' => 'belum_bayar', 'no_invois' => $data['no_invois'] ?? null, 'tarikh_invois' => $data['tarikh_invois'] ?? null,
            'tarikh_akhir_bayaran' => $data['tarikh_akhir_bayaran'] ?? null, 'id_pencipta' => $user->id,
        ])->save();

        return $k->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(KosUrusan $k, array $data, User $user): KosUrusan
    {
        if ($k->urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        if ($k->status_bayaran === 'dibayar' && isset($data['amaun']) && bccomp(Wang::norm((string) $data['amaun']), (string) $k->amaun, 2) !== 0) {
            throw ValidationException::withMessages(['amaun' => [__('tsu::tsu.kos.sudah_dibayar')]]);
        }
        $merged = array_merge($k->only(['status_bayaran', 'no_resit', 'tarikh_bayaran']), array_intersect_key($data, array_flip(['status_bayaran', 'no_resit', 'tarikh_bayaran'])));
        if (($merged['status_bayaran'] ?? null) === 'dibayar' && (empty($merged['no_resit']) || empty($merged['tarikh_bayaran']))) {
            throw ValidationException::withMessages(['no_resit' => [__('tsu::tsu.kos.resit_wajib')]]);
        }
        if (! empty($data['id_dokumen']) && ! UrusanDokumen::query()->where('urusan_id', $k->urusan_id)->where('id_dokumen_dms', $data['id_dokumen'])->exists()) {
            throw ValidationException::withMessages(['id_dokumen' => [__('tsu::tsu.kos.dokumen_bukan_urusan')]]);
        }
        $fields = array_intersect_key($data, array_flip(['kategori_kos', 'keterangan', 'amaun', 'status_bayaran', 'no_invois', 'tarikh_invois', 'tarikh_akhir_bayaran', 'tarikh_bayaran', 'no_resit', 'id_dokumen', 'rujukan_grp']));
        if (isset($fields['amaun'])) {
            $fields['amaun'] = Wang::norm((string) $fields['amaun']);
        }
        $k->forceFill([...$fields, 'id_pengemaskini' => $user->id])->save();

        return $k->refresh();
    }

    /**
     * Totals per category / payment status (SRS-TSU-14 "jumlah terkumpul per urusan").
     *
     * @return array{jumlah: string, dibayar: string, belum_dibayar: string, mengikut_kategori: list<array{kod: string, nama: string, jumlah: string}>}
     */
    public function summary(UrusanTanah $urusan): array
    {
        $rows = KosUrusan::query()->where('urusan_id', $urusan->id)->where('status_bayaran', '!=', 'dibatalkan')->get();
        $byCat = [];
        foreach (Lalai::KATEGORI_KOS as $kod => $nama) {
            $sum = Wang::sum($rows->where('kategori_kos', $kod)->pluck('amaun'));
            if (bccomp($sum, '0', 2) !== 0) {
                $byCat[] = ['kod' => $kod, 'nama' => $nama, 'jumlah' => $sum];
            }
        }

        return [
            'jumlah' => Wang::sum($rows->pluck('amaun')),
            'dibayar' => Wang::sum($rows->where('status_bayaran', 'dibayar')->pluck('amaun')),
            'belum_dibayar' => Wang::sum($rows->where('status_bayaran', '!=', 'dibayar')->pluck('amaun')),
            'mengikut_kategori' => $byCat,
        ];
    }

    private function defaultStage(UrusanTanah $urusan, string $kategori): ?int
    {
        $current = UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('no_urutan', $urusan->peringkat_semasa)->first();
        if ($current !== null && in_array($kategori, $current->senarai_kategori_kos, true)) {
            return $current->id;
        }

        return UrusanPeringkat::query()->where('urusan_id', $urusan->id)->whereJsonContains('senarai_kategori_kos', $kategori)->orderBy('no_urutan')->value('id') ?? $current?->id;
    }
}
