<?php

namespace App\Modules\Adm\Services\Kesihatan;

use App\Modules\Adm\Contracts\Parameters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Queue depth (vs threshold adm.ambang_baris_gilir), failed jobs, and
 * whether a Horizon supervisor is running (SRS-ADM-16/17).
 */
class SemakanBarisGilir implements Semakan
{
    public function __construct(private Parameters $parameters) {}

    public function kod(): string
    {
        return 'baris_gilir';
    }

    public function label(): string
    {
        return 'Baris gilir (Horizon)';
    }

    public function jalankan(): array
    {
        try {
            $connection = (string) config('queue.default');
            $size = Queue::connection($connection)->size();
            $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();
            $threshold = (int) $this->parameters->get('adm.ambang_baris_gilir', 1000);

            $horizon = null;
            if ($connection === 'redis' && interface_exists(MasterSupervisorRepository::class)) {
                try {
                    $horizon = count(app(MasterSupervisorRepository::class)->all()) > 0;
                } catch (Throwable) {
                    $horizon = false;
                }
            }

            $status = self::OK;
            $message = null;
            if ($size > $threshold) {
                $status = self::AMARAN;
                $message = __('adm::adm.kesihatan.queue_over', ['saiz' => $size, 'ambang' => $threshold]);
            } elseif ($horizon === false) {
                $status = self::AMARAN;
                $message = __('adm::adm.kesihatan.horizon_down');
            }

            return ['status' => $status, 'mesej' => $message, 'butiran' => [
                'sambungan' => $connection,
                'saiz' => $size,
                'ambang' => $threshold,
                'gagal' => $failed,
                'horizon_aktif' => $horizon,
            ]];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
