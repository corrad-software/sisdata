<?php

namespace App\Modules\Adm\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Adm\Contracts\Parameters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SRS-ADM-14: retention policy for ADM's own logs — error log, integration
 * log, job run history and health snapshots — each with its own configurable
 * period (critical parameters → changing them needs second-admin approval).
 * Audit records are MOD-AUD's and never touched here.
 */
class PangkasLog extends Command
{
    protected $signature = 'adm:pangkas-log {--dry-run : Count only}';

    protected $description = 'Delete ADM log rows older than their configured retention period';

    /** table => [date column, parameter, default days] */
    public const POLICIES = [
        'adm_log_ralat' => ['tarikh_masa', 'adm.log_ralat_simpan_hari', 180],
        'adm_log_integrasi' => ['tarikh_masa', 'adm.log_integrasi_simpan_hari', 180],
        'adm_tugasan_larian' => ['created_at', 'adm.sejarah_tugasan_simpan_hari', 365],
        'adm_kesihatan' => ['dicatat_pada', 'adm.kesihatan_simpan_hari', 30],
    ];

    public function handle(Parameters $parameters, JobProgress $progress): int
    {
        foreach (self::POLICIES as $table => [$column, $parameter, $default]) {
            $days = max(1, (int) $parameters->get($parameter, $default));
            $query = DB::table($table)->where($column, '<', now()->subDays($days));
            $count = $this->option('dry-run') ? $query->count() : $query->delete();
            $progress->processed($count);
            $this->line(sprintf('%-22s > %4d hari: %d', $table, $days, $count));
        }

        return self::SUCCESS;
    }
}
