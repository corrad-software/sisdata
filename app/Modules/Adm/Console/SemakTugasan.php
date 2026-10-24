<?php

namespace App\Modules\Adm\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Models\Tugasan;
use App\Modules\Adm\Models\TugasanLarian;
use App\Modules\Adm\Services\Amaran;
use Illuminate\Console\Command;
use Throwable;

/**
 * SRS-ADM-10: alert when an active job did not run within its schedule
 * (+ tolerance), and close runs stuck in "berjalan" past their timeout.
 */
class SemakTugasan extends Command
{
    protected $signature = 'adm:semak-tugasan';

    protected $description = 'Detect scheduled jobs that missed their schedule and stuck runs';

    public function handle(Amaran $amaran, Parameters $parameters, JobProgress $progress): int
    {
        $defaultTolerance = (int) $parameters->get('adm.tugasan_toleransi_lewat_minit', 15);
        $missed = 0;

        foreach (Tugasan::query()->where('aktif', true)->get() as $tugasan) {
            $progress->processed();
            $this->closeStuck($tugasan);

            try {
                $due = $tugasan->previousDue();
            } catch (Throwable) {
                continue;
            }

            $tolerance = $tugasan->toleransi_lewat_minit ?? $defaultTolerance;
            $lateBy = $due->copy()->addMinutes($tolerance);

            if ($due->lt($tugasan->created_at) || now()->lt($lateBy)) {
                continue; // not yet due (or job created after that slot)
            }

            $ranSince = TugasanLarian::query()->where('id_tugasan', $tugasan->id)
                ->where(fn ($q) => $q->where('tarikh_mula', '>=', $due->copy()->subMinute())->orWhere(fn ($q) => $q->whereNull('tarikh_mula')->where('created_at', '>=', $due->copy()->subMinute())))
                ->exists();

            if (! $ranSince) {
                $missed++;
                $amaran->ict('adm.tugasan_terlepas', [
                    'tugasan' => $tugasan->nama,
                    'kod' => $tugasan->kod,
                    'dijadualkan' => $due->format('Y-m-d H:i'),
                ], '/admin/pentadbiran/tugasan-berjadual/'.$tugasan->id, 'tugasan_terlepas:'.$tugasan->id.':'.$due->format('YmdHi'));
                $this->warn("Missed: {$tugasan->kod} (due {$due->format('Y-m-d H:i')})");
            }
        }

        $this->info("Missed schedules: {$missed}");

        return self::SUCCESS;
    }

    private function closeStuck(Tugasan $tugasan): void
    {
        TugasanLarian::query()
            ->where('id_tugasan', $tugasan->id)
            ->where('status', TugasanLarian::BERJALAN)
            ->where('tarikh_mula', '<', now()->subSeconds(max(60, $tugasan->tempoh_tamat_masa_saat) * 2))
            ->get()
            ->each(fn (TugasanLarian $run) => $run->update([
                'status' => TugasanLarian::GAGAL,
                'tarikh_tamat' => now(),
                'mesej_ralat' => __('adm::adm.tugasan.stuck'),
            ]));
    }
}
