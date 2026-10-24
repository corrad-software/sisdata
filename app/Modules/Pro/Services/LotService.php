<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\VersiRekodInduk;
use App\Modules\Pro\Support\NoLot;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Master lot lifecycle (SRS-PRO-13, SRS-PRO-22, SRS-DAT-04):
 *  - every create/update passes the configurable quality rules;
 *  - every applied change becomes a numbered version (before/after kept);
 *  - critical fields (no_lot, mukim, keluasan, bahagian…) do NOT change on
 *    save: a pending version is created and a second officer confirms it via
 *    MOD-WFE flow `pro.perubahan_kritikal`; the change is applied when
 *    WorkflowCompleted arrives with "lulus" (SRS-GEN-03 — no approval logic here).
 */
class LotService
{
    public const FLOW_CRITICAL = 'pro.perubahan_kritikal';

    public const SUBJECT_VERSION = 'versi_rekod_induk';

    public const DEFAULT_CRITICAL = ['no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'bahagian_pemilik'];

    /** Statuses only the system sets (merge / archive). */
    private const SYSTEM_STATUS = ['digabung', 'arkib'];

    public function __construct(
        private PeraturanKualiti $rules,
        private Parameters $parameters,
        private WorkflowEngine $workflow,
        private Notifier $notifier,
        private AccessControl $access,
        private Klasifikasi $klasifikasi,
    ) {}

    /**
     * SRS-RBC-07: an officer can only create / move a lot inside their own
     * daerah / mukim scope and at a classification level they may see.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>
     */
    public function scopeIssues(User $user, array $data): array
    {
        $issues = [];
        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim'] as $type => $field) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null && ! empty($data[$field]) && ! in_array((string) $data[$field], $allowed, true)) {
                $issues[] = ['medan' => $field, 'label' => $this->rules->label($field), 'kod' => 'skop', 'mesej' => (string) __('pro::pro.lot.luar_skop'), 'tahap' => 'ralat', 'nilai' => $data[$field]];
            }
        }
        if (! empty($data['klasifikasi']) && ! $this->klasifikasi->canSee($user, (string) $data['klasifikasi'])) {
            $issues[] = ['medan' => 'klasifikasi', 'label' => $this->rules->label('klasifikasi'), 'kod' => 'skop', 'mesej' => (string) __('pro::pro.lot.klasifikasi_luar_skop'), 'tahap' => 'ralat', 'nilai' => $data['klasifikasi']];
        }

        return $issues;
    }

    /** @return list<string> */
    public function criticalFields(): array
    {
        $value = $this->parameters->get('pro.medan_kritikal', self::DEFAULT_CRITICAL);
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_CRITICAL;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $system  lineage / import fields set by the caller, not the officer
     */
    public function create(array $input, ?User $user, array $system = []): LotTanah
    {
        $data = $this->rules->prepareLot(array_intersect_key($input, array_flip(LotTanah::EDITABLE)));
        $data += ['unit_keluasan' => 'm2', 'status_lot' => 'aktif', 'klasifikasi' => 'dalaman'];
        $this->guardSystemStatus($data);
        PeraturanKualiti::throwIfErrors([
            ...$this->rules->check('lot_tanah', $data),
            ...($user !== null ? $this->scopeIssues($user, $data) : []),
        ]);
        $note = $system['catatan_versi'] ?? null;
        unset($system['catatan_versi']);

        return DB::transaction(function () use ($data, $user, $system, $note) {
            $lot = LotTanah::query()->create([
                ...$data,
                ...$system,
                'no_lot_normal' => NoLot::key((string) $data['no_lot']),
                'kod_negeri' => $data['kod_negeri'] ?? '10',
                'id_pencipta' => $user?->id,
                'id_data_steward' => $data['id_data_steward'] ?? null,
                'versi_rekod' => 1,
            ])->refresh();

            VersiRekodInduk::query()->create([
                'entiti' => 'lot_tanah',
                'id_rekod' => $lot->id,
                'no_versi' => 1,
                'nilai_sebelum' => null,
                'nilai_selepas' => $this->snapshot($lot),
                'kritikal' => false,
                'id_pengguna' => $user?->id,
                'justifikasi' => $note,
                'status_pengesahan' => 'berkuat_kuasa',
            ]);

            return $lot;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{lot: LotTanah, applied: list<string>, pending: VersiRekodInduk|null}
     */
    public function update(LotTanah $lot, array $input, User $user, ?string $justification = null, ?int $expectedVersion = null): array
    {
        // SRS-PRO-01 concurrency control: optimistic lock on versi_rekod.
        if ($expectedVersion !== null && $expectedVersion !== $lot->versi_rekod) {
            throw new HttpException(409, __('pro::pro.lot.konflik_versi', ['versi' => $lot->versi_rekod]));
        }

        $changes = array_intersect_key($input, array_flip(LotTanah::EDITABLE));
        if (array_key_exists('kod_mukim', $changes) && ! array_key_exists('kod_daerah', $changes)) {
            $changes['kod_daerah'] = null; // re-derive from the new mukim
        }
        $current = $lot->only(LotTanah::EDITABLE);
        $merged = $this->rules->prepareLot([...$current, ...$changes]);
        $this->guardSystemStatus(array_intersect_key($merged, $changes));
        PeraturanKualiti::throwIfErrors([
            ...$this->rules->check('lot_tanah', $merged, $lot->id),
            ...$this->scopeIssues($user, $merged),
        ]);

        $diff = [];
        foreach ($merged as $field => $value) {
            if (in_array($field, LotTanah::EDITABLE, true) && ! $this->same($current[$field] ?? null, $value)) {
                $diff[$field] = $value;
            }
        }

        $critical = array_intersect_key($diff, array_flip($this->criticalFields()));
        $plain = array_diff_key($diff, $critical);

        if ($critical !== [] && trim((string) $justification) === '') {
            throw ValidationException::withMessages(['justifikasi' => [__('pro::pro.lot.justifikasi_wajib')]]);
        }
        if ($critical !== [] && VersiRekodInduk::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->where('status_pengesahan', 'menunggu')->exists()) {
            throw new HttpException(409, __('pro::pro.lot.kritikal_tertunda'));
        }

        $pending = null;
        DB::transaction(function () use ($lot, $plain, $critical, $user, $justification, &$pending) {
            if ($plain !== []) {
                $this->apply($lot, $plain, $user->id, $justification, null);
            }

            if ($critical !== []) {
                $pending = VersiRekodInduk::query()->create([
                    'entiti' => 'lot_tanah',
                    'id_rekod' => $lot->id,
                    'nilai_sebelum' => $lot->only(array_keys($critical)),
                    'nilai_selepas' => $critical,
                    'kritikal' => true,
                    'id_pengguna' => $user->id,
                    'justifikasi' => $justification,
                    'status_pengesahan' => 'menunggu',
                ]);

                try {
                    $instance = $this->workflow->start(self::FLOW_CRITICAL, self::SUBJECT_VERSION, $pending->id, $user, [
                        'kod_daerah' => $lot->kod_daerah,
                        'bahagian_pemilik' => $lot->bahagian_pemilik,
                        'medan' => implode(',', array_keys($critical)),
                        'pautan' => '/admin/pro/lot/'.$lot->id.'?tab=dokumen',
                    ], __('pro::pro.lot.tajuk_aliran', ['no_lot' => $lot->no_lot]));
                } catch (InvalidArgumentException) {
                    throw new HttpException(422, __('pro::pro.lot.aliran_tiada', ['kod' => self::FLOW_CRITICAL]));
                }
                $pending->update(['id_aliran_kerja' => $instance]);
            }
        });

        return ['lot' => $lot->refresh(), 'applied' => array_keys($plain), 'pending' => $pending];
    }

    /** WFE outcome of a critical change (SRS-PRO-22 second-officer confirmation). */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW_CRITICAL || $event->subjectType !== self::SUBJECT_VERSION) {
            return;
        }

        $version = VersiRekodInduk::query()->find($event->subjectId);
        if ($version === null || $version->status_pengesahan !== 'menunggu') {
            return;
        }

        $lot = LotTanah::query()->withoutGlobalScope('access')->find($version->id_rekod);
        $outcome = $event->outcome;
        $note = $event->remarks;

        // Segregation of duties: the maker can never be the confirming officer.
        if ($outcome === 'lulus' && $event->decidedBy !== null && $event->decidedBy === $version->id_pengguna) {
            $outcome = 'tolak';
            $note = __('pro::pro.lot.pengesah_sama');
        }

        if ($outcome === 'lulus' && $lot !== null) {
            $merged = $this->rules->prepareLot([...$lot->only(LotTanah::EDITABLE), ...(array) $version->nilai_selepas]);
            $errors = PeraturanKualiti::errors($this->rules->check('lot_tanah', $merged, $lot->id));
            if ($errors !== []) {
                $outcome = 'tolak';
                $note = implode(' ', array_map(fn ($e) => $e['label'].': '.$e['mesej'], $errors));
            } else {
                DB::transaction(function () use ($lot, $version, $event) {
                    $applied = $this->apply($lot, (array) $version->nilai_selepas, $version->id_pengguna, $version->justifikasi, $version);
                    $version->update([
                        'no_versi' => $applied,
                        'status_pengesahan' => 'lulus',
                        'id_pengesah_kedua' => $event->decidedBy,
                        'catatan_pengesahan' => $event->remarks,
                        'tarikh_pengesahan' => now(),
                    ]);
                });
            }
        }

        if ($outcome !== 'lulus') {
            $version->update([
                'status_pengesahan' => in_array($outcome, ['tolak', 'ditarik_balik', 'dibatalkan'], true) ? $outcome : 'tolak',
                'id_pengesah_kedua' => $event->decidedBy,
                'catatan_pengesahan' => $note,
                'tarikh_pengesahan' => now(),
            ]);
        }

        $initiator = $version->id_pengguna ? User::query()->find($version->id_pengguna) : null;
        if ($initiator !== null && $lot !== null) {
            try {
                $this->notifier->send('pro.perubahan_kritikal_keputusan', $initiator, [
                    'no_lot' => $lot->no_lot,
                    'keputusan' => __('pro::pro.keputusan.'.($outcome === 'lulus' ? 'lulus' : 'tolak')),
                ], '/admin/pro/lot/'.$lot->id);
            } catch (Throwable) {
                // notification failure never blocks the outcome
            }
        }
    }

    /**
     * Apply $changes as the next version. Returns the new version number.
     *
     * @param  array<string, mixed>  $changes
     */
    private function apply(LotTanah $lot, array $changes, ?int $userId, ?string $justification, ?VersiRekodInduk $version): int
    {
        $before = $lot->only(array_keys($changes));
        $next = $lot->versi_rekod + 1;

        $attributes = [...$changes, 'versi_rekod' => $next];
        if (array_key_exists('no_lot', $changes)) {
            $attributes['no_lot_normal'] = NoLot::key((string) $changes['no_lot']);
        }

        $reason = $justification ?: __('pro::pro.lot.sebab_lalai');
        AuditScope::withReason($reason, fn () => $lot->update($attributes));

        if ($version === null) {
            VersiRekodInduk::query()->create([
                'entiti' => 'lot_tanah',
                'id_rekod' => $lot->id,
                'no_versi' => $next,
                'nilai_sebelum' => $before,
                'nilai_selepas' => $changes,
                'kritikal' => false,
                'id_pengguna' => $userId,
                'justifikasi' => $justification,
                'status_pengesahan' => 'berkuat_kuasa',
            ]);
        }

        return $next;
    }

    /** @return array<string, mixed> */
    public function snapshot(LotTanah $lot): array
    {
        return $lot->only(LotTanah::EDITABLE);
    }

    /**
     * Field-by-field before/after for one version (SRS-DAT-04 compare).
     *
     * @return list<array{medan: string, label: string, sebelum: mixed, selepas: mixed}>
     */
    public function compare(VersiRekodInduk $version): array
    {
        $before = (array) ($version->nilai_sebelum ?? []);
        $after = (array) $version->nilai_selepas;
        $rows = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $rows[] = [
                'medan' => (string) $field,
                'label' => $this->rules->label((string) $field),
                'sebelum' => $before[$field] ?? null,
                'selepas' => $after[$field] ?? null,
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    private function guardSystemStatus(array $data): void
    {
        if (isset($data['status_lot']) && in_array($data['status_lot'], self::SYSTEM_STATUS, true)) {
            throw ValidationException::withMessages(['status_lot' => [__('pro::pro.lot.status_sistem')]]);
        }
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.00005;
        }
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        return (string) $a === (string) $b && ($a === null) === ($b === null);
    }
}
