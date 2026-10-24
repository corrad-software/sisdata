<?php

namespace App\Modules\Aud\Services;

/**
 * SRS-AUD-25: passwords, tokens, API/encryption keys and identity-document
 * numbers never reach the audit store in plain text. A matching field is still
 * recorded as changed, with its values replaced by the mask.
 */
class SensitiveFieldMasker
{
    /**
     * @param  list<string>  $extra  field names declared sensitive by the model
     */
    public function isSensitive(string $field, array $extra = []): bool
    {
        if (in_array($field, $extra, true)) {
            return true;
        }

        foreach ((array) config('aud.corak_medan_sensitif', []) as $pattern) {
            if (preg_match($pattern, $field)) {
                return true;
            }
        }

        return false;
    }

    public function mask(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) config('aud.topeng', '[DITAPIS]');
    }

    /**
     * Recursively masks sensitive keys inside free-form metadata.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = $this->mask(is_array($value) ? json_encode($value) : $value);
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            }
        }

        return $data;
    }
}
