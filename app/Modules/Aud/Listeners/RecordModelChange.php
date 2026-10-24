<?php

namespace App\Modules\Aud\Listeners;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Aud\Services\AuditEntry;
use App\Modules\Aud\Services\AuditWriter;
use App\Modules\Aud\Services\SensitiveFieldMasker;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

/**
 * SRS-AUD-01/05/09: one wildcard listener on every Eloquent model's
 * created/updated/deleted/restored events, registered by AudServiceProvider —
 * modules write no audit code for plain data changes and cannot opt out.
 * Every changed field becomes its own audit_perubahan_medan row.
 *
 * Writes that bypass Eloquent (DB::table()->update(), raw SQL, mass
 * Model::query()->update()) do not fire model events and are NOT captured
 * here; modules must use Eloquent for audited data.
 */
class RecordModelChange
{
    private const ACTIONS = [
        'created' => 'cipta',
        'updated' => 'kemas_kini',
        'deleted' => 'padam',
        'restored' => 'pulih',
    ];

    public function __construct(private AuditWriter $writer, private SensitiveFieldMasker $masker) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;
        if (! $model instanceof Model) {
            return;
        }

        try {
            $entry = $this->entryFor($event, $model);
        } catch (Throwable $e) {
            $this->writer->incident('model', 'aud', $e, null);

            return;
        }

        if ($entry) {
            $this->writer->write($entry);
        }
    }

    private function entryFor(string $event, Model $model): ?AuditEntry
    {
        $table = $model->getTable();
        if (in_array($table, (array) config('aud.jadual_dikecualikan', []), true)) {
            return null;
        }

        $type = str_replace('eloquent.', '', strstr($event, ':', true) ?: $event);
        $action = self::ACTIONS[$type] ?? null;
        if (! $action) {
            return null;
        }

        $ignored = [
            ...((array) config('aud.medan_diabaikan.*', [])),
            ...((array) config('aud.medan_diabaikan.'.$table, [])),
            ...($model instanceof Auditable ? $model->auditExcept() : []),
        ];
        $sensitive = [...$model->getHidden(), ...($model instanceof Auditable ? $model->auditSensitive() : [])];

        $pairs = match ($type) {
            'created' => array_map(fn ($v) => [null, $v], $model->getAttributes()),
            'updated' => $this->updatedPairs($model),
            'deleted' => $this->deletedPairs($model),
            default => [], // restored: the deleted_at change is already in the "updated" entry restore() fires
        };

        $changes = [];
        foreach ($pairs as $field => [$old, $new]) {
            // The key is already id_rekod; it never "changes" on create/delete.
            if (in_array($field, $ignored, true) || ($type !== 'updated' && $field === $model->getKeyName())) {
                continue;
            }

            $masked = $this->masker->isSensitive((string) $field, $sensitive);
            $old = $this->stringify($old);
            $new = $this->stringify($new);
            if ($masked) {
                [$old, $new] = [$this->masker->mask($old), $this->masker->mask($new)];
            }
            $changes[] = [(string) $field, $old, $new, $masked];
        }

        if ($type === 'updated' && $changes === []) {
            return null; // only ignored fields changed (e.g. last_used_at)
        }

        $meta = [];
        if ($type === 'deleted' && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $meta['padam_lembut'] = ! $model->isForceDeleting(); // @phpstan-ignore method.notFound
        }

        return new AuditEntry(
            action: $action,
            module: $this->moduleOf($model),
            entityType: $table,
            entityId: $model->getKey() === null ? null : (string) $model->getKey(),
            changes: $changes,
            meta: $meta,
            classification: $this->classificationOf($model),
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function updatedPairs(Model $model): array
    {
        $pairs = [];
        foreach ($model->getChanges() as $field => $new) {
            $pairs[$field] = [$model->getRawOriginal($field), $new];
        }

        return $pairs;
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function deletedPairs(Model $model): array
    {
        $original = $model->getRawOriginal() ?: $model->getAttributes();

        // Soft delete: the only real change is deleted_at.
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting()) { // @phpstan-ignore method.notFound
            $column = $model->getDeletedAtColumn(); // @phpstan-ignore method.notFound

            return [$column => [null, $model->getAttributes()[$column] ?? null]];
        }

        return array_map(fn ($v) => [$v, null], $original);
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    private function moduleOf(Model $model): string
    {
        if ($model instanceof Auditable) {
            return $model->auditModule();
        }

        if (preg_match('/^App\\\\Modules\\\\([A-Za-z]+)\\\\/', $model::class, $m)) {
            return strtolower($m[1]);
        }

        return (string) (config('aud.modul_jadual.'.$model->getTable()) ?? 'teras');
    }

    private function classificationOf(Model $model): ?string
    {
        if ($model instanceof HasClassification) {
            return $model->auditClassification();
        }

        $value = $model->getAttributes()['klasifikasi'] ?? null;

        return is_string($value) ? $value : null;
    }
}
