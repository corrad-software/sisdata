<?php

namespace App\Modules\Lap\Jobs;

use App\Models\User;
use App\Modules\Lap\Models\LogPenjanaan;
use App\Modules\Lap\Services\Penjana;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Background generation of a large report (SRS-LAP-07): runs on the queue
 * (reporting connection = read replica), stores the file privately and
 * notifies the requester through MOD-NTF when it is ready. The link opens
 * the generation log, whose download is owner-only.
 */
class JanaLaporan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $logId) {}

    public function handle(Penjana $penjana, Notifier $notifier): void
    {
        $log = LogPenjanaan::query()->find($this->logId);
        $user = $log?->id_pengguna ? User::query()->find($log->id_pengguna) : null;
        if (! $log || ! $user) {
            return;
        }
        $log = $penjana->toFile($log, $user);
        try {
            $notifier->send('lap.laporan_siap', $user, [
                'tajuk' => $log->tajuk,
                'status' => __('lap::lap.status.'.$log->status),
                'bilangan_rekod' => $log->bilangan_rekod,
                'masa' => Carbon::now()->format('d/m/Y H:i'),
            ], '/admin/lap/log?id='.$log->id, 'lap.siap.'.$log->id);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
