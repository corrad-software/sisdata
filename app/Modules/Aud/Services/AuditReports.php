<?php

namespace App\Modules\Aud\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AUD-15 automatic audit reports over a local-date range:
 * Sulit/Rahsia access, off-hours activity, unusual bulk activity, large
 * exports, access to the audit trail itself — plus failed logins / denials.
 */
class AuditReports
{
    public const TYPES = ['sulit_rahsia', 'luar_waktu', 'kelompok', 'eksport_besar', 'capaian_audit', 'log_masuk_gagal'];

    public function __construct(private AuditSearch $search) {}

    /**
     * @return array{jenis: string, dari: string, hingga: string, jumlah: int, rekod: list<array<string, mixed>>, parameter: array<string, mixed>}
     */
    public function generate(string $type, string $from, string $to, int $limit = 500): array
    {
        $base = fn () => $this->search->query(['dari' => $from, 'hingga' => $to, 'sumber' => AuditSearch::SUMBER_SEMUA]);
        $params = [];

        switch ($type) {
            case 'sulit_rahsia':
                $query = $base()->whereIn('klasifikasi', ['sulit', 'rahsia']);
                break;

            case 'luar_waktu':
                $hours = (array) config('aud.waktu_pejabat');
                $params = $hours;
                // User activity only — scheduled jobs run off-hours by design.
                $query = $base()->whereNotNull('id_pengguna')
                    ->where(function (Builder $q) use ($hours) {
                        $days = array_map('intval', (array) ($hours['hari_bekerja'] ?? [1, 2, 3, 4, 5]));
                        $q->whereRaw('extract(isodow from tarikh_masa_tempatan)::int not in ('.implode(',', $days ?: [0]).')')
                            ->orWhereRaw('tarikh_masa_tempatan::time < ?::time', [(string) ($hours['mula'] ?? '08:00')])
                            ->orWhereRaw('tarikh_masa_tempatan::time >= ?::time', [(string) ($hours['tamat'] ?? '17:00')]);
                        if (! empty($hours['cuti_umum'])) {
                            $q->orWhereIn(DB::raw('tarikh_masa_tempatan::date'), (array) $hours['cuti_umum']);
                        }
                    });
                break;

            case 'kelompok':
                $threshold = (int) config('aud.ambang_kelompok', 100);
                $params = ['ambang_sejam' => $threshold];
                $groups = $base()->whereNotNull('id_pengguna')
                    ->selectRaw("id_pengguna, max(pengecam_pengguna) as pengecam_pengguna, date_trunc('hour', tarikh_masa_tempatan) as jam, count(*) as bilangan, string_agg(distinct jenis_tindakan, ', ') as tindakan")
                    ->groupBy('id_pengguna', 'jam')
                    ->havingRaw('count(*) >= ?', [$threshold])
                    ->orderByDesc('bilangan')
                    ->limit($limit)
                    ->get();

                return [
                    'jenis' => $type, 'dari' => $from, 'hingga' => $to, 'parameter' => $params,
                    'jumlah' => $groups->count(),
                    'rekod' => $groups->map(fn ($g) => [
                        'id_pengguna' => (int) $g->id_pengguna,
                        'pengecam_pengguna' => $g->pengecam_pengguna,
                        'jam' => Carbon::parse($g->jam)->format('Y-m-d H:00'),
                        'bilangan' => (int) $g->bilangan,
                        'tindakan' => $g->tindakan,
                    ])->all(),
                ];

            case 'eksport_besar':
                $threshold = (int) config('aud.ambang_eksport_besar', 1000);
                $params = ['ambang_rekod' => $threshold];
                $query = $base()->where('jenis_tindakan', 'eksport')
                    ->whereRaw("coalesce((meta::jsonb->>'bilangan_rekod')::numeric, 0) >= ?", [$threshold]);
                break;

            case 'capaian_audit':
                $query = $base()->where('modul', 'aud');
                break;

            case 'log_masuk_gagal':
                $query = $base()->where(fn (Builder $q) => $q
                    ->where(fn (Builder $w) => $w->where('jenis_tindakan', 'log_masuk')->where('status_transaksi', 'gagal'))
                    ->orWhere('jenis_tindakan', 'capaian_ditolak'));
                break;

            default:
                throw new \InvalidArgumentException('Jenis laporan tidak sah');
        }

        $total = (clone $query)->count();
        $rows = $this->search->present($query->orderByDesc('tarikh_masa_utc')->limit($limit)->get());

        return [
            'jenis' => $type, 'dari' => $from, 'hingga' => $to, 'parameter' => $params,
            'jumlah' => $total,
            'rekod' => $rows->values()->all(),
        ];
    }
}
