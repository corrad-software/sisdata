<?php

namespace App\Modules\Aud\Services;

use App\Modules\Aud\Models\AuditRetensiPolisi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SRS-AUD-20/21: moves records older than the online retention period
 * (policy, never below 24 months) into audit_arkib. The move is done by the
 * SECURITY DEFINER function aud_arkib_pindah(), the only path the append-only
 * triggers let through; archived rows keep their hash and stay searchable.
 */
class AuditArchiver
{
    public function __construct(private TrailAuditLogger $logger) {}

    /**
     * @return array{dipindah: int, had: string, kelompok: string, bulan: int}
     */
    public function run(): array
    {
        $months = AuditRetensiPolisi::current()->onlineMonths();
        $cutoff = Carbon::now('UTC')->subMonthsNoOverflow($months);
        $batch = (string) Str::uuid();

        $moved = (int) DB::selectOne('select aud_arkib_pindah(?::timestamptz, ?) as n', [$cutoff->format('Y-m-d H:i:s.uP'), $batch])->n;

        $this->logger->record('arkib', 'aud', 'audit_rekod', null, 'berjaya', null, [], [
            'bilangan_rekod' => $moved,
            'had_utc' => $cutoff->toIso8601ZuluString(),
            'tempoh_dalam_talian_bulan' => $months,
            'id_kelompok_arkib' => $batch,
        ]);

        return ['dipindah' => $moved, 'had' => $cutoff->toIso8601ZuluString(), 'kelompok' => $batch, 'bulan' => $months];
    }
}
