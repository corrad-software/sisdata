<?php

namespace App\Modules\Ptn\Kpi;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ptn\Services\Analitik;
use App\Modules\Ptn\Support\LotSql;
use Illuminate\Support\Facades\DB;

/** KPI: lots not inspected for more than N days (ptn.hari_belum_diperiksa, default 365). */
class LotBelumDiperiksa extends PtnKpi
{
    public function __construct(Analitik $analitik, private LotSql $lots, private Parameters $parameters)
    {
        parent::__construct($analitik);
    }

    public function key(): string
    {
        return 'ptn.lot_belum_diperiksa';
    }

    public function label(): string
    {
        return 'Lot belum diperiksa melebihi tempoh';
    }

    public function compute(User $user, array $filters = []): array
    {
        $n = (int) $this->parameters->get('ptn.hari_belum_diperiksa', 365);
        [$where, $b] = $this->lots->where($user, 'l', ['kod_daerah' => $filters['kod_daerah'] ?? null]);
        $rows = DB::select("SELECT l.kod_daerah, COUNT(*) AS n FROM lot_tanah l
            WHERE {$where} AND NOT EXISTS (SELECT 1 FROM rekod_lawatan v WHERE v.lot_id = l.id AND v.status_lawatan IN ('dihantar','disahkan') AND v.tarikh_lawatan >= CURRENT_DATE - ?::int)
            GROUP BY l.kod_daerah ORDER BY n DESC", [...$b, $n]);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)), 'unit' => 'lot',
            'pecahan' => array_map(fn ($r) => ['label' => (string) $r->kod_daerah, 'nilai' => (int) $r->n, 'kod' => 'daerah:'.$r->kod_daerah], $rows),
            'pautan' => '/admin/ptn/lot',
        ];
    }
}
