<?php

namespace App\Modules\Pws\Services;

use App\Models\User;
use App\Modules\Pws\Models\StatistikTerbitan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Aggregate public statistics (no record-level rows, no personal data):
 * portal applications by type and status, online payments by channel,
 * premises currently offered (MOD-PJK public catalogue) and the confirmed
 * benefit-distribution totals (SRS-PJK-25: confirmed figures only). Counts
 * below the suppression threshold are shown as "<N" so a small district /
 * status combination cannot identify an applicant.
 */
class StatistikService
{
    public const AMBANG = 3;

    public function __construct(private InternalApi $api) {}

    /** @return array<string, mixed> */
    public function compute(string $period): array
    {
        [$from, $to] = $this->range($period);
        $apps = DB::table('pws_permohonan')->whereBetween('created_at', [$from, $to])
            ->select('jenis', 'status', DB::raw('count(*) as n'))->groupBy('jenis', 'status')->get();
        $byType = [];
        foreach ($apps as $r) {
            $byType[$r->jenis]['jumlah'] = ($byType[$r->jenis]['jumlah'] ?? 0) + (int) $r->n;
            $byType[$r->jenis]['mengikut_status'][$r->status] = (int) $r->n;
        }
        foreach ($byType as $type => $row) {
            $byType[$type]['jumlah'] = $this->suppress($row['jumlah']);
            $byType[$type]['mengikut_status'] = array_map(fn (int $n) => $this->suppress($n), $row['mengikut_status']);
        }

        $payments = DB::table('pws_sesi_bayaran')->where('status', 'berjaya')->whereBetween('cap_masa_pengesahan', [$from, $to])
            ->select('saluran_bayaran', DB::raw('count(*) as n'), DB::raw('sum(jumlah) as amaun'))->groupBy('saluran_bayaran')->get()
            ->map(fn ($r) => ['saluran' => $r->saluran_bayaran, 'bilangan' => $this->suppress((int) $r->n), 'amaun' => (int) $r->n < self::AMBANG ? null : number_format((float) $r->amaun, 2, '.', '')])->all();

        $catalogue = $this->api->call('GET', 'pjk/awam/katalog', ['per_page' => 1]);
        $distribution = $this->api->call('GET', 'pjk/awam/agihan'); // confirmed figures only (PJK filters)
        $agihan = [];
        foreach ((array) ($distribution['body']['data'] ?? []) as $g) {
            $key = ($g['kategori_hartanah'] ?? '-').'|'.($g['status_perolehan'] ?? '-');
            $agihan[$key] ??= ['kategori_hartanah' => $g['kategori_hartanah'] ?? null, 'status_perolehan' => $g['status_perolehan'] ?? null, 'agihan' => 0.0];
            $agihan[$key]['agihan'] += (float) ($g['agihan'] ?? 0);
        }

        return [
            'tempoh' => $period,
            'dijana_pada' => now()->toIso8601String(),
            'permohonan' => $byType,
            'bayaran_dalam_talian' => array_values($payments),
            'premis_ditawarkan' => $catalogue['status'] === 200 ? (int) ($catalogue['body']['meta']['total'] ?? 0) : null,
            'agihan_manfaat_disahkan' => array_values(array_map(fn ($a) => [...$a, 'agihan' => number_format($a['agihan'], 2, '.', '')], $agihan)),
            'nota' => __('pws::pws.statistik.nota', ['n' => self::AMBANG]),
        ];
    }

    /** @param array<string, mixed> $input */
    public function snapshot(array $input, User $by): StatistikTerbitan
    {
        $data = Validator::make($input, [
            'tajuk' => ['required', 'string', 'max:200'],
            'tempoh' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
        ], [], ['tajuk' => 'tajuk', 'tempoh' => 'tempoh'])->validate();

        return StatistikTerbitan::query()->create([
            'tajuk' => $data['tajuk'], 'tempoh' => $data['tempoh'], 'data' => $this->compute($data['tempoh']),
            'status' => 'draf', 'dicipta_oleh' => $by->id,
        ]);
    }

    private function suppress(int $n): int|string
    {
        return $n > 0 && $n < self::AMBANG ? '<'.self::AMBANG : $n;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(string $period): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01-01')->startOfYear();

        return [$start, $start->copy()->endOfYear()];
    }
}
