<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-PJK-07: rate review — current rate against comparable running
 * contracts (same category and district, per m²) and the latest market
 * valuation of the lot. Valuations are Rahsia (SRS §4.2): shown only with
 * pjk.rahsia.lihat and every such view is audited.
 */
class SemakanKadar
{
    public function __construct(private AccessControl $access, private AuditLogger $audit, private Parameters $parameters) {}

    /** @return array<string, mixed> */
    public function forContract(Kontrak $kontrak, User $user, bool $withValuation = true): array
    {
        $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
        $rateSen = Wang::sen($kontrak->kadar);

        $comparables = DB::table('kontrak_pajakan as k')->join('premis_aset as p', 'p.id', '=', 'k.premis_id')
            ->whereNull('k.deleted_at')->whereIn('k.status', Kod::BERKUAT_KUASA)
            ->where('k.id', '!=', $kontrak->id)->where('k.kategori_hartanah', $kontrak->kategori_hartanah)
            ->where('k.kod_daerah', $kontrak->kod_daerah)->where('p.keluasan', '>', 0)
            ->orderByRaw('k.kadar / p.keluasan')
            ->selectRaw('round(k.kadar / p.keluasan, 4)::text as per_m2')->pluck('per_m2')->map(fn ($v) => (string) $v)->values();
        // Decimal arithmetic (bcmath) — no floats for money.
        $median = null;
        if ($comparables->isNotEmpty()) {
            $n = $comparables->count();
            $median = $n % 2 ? $comparables[intdiv($n, 2)] : bcdiv(bcadd($comparables[$n / 2 - 1], $comparables[$n / 2], 4), '2', 4);
        }
        $areaStr = $premis?->keluasan !== null && Wang::sen($premis->keluasan) > 0 ? $premis->keluasan : null;

        $out = [
            'kadar_semasa' => $kontrak->kadar,
            'keluasan' => $premis?->keluasan,
            'kadar_per_m2' => $areaStr ? bcdiv(Wang::dec($rateSen), $areaStr, 2) : null,
            'bil_perbandingan' => $comparables->count(),
            'median_kadar_setara_per_m2' => $median !== null ? bcadd($median, '0', 2) : null,
            'kadar_cadangan_setara' => $median !== null && $areaStr ? Wang::norm(bcmul($median, $areaStr, 4)) : null,
            'nilai_pasaran' => null,
            'tarikh_penilaian' => null,
            'hasil_tahunan_peratus' => null,
            'kadar_cadangan_pasaran' => null,
            'penilaian_terhad' => false,
        ];

        if (! $withValuation || ! Schema::hasTable('penilaian')) {
            return $out;
        }
        if (! $this->access->allows($user, 'pjk.rahsia.lihat')) {
            $out['penilaian_terhad'] = true;

            return $out;
        }
        $val = DB::table('penilaian')->where('lot_id', $kontrak->lot_id)->whereNull('deleted_at')->whereNotNull('nilai_pasaran')
            ->orderByDesc('tarikh_penilaian')->first(['id', 'nilai_pasaran', 'tarikh_penilaian']);
        if ($val !== null) {
            $market = Wang::sen((string) $val->nilai_pasaran);
            $target = (string) $this->parameters->get('pjk.sasaran_hasil_peratus', '5.00');
            $out['nilai_pasaran'] = Wang::dec($market);
            $out['tarikh_penilaian'] = $val->tarikh_penilaian;
            $out['hasil_tahunan_peratus'] = $market > 0 ? bcdiv((string) ($rateSen * 12 * 100), (string) $market, 2) : null;
            $out['kadar_cadangan_pasaran'] = Wang::dec(intdiv(Wang::percent($market, $target), 12));
            $this->audit->record('lihat', 'pjk', 'penilaian', (string) $val->id, meta: ['klasifikasi' => 'rahsia', 'tujuan' => 'semakan_kadar', 'no_kontrak' => $kontrak->no_kontrak]);
        }

        return $out;
    }
}
