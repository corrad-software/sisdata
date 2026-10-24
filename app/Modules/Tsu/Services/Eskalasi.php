<?php

namespace App\Modules\Tsu\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Makluman;

/**
 * SRS-TSU-03 target durations with escalation: a current stage past its
 * target date notifies the responsible officer (level 1); after
 * `tsu.hari_eskalasi_ketua` more days the head of division too (level 2).
 */
class Eskalasi
{
    public function __construct(private Parameters $parameters, private Makluman $makluman) {}

    /** @return array{aras_1: int, aras_2: int} */
    public function run(): array
    {
        $days = (int) $this->parameters->get('tsu.hari_eskalasi_ketua', 7);
        $n = ['aras_1' => 0, 'aras_2' => 0];
        $rows = UrusanPeringkat::query()->with('urusan.pegawai')->whereIn('status', ['aktif', 'menunggu_kelulusan'])->where('tarikh_sasaran', '<', today())->where('aras_eskalasi', '<', 2)->get();
        foreach ($rows as $p) {
            $u = $p->urusan;
            if ($u->sintetik || $u->dikunci || ! $u->terbuka() || $p->no_urutan !== $u->peringkat_semasa) {
                continue;
            }
            $late = (int) $p->tarikh_sasaran?->diffInDays(today());
            $data = ['no_rujukan' => $u->no_rujukan_mais, 'peringkat' => $p->nama_peringkat, 'pihak' => Lalai::PIHAK[$p->pihak_bertanggungjawab] ?? $p->pihak_bertanggungjawab, 'hari_lewat' => $late, 'tarikh_sasaran' => $p->tarikh_sasaran?->format('d/m/Y')];
            if ($p->aras_eskalasi === 0) {
                $this->makluman->hantar('tsu.peringkat_lewat', $u->pegawai, $data, '/admin/tsu/urusan/'.$u->id, 'tsu.lewat:'.$p->id);
                $p->forceFill(['aras_eskalasi' => 1])->save();
                $n['aras_1']++;
            }
            if ($late >= $days && $p->aras_eskalasi < 2) {
                $this->makluman->hantar('tsu.peringkat_eskalasi', $this->makluman->denganKetua($u->pegawai), $data, '/admin/tsu/urusan/'.$u->id, 'tsu.eskalasi:'.$p->id);
                $p->forceFill(['aras_eskalasi' => 2])->save();
                $n['aras_2']++;
            }
        }

        return $n;
    }
}
