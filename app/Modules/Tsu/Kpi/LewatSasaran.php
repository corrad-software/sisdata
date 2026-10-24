<?php

namespace App\Modules\Tsu\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Tsu\Services\LaporanService;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Support\Facades\DB;

/** KPI: open urusan whose current stage is past its target date, split MAIS vs external parties (SRS-TSU-27). */
class LewatSasaran implements KpiProvider
{
    public function __construct(private LaporanService $laporan) {}

    public function key(): string
    {
        return 'tsu.lewat_sasaran';
    }

    public function label(): string
    {
        return 'Urusan tanah melepasi tempoh sasaran peringkat';
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    public function permission(): string
    {
        return 'tsu.laporan.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        $ids = $this->laporan->base($user, ['kod_daerah' => $filters['kod_daerah'] ?? null])->whereIn('status', Lalai::URUSAN_TERBUKA)->select('id');
        $rows = DB::table('urusan_peringkat as p')->join('urusan_tanah as u', fn ($j) => $j->on('u.id', '=', 'p.urusan_id')->on('u.peringkat_semasa', '=', 'p.no_urutan'))
            ->whereIn('u.id', $ids)->whereIn('p.status', ['aktif', 'menunggu_kelulusan'])->where('p.tarikh_sasaran', '<', today())
            ->selectRaw("CASE WHEN p.pihak_bertanggungjawab = 'mais' THEN 'mais' ELSE 'luar' END AS pihak, count(*) AS n")->groupBy('pihak')->pluck('n', 'pihak');

        return [
            'nilai' => (int) $rows->sum(), 'unit' => 'urusan', 'sasaran' => 0,
            'pecahan' => [['label' => 'Di pihak MAIS', 'nilai' => (int) ($rows['mais'] ?? 0), 'kod' => 'pihak:mais'], ['label' => 'Di pihak luar (PTD, PTG, jurukur, JPPH…)', 'nilai' => (int) ($rows['luar'] ?? 0), 'kod' => 'pihak:luar']],
            'pautan' => '/admin/tsu/urusan?lewat=1',
        ];
    }
}
