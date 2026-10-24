<?php

namespace App\Modules\Ntf\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Ntf\Contracts\SupervisorResolver;
use App\Modules\Ntf\Jobs\DeliverDigest;
use App\Modules\Ntf\Models\EskalasiLog;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The time-driven half of MOD-NTF, run by Laravel's scheduler (see
 * NtfServiceProvider): scheduled reminders (SRS-NTF-06/07), escalation of
 * unread Kritikal (SRS-NTF-18), digests (SRS-NTF-20) and re-queueing of
 * deliveries the queue lost (SRS-NTF-21).
 */
class Scheduler
{
    public const ESCALATION_TYPE = 'ntf.eskalasi';

    public function __construct(
        private NotificationEngine $engine,
        private TypeRegistry $types,
        private ReminderRegistry $reminders,
        private SupervisorResolver $supervisors,
        private Settings $settings,
        private AuditLogger $audit,
    ) {}

    /** @return int reminders sent */
    public function runReminders(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        foreach ($this->reminders->sources() as $class) {
            try {
                /** @var ReminderSource $source */
                $source = app($class);
                $jenis = $this->types->find($source->type());
                if (! $jenis || ! $jenis->aktif) {
                    continue;
                }

                if ($jenis->hari_peringatan === null) {
                    $jenis->forceFill(['hari_peringatan' => $source->defaultDays()])->save();
                }

                foreach ($jenis->hari_peringatan ?? [] as $days) {
                    foreach ($source->due((int) $days, $today) as $reminder) {
                        // Re-running the job the same day never sends twice.
                        $key = 'peringatan:'.$reminder->key.':'.$days.':'.$today->toDateString();
                        if (Mesej::query()->where('jenis_id', $jenis->id)->where('kunci_dedupe', $key)->exists()) {
                            continue;
                        }

                        $result = $this->engine->dispatch(
                            $jenis->kod,
                            $reminder->recipients,
                            $reminder->data + ['baki_hari' => (int) $days],
                            $reminder->link,
                            $key,
                        );
                        $sent += $result['penerima'];
                    }
                }
            } catch (Throwable $e) {
                // One broken source must not stop the others.
                report($e);
            }
        }

        return $sent;
    }

    /** @return int escalated inbox items */
    public function escalate(): int
    {
        $count = 0;
        $default = $this->settings->int('ntf.tempoh_eskalasi_minit');

        $items = StatusPengguna::query()
            ->whereNull('dibaca_pada')
            ->whereNull('dieskalasi_pada')
            ->whereHas('mesej', fn ($q) => $q->where('keutamaan', 'kritikal'))
            ->with(['mesej.jenis', 'pengguna'])
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $minutes = $item->mesej->jenis->tempoh_eskalasi_minit ?? $default;
            if ($item->created_at->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $supervisors = $this->supervisors->supervisorsOf($item->pengguna);
            $item->forceFill(['dieskalasi_pada' => now()])->save();

            $mesejId = null;
            if ($supervisors !== []) {
                try {
                    $result = $this->engine->dispatch(self::ESCALATION_TYPE, $supervisors, [
                        'subjek_asal' => $item->mesej->subjek,
                        'penerima_asal' => $item->pengguna->name,
                        'tempoh_minit' => $minutes,
                    ], $item->mesej->pautan, 'eskalasi:'.$item->id);
                    $mesejId = $result['mesej']?->id;
                } catch (Throwable $e) {
                    report($e);
                }
            }

            foreach ($supervisors ?: [null] as $supervisor) {
                EskalasiLog::query()->create([
                    'status_pengguna_id' => $item->id,
                    'mesej_id' => $item->mesej_id,
                    'penerima_asal_id' => $item->pengguna_id,
                    'penyelia_id' => $supervisor?->id,
                    'mesej_eskalasi_id' => $mesejId,
                    'tarikh_eskalasi' => now(),
                ]);
            }

            $this->audit->record('eskalasi', 'ntf', 'ntf_status_pengguna', $item->id, $supervisors === [] ? 'gagal' : 'berjaya',
                $supervisors === [] ? 'tiada_penyelia' : null,
                meta: ['penyelia' => array_map(fn (User $u) => $u->id, $supervisors), 'jenis' => $item->mesej->jenis->kod]);

            $count++;
        }

        return $count;
    }

    /** @return int digest e-mails queued */
    public function sendDigests(): int
    {
        $held = Penghantaran::query()
            ->where('status', Penghantaran::DITAHAN)
            ->where('saluran', 'emel')
            ->whereNotNull('penerima_id')
            ->orderBy('id')
            ->get(['id', 'penerima_id'])
            ->groupBy('penerima_id');

        foreach ($held as $userId => $rows) {
            try {
                DeliverDigest::dispatch((int) $userId, $rows->pluck('id')->map(fn ($id) => (int) $id)->all());
            } catch (Throwable $e) {
                Log::warning('NTF: digest dispatch failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }

        return $held->count();
    }

    /** @return int deliveries put back on the queue */
    public function requeueStale(int $olderThanMinutes = 10): int
    {
        $ids = Penghantaran::query()
            ->where(function ($q) use ($olderThanMinutes) {
                $q->where('status', Penghantaran::BERATUR)->where('tarikh_beratur', '<', now()->subMinutes($olderThanMinutes));
            })
            ->orWhere(function ($q) {
                // A retry the queue lost (worker crash, Redis flush).
                $q->where('status', Penghantaran::GAGAL)->where('updated_at', '<', now()->subHours(2));
            })
            ->limit(1000)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->engine->queue((int) $id);
        }

        return $ids->count();
    }
}
