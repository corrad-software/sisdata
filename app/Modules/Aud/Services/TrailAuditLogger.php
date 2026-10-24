<?php

namespace App\Modules\Aud\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use Throwable;

/** MOD-AUD's implementation of the AuditLogger contract (explicit entries, SRS-AUD-02). */
class TrailAuditLogger implements AuditLogger
{
    public function __construct(private AuditWriter $writer, private SensitiveFieldMasker $masker) {}

    public function record(
        string $action,
        string $module,
        ?string $entityType = null,
        string|int|null $entityId = null,
        string $status = 'berjaya',
        ?string $reason = null,
        array $changes = [],
        array $meta = [],
    ): void {
        $this->recordAs(null, $action, $module, $entityType, $entityId, $status, $reason, $changes, $meta);
    }

    /**
     * Same as record(), with an explicit actor (e.g. the user logging in, who
     * isn't authenticated on the request yet).
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @param  array<string, mixed>  $meta
     */
    public function recordAs(
        ?User $actor,
        string $action,
        string $module,
        ?string $entityType = null,
        string|int|null $entityId = null,
        string $status = 'berjaya',
        ?string $reason = null,
        array $changes = [],
        array $meta = [],
        ?string $actorIdentifier = null,
    ): void {
        try {
            $rows = [];
            foreach ($changes as $field => $pair) {
                $masked = $this->masker->isSensitive((string) $field);
                $old = $this->stringify($pair[0] ?? null);
                $new = $this->stringify($pair[1] ?? null);
                $rows[] = [(string) $field, $masked ? $this->masker->mask($old) : $old, $masked ? $this->masker->mask($new) : $new, $masked];
            }

            $classification = isset($meta['klasifikasi']) && is_string($meta['klasifikasi']) ? $meta['klasifikasi'] : null;

            $this->writer->write(new AuditEntry(
                action: $action,
                module: $module,
                entityType: $entityType,
                entityId: $entityId === null ? null : (string) $entityId,
                status: $status,
                reason: $reason,
                changes: $rows,
                meta: $this->masker->maskArray($meta),
                classification: $classification,
                actor: $actor,
                actorIdentifier: $actorIdentifier,
            ));
        } catch (Throwable $e) {
            $this->writer->incident($action, $module, $e, null);
        }
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
