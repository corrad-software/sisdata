<?php

namespace App\Modules\Per\Services;

use App\Modules\Per\Support\ChannelCatalog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates the channel-specific fields of a case (SRS-PER-02..15) against
 * the schema in ChannelCatalog, and splits them into public fields
 * (butiran_saluran, JSONB) and personal fields (butiran_peribadi, encrypted).
 *
 * Saving only checks types/options (cases are filled in progressively);
 * "required by stage" is enforced when a case leaves that stage (missingFor).
 */
class ChannelFields
{
    /**
     * @param  array<string, mixed>  $input  field => value (unknown keys rejected)
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [public, personal]
     *
     * @throws ValidationException
     */
    public function validate(string $channel, array $input): array
    {
        $schema = ChannelCatalog::fields($channel);
        $unknown = array_diff(array_keys($input), array_keys($schema));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'butiran' => __('per::per.medan_tidak_dikenali', ['medan' => implode(', ', $unknown)]),
            ]);
        }

        $rules = [];
        $attributes = [];
        foreach ($schema as $key => $f) {
            $rules[$key] = match ($f['jenis']) {
                'tarikh' => ['nullable', 'date_format:Y-m-d'],
                'nombor', 'wang' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
                'pilihan' => ['nullable', 'string', Rule::in(array_keys($f['pilihan']))],
                'teks_panjang' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:255'],
            };
            $attributes[$key] = $f['label'];
        }

        $data = Validator::make($input, $rules, [], $attributes)->validate();

        $public = [];
        $personal = [];
        foreach ($data as $key => $value) {
            if ($value === '') {
                $value = null;
            }
            if (in_array($schema[$key]['jenis'], ['nombor', 'wang'], true) && $value !== null) {
                $value = (string) round((float) $value, 2);
            }
            if (! empty($schema[$key]['peribadi'])) {
                $personal[$key] = $value;
            } else {
                $public[$key] = $value;
            }
        }

        return [$public, $personal];
    }

    /**
     * Labels of fields that must be filled (or hold their required value)
     * before the case may leave $stageCode.
     *
     * @param  array<string, mixed>  $values  merged public + personal values
     * @return list<string>
     */
    public function missingFor(string $channel, string $stageCode, array $values): array
    {
        $missing = [];
        foreach (ChannelCatalog::fields($channel) as $key => $f) {
            if (($f['peringkat'] ?? null) !== $stageCode) {
                continue;
            }
            $value = $values[$key] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $f['label'];
            } elseif (isset($f['mesti']) && $value !== $f['mesti']) {
                $missing[] = __('per::per.medan_mesti', ['medan' => $f['label'], 'nilai' => $f['pilihan'][$f['mesti']] ?? $f['mesti']]);
            }
        }

        return $missing;
    }

    /**
     * The schema for the frontend form generator.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(string $channel): array
    {
        $out = [];
        foreach (ChannelCatalog::fields($channel) as $key => $f) {
            $out[] = [
                'kod' => $key,
                'label' => $f['label'],
                'jenis' => $f['jenis'],
                'pilihan' => isset($f['pilihan']) ? array_map(fn ($v, $l) => ['nilai' => $v, 'label' => $l], array_keys($f['pilihan']), $f['pilihan']) : null,
                'peringkat' => $f['peringkat'] ?? null,
                'peribadi' => ! empty($f['peribadi']),
                'mesti' => $f['mesti'] ?? null,
            ];
        }

        return $out;
    }
}
