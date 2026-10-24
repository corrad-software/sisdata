<?php

namespace App\Modules\Pjk\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Pjk\Models\PremisAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-PJK-23: is a premise's lot "diwakafkan" or "dibeli"? Decided from the
 * acquisition channel recorded by MOD-PER (latest completed pemerolehan of
 * the lot), mapped by parameter pjk.peta_saluran_perolehan; falls back to the
 * lot's owning bahagian, then "belian". An explicit value on the premise wins.
 *
 * MOD-PER exposes no contract for this yet, so its two tables are read
 * (read-only, by table name). Suggested: a Per\Contracts\AcquisitionRegistry.
 */
class SaluranPemerolehan
{
    public const PETA_LALAI = ['wakaf' => 'wakaf', 'pembelian_terus' => 'belian'];

    public function __construct(private Parameters $parameters) {}

    public function statusFor(PremisAset $premis): string
    {
        if (in_array($premis->saluran_pemerolehan, ['wakaf', 'belian'], true)) {
            return $premis->saluran_pemerolehan;
        }

        return $this->forLot($premis->lot_id, $premis->bahagian);
    }

    public function forLot(string $lotId, ?string $bahagian = null): string
    {
        $map = (array) $this->parameters->get('pjk.peta_saluran_perolehan', self::PETA_LALAI);
        if (Schema::hasTable('pemerolehan') && Schema::hasTable('saluran_pemerolehan')) {
            $code = DB::table('pemerolehan as p')->join('saluran_pemerolehan as s', 's.id', '=', 'p.id_saluran')
                ->where('p.lot_id', $lotId)->whereNull('p.deleted_at')->whereNotIn('p.status_pemerolehan', ['dibatalkan', 'ditolak'])
                ->orderByRaw("CASE WHEN p.status_pemerolehan = 'selesai' THEN 0 ELSE 1 END")->orderByDesc('p.created_at')
                ->value('s.kod_saluran');
            if (is_string($code) && isset($map[$code])) {
                return $map[$code] === 'wakaf' ? 'wakaf' : 'belian';
            }
            if (is_string($code)) {
                return 'belian';
            }
        }

        return $bahagian === 'wakaf' ? 'wakaf' : 'belian';
    }
}
