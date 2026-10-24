<?php

namespace App\Modules\Adm\Services\Tugasan;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Adm\Models\Tugasan;
use App\Modules\Adm\Models\TugasanLarian;
use App\Modules\Adm\Services\Amaran;
use App\Modules\Adm\Services\Logging\Redactor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes one scheduled job (SRS-ADM-06..10):
 *  - an atomic cache lock per job, so two instances never run at once — a
 *    second trigger is recorded as "dilangkau" (SRS-ADM-09);
 *  - each attempt is a row in adm_tugasan_larian with start, end, duration,
 *    status, records processed and (redacted) error (SRS-ADM-08);
 *  - failed attempts are retried up to `cubaan_semula` times (SRS-ADM-09);
 *  - a final failure alerts Pentadbir Sistem through MOD-NTF (SRS-ADM-10).
 */
class PelaksanaTugasan
{
    public function __construct(
        private Kernel $artisan,
        private PembilangRekod $counter,
        private ArahanDibenarkan $allowed,
        private Amaran $amaran,
    ) {}

    public static function lockKey(int $jobId): string
    {
        return 'adm:tugasan:kunci:'.$jobId;
    }

    /** Create the "beratur" row for a manual run before it is queued. */
    public function queue(Tugasan $tugasan, ?int $userId): TugasanLarian
    {
        return TugasanLarian::query()->create([
            'id_tugasan' => $tugasan->id,
            'pencetus' => 'manual',
            'id_pengguna' => $userId,
            'status' => TugasanLarian::BERATUR,
            'id_korelasi' => AssignRequestId::current(),
        ]);
    }

    public function run(int $jobId, string $trigger = 'jadual', ?int $userId = null, ?int $queuedRunId = null): ?TugasanLarian
    {
        $tugasan = Tugasan::query()->find($jobId);
        if (! $tugasan) {
            return null;
        }

        $this->counter->reset();
        $queued = $queuedRunId ? TugasanLarian::query()->find($queuedRunId) : null;
        $correlation = $queued !== null && $queued->id_korelasi !== null ? $queued->id_korelasi : (string) Str::uuid();
        Context::add('request_id', $correlation);
        Context::add('modul', 'adm');

        $lock = Cache::lock(self::lockKey($tugasan->id), max(60, $tugasan->tempoh_tamat_masa_saat));

        if (! $lock->get()) {
            return $this->finish($queued ?? $this->start($tugasan, $trigger, $userId, 1, $correlation), TugasanLarian::DILANGKAU, null, __('adm::adm.tugasan.already_running'), now()->getTimestampMs());
        }

        try {
            $tugasan->forceFill(['terakhir_mula_pada' => now()])->saveQuietly();
            $attempts = 1 + max(0, $tugasan->cubaan_semula);
            $run = null;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $run = $attempt === 1 && $queued
                    ? $this->begin($queued, $attempt)
                    : $this->start($tugasan, $trigger, $userId, $attempt, $correlation);

                $run = $this->execute($tugasan, $run);
                if ($run->status === TugasanLarian::BERJAYA) {
                    break;
                }
            }

            $tugasan->forceFill(['terakhir_status' => $run->status])->saveQuietly();

            if ($run->status === TugasanLarian::GAGAL) {
                $this->amaran->ict('adm.tugasan_gagal', [
                    'tugasan' => $tugasan->nama,
                    'kod' => $tugasan->kod,
                    'cubaan' => $run->cubaan,
                    'ralat' => Str::limit((string) $run->mesej_ralat, 200),
                ], '/admin/pentadbiran/tugasan-berjadual/'.$tugasan->id, 'tugasan_gagal:'.$tugasan->id.':'.$run->id);
            }

            return $run;
        } finally {
            $lock->release();
        }
    }

    private function execute(Tugasan $tugasan, TugasanLarian $run): TugasanLarian
    {
        $startedMs = now()->getTimestampMs();
        $this->counter->reset();

        if (! $this->allowed->allowed($tugasan->arahan)) {
            return $this->finish($run, TugasanLarian::GAGAL, null, __('adm::adm.tugasan.command_not_allowed', ['arahan' => $tugasan->arahan]), $startedMs);
        }

        try {
            $exit = $this->artisan->call($tugasan->arahan, $tugasan->argumen ?? []);
            $output = $this->artisan->output();

            return $this->finish(
                $run,
                $exit === 0 ? TugasanLarian::BERJAYA : TugasanLarian::GAGAL,
                $output,
                $exit === 0 ? null : __('adm::adm.tugasan.exit_code', ['kod' => $exit]).' '.Str::limit(trim($output), 1000),
                $startedMs,
            );
        } catch (Throwable $e) {
            report($e);

            return $this->finish($run, TugasanLarian::GAGAL, null, $e::class.': '.$e->getMessage(), $startedMs);
        }
    }

    private function start(Tugasan $tugasan, string $trigger, ?int $userId, int $attempt, string $correlation): TugasanLarian
    {
        return TugasanLarian::query()->create([
            'id_tugasan' => $tugasan->id,
            'pencetus' => $trigger,
            'id_pengguna' => $userId,
            'cubaan' => $attempt,
            'tarikh_mula' => now(),
            'status' => TugasanLarian::BERJALAN,
            'id_korelasi' => $correlation,
        ]);
    }

    private function begin(TugasanLarian $run, int $attempt): TugasanLarian
    {
        $run->update(['status' => TugasanLarian::BERJALAN, 'tarikh_mula' => now(), 'cubaan' => $attempt]);

        return $run;
    }

    private function finish(TugasanLarian $run, string $status, ?string $output, ?string $error, int $startedMs): TugasanLarian
    {
        $run->update([
            'tarikh_mula' => $run->tarikh_mula ?? now(),
            'tarikh_tamat' => now(),
            'tempoh_saat' => round(max(0, now()->getTimestampMs() - $startedMs) / 1000, 3),
            'status' => $status,
            'bilangan_rekod' => $this->counter->count(),
            'mesej_ralat' => Redactor::string($error !== null ? Str::limit($error, 4000) : null),
            'output' => Redactor::string($output !== null ? Str::limit($output, 10000) : null),
        ]);

        return $run;
    }
}
