<?php

namespace App\Modules\Ntf\Services;

/**
 * Fills {{medan}} placeholders in Malay templates (SRS-NTF-05). Output is
 * plain text; channels escape it for their medium.
 */
class TemplateRenderer
{
    /** Fields every template may use without the sender supplying them. */
    public const SYSTEM_FIELDS = ['tarikh'];

    private const PATTERN = '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/';

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function render(string $template, array $data): string
    {
        $data += ['tarikh' => now()->format('d/m/Y')];

        return (string) preg_replace_callback(self::PATTERN, function (array $m) use ($data) {
            $value = $data[$m[1]] ?? null;

            if (is_bool($value)) {
                return $value ? 'Ya' : 'Tidak';
            }

            return $value === null || $value === '' ? '—' : (string) $value;
        }, $template);
    }

    /**
     * @return list<string>
     */
    public function placeholders(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $m);

        return array_values(array_unique($m[1]));
    }
}
