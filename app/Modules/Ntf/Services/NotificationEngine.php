<?php

namespace App\Modules\Ntf\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Contracts\SensitiveDataRefused;
use App\Modules\Ntf\Jobs\DeliverNotification;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\KeutamaanPengguna;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * The single notification engine (SRS-NTF-02), following SRS Modul 15's
 * process flow: match type → resolve recipients (explicit + rules) → apply
 * preferences unless mandatory → render template, refusing sensitive fields →
 * dedupe within the quiet period → persist message + one delivery row per
 * recipient × channel → queue each delivery.
 */
class NotificationEngine implements Notifier
{
    public function __construct(
        private TypeRegistry $types,
        private TemplateRenderer $renderer,
        private RecipientDirectory $directory,
        private Settings $settings,
        private AuditLogger $audit,
    ) {}

    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $this->dispatch($type, $recipients, $data, $link, $dedupeKey);
    }

    /**
     * @param  User|iterable<User>  $recipients
     * @param  array<string, mixed>  $data
     * @return array{mesej: Mesej|null, penerima: int, dilangkau: int}
     *
     * @throws InvalidArgumentException unknown type, non-scalar data or external link
     * @throws SensitiveDataRefused
     */
    public function dispatch(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null, ?User $sender = null): array
    {
        $jenis = $this->types->find($type) ?? throw new InvalidArgumentException("Unknown notification type \"{$type}\". Register it with NotificationTypes first.");

        if (! $jenis->aktif) {
            return ['mesej' => null, 'penerima' => 0, 'dilangkau' => 0];
        }

        $data = $this->guardData($jenis, $data);
        $link = $this->guardLink($link);
        $users = $this->recipients($jenis, $recipients);

        if ($users === []) {
            Log::info('NTF: no recipients', ['type' => $type]);

            return ['mesej' => null, 'penerima' => 0, 'dilangkau' => 0];
        }

        $key = $dedupeKey ?? sha1($type.'|'.$link.'|'.json_encode($data));
        $quiet = $jenis->tempoh_pendiaman_minit;
        $recent = $quiet > 0 ? $this->recentlyNotified($jenis, $key, array_keys($users), $quiet) : [];
        $prefs = KeutamaanPengguna::query()->where('jenis_id', $jenis->id)->whereIn('pengguna_id', array_keys($users))->get()->keyBy('pengguna_id');

        $plan = [];
        $skipped = 0;
        foreach ($users as $id => $user) {
            $pref = $prefs->get($id);
            $channels = $this->channelsFor($jenis, $pref);

            if (isset($recent[$id]) || $channels === []) {
                $skipped++;

                continue;
            }

            $digest = $jenis->bolehRingkasan() && ($jenis->kekerapan === 'ringkasan' || (! $jenis->wajib && $pref?->ringkasan));
            $plan[$id] = [$channels, $digest];
        }

        if ($plan === []) {
            return ['mesej' => null, 'penerima' => 0, 'dilangkau' => $skipped];
        }

        $templat = $jenis->templatAktif;

        [$mesej, $queued] = DB::transaction(function () use ($jenis, $templat, $data, $link, $key, $sender, $plan) {
            $mesej = Mesej::query()->create([
                'jenis_id' => $jenis->id,
                'templat_id' => $templat?->id,
                'keutamaan' => $jenis->keutamaan,
                'modul' => $jenis->modul,
                'subjek' => Str::limit($this->renderer->render($templat->subjek ?? $jenis->nama, $data), 250),
                'kandungan' => $this->renderer->render($templat->kandungan ?? '', $data),
                'pautan' => $link,
                'data' => $data,
                'kunci_dedupe' => Str::limit($key, 191, ''),
                'dicipta_oleh' => $sender?->id,
            ]);

            $queued = [];
            foreach ($plan as $userId => [$channels, $digest]) {
                foreach ($channels as $channel) {
                    $held = $digest && $channel === 'emel';
                    $row = Penghantaran::query()->create([
                        'mesej_id' => $mesej->id,
                        'penerima_id' => $userId,
                        'saluran' => $channel,
                        'status' => $held ? Penghantaran::DITAHAN : Penghantaran::BERATUR,
                        'ringkasan' => $held,
                        'tarikh_beratur' => now(),
                    ]);
                    if (! $held) {
                        $queued[] = $row->id;
                    }
                }
            }

            return [$mesej, $queued];
        });

        // Queue only once the caller's transaction (if any) has committed, so
        // workers see the rows; a queue outage never breaks the caller.
        DB::afterCommit(function () use ($queued) {
            foreach ($queued as $id) {
                $this->queue($id);
            }
        });

        return ['mesej' => $mesej, 'penerima' => count($plan), 'dilangkau' => $skipped];
    }

    /** Put one delivery on the queue. If the queue is down the row stays "beratur" and ntf:proses-tertunggak picks it up (SRS-NTF-21). */
    public function queue(int $deliveryId): void
    {
        try {
            DeliverNotification::dispatch($deliveryId);
        } catch (Throwable $e) {
            Log::warning('NTF: could not queue delivery, will be re-queued', ['id' => $deliveryId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Mandatory types ignore preferences (SRS-NTF-10); otherwise the user may
     * opt out or pick channels (SRS-NTF-09).
     *
     * @return list<string>
     */
    public function channelsFor(Jenis $jenis, ?KeutamaanPengguna $pref): array
    {
        if ($jenis->wajib || $pref === null) {
            $channels = $jenis->saluran_lalai;
        } elseif (! $pref->aktif) {
            return [];
        } else {
            $channels = $pref->saluran ?? $jenis->saluran_lalai;
        }

        return array_values(array_intersect(Jenis::SALURAN, $channels));
    }

    /**
     * SRS-NTF-19: refuse fields flagged Sulit/Rahsia, keep only declared
     * template fields, scalars only.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, scalar|null>
     */
    private function guardData(Jenis $jenis, array $data): array
    {
        $sensitive = array_values(array_intersect(
            array_keys($data),
            array_merge($jenis->medan_sensitif ?? [], $this->settings->sensitiveFields()),
        ));

        if ($sensitive !== []) {
            $this->audit->record('tolak', 'ntf', 'ntf_jenis', $jenis->id, 'gagal', 'medan_sensitif', meta: ['jenis' => $jenis->kod, 'medan' => $sensitive]);

            throw new SensitiveDataRefused($jenis->kod, $sensitive);
        }

        $clean = [];
        foreach ($data as $field => $value) {
            if (! in_array($field, $jenis->medan ?? [], true)) {
                continue;
            }
            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException("Notification field \"{$field}\" must be a scalar.");
            }
            $clean[$field] = is_string($value) ? Str::limit($value, 500) : $value;
        }

        return $clean;
    }

    private function guardLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }

        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            throw new InvalidArgumentException('Notification links must be internal frontend paths, e.g. "/admin/…".');
        }

        return Str::limit($link, 250, '');
    }

    /**
     * Explicit recipients plus the type's admin-configured rules; active users only.
     *
     * @param  User|iterable<User>  $recipients
     * @return array<int, User>
     */
    private function recipients(Jenis $jenis, User|iterable $recipients): array
    {
        $users = [];
        foreach ($recipients instanceof User ? [$recipients] : $recipients as $user) {
            $users[$user->id] = $user;
        }

        foreach ($jenis->peraturan()->where('aktif', true)->get() as $rule) {
            foreach ($this->directory->resolve($rule->sasaran, $rule->nilai) as $user) {
                $users[$user->id] = $user;
            }
        }

        return array_filter($users, fn (User $u) => $u->is_active !== false);
    }

    /**
     * SRS-NTF-22: recipients who already got this event inside the quiet period.
     *
     * @param  list<int>  $userIds
     * @return array<int, bool>
     */
    private function recentlyNotified(Jenis $jenis, string $key, array $userIds, int $minutes): array
    {
        return Penghantaran::query()
            ->join('ntf_mesej', 'ntf_mesej.id', '=', 'ntf_penghantaran.mesej_id')
            ->where('ntf_mesej.jenis_id', $jenis->id)
            ->where('ntf_mesej.kunci_dedupe', Str::limit($key, 191, ''))
            ->whereIn('ntf_penghantaran.penerima_id', $userIds)
            ->where('ntf_penghantaran.created_at', '>=', now()->subMinutes($minutes))
            ->distinct()
            ->pluck('ntf_penghantaran.penerima_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }
}
