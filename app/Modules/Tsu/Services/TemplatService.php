<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Tsu\Models\TemplatPeringkat;
use App\Modules\Tsu\Models\TemplatProses;
use Illuminate\Support\Facades\DB;

/**
 * SRS-TSU-02: administrators change stages, order, target durations,
 * mandatory documents and cost categories without a code release. A change
 * is a NEW template version; running urusan keep the stages they were
 * generated with, new urusan use the active version.
 */
class TemplatService
{
    /**
     * @param  array{nama: string, keterangan?: string|null, aliran_kelulusan?: string|null, peringkat: list<array<string, mixed>>}  $data
     */
    public function newVersion(string $jenis, array $data, User $user): TemplatProses
    {
        return DB::transaction(function () use ($jenis, $data, $user) {
            $prev = TemplatProses::query()->where('jenis_urusan', $jenis)->lockForUpdate()->orderByDesc('versi')->first();
            TemplatProses::query()->where('jenis_urusan', $jenis)->where('status_aktif', true)->update(['status_aktif' => false, 'updated_at' => now()]);
            $t = TemplatProses::query()->create([
                'jenis_urusan' => $jenis, 'versi' => ($prev->versi ?? 0) + 1, 'nama' => $data['nama'], 'keterangan' => $data['keterangan'] ?? null,
                'aliran_kelulusan' => $data['aliran_kelulusan'] ?? $prev?->aliran_kelulusan, 'status_aktif' => true, 'id_pencipta' => $user->id,
            ]);
            foreach ($data['peringkat'] as $i => $p) {
                TemplatPeringkat::query()->create([
                    'templat_id' => $t->id, 'no_urutan' => $i + 1, 'kod' => $p['kod'], 'nama_peringkat' => $p['nama_peringkat'],
                    'tindakan_diperlukan' => $p['tindakan_diperlukan'] ?? null, 'pihak_bertanggungjawab' => $p['pihak_bertanggungjawab'],
                    'tempoh_sasaran_hari' => (int) $p['tempoh_sasaran_hari'], 'senarai_dokumen_wajib' => array_values($p['senarai_dokumen_wajib'] ?? []),
                    'senarai_kategori_kos' => array_values($p['senarai_kategori_kos'] ?? []), 'perlu_kelulusan' => (bool) ($p['perlu_kelulusan'] ?? false),
                    'kesan' => $p['kesan'] ?? null,
                ]);
            }

            return $t->load('peringkat');
        });
    }
}
