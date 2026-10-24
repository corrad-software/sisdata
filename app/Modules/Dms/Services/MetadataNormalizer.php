<?php

namespace App\Modules\Dms\Services;

use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Models\DokumenMedanMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-DMS-10/11: splits caller metadata into the structured columns and the
 * admin-defined extra fields of the category, validating the latter against
 * their definitions (required / type / list values). Unknown keys are dropped.
 */
class MetadataNormalizer
{
    /** Structured SRS-DMS-10 fields stored as columns. */
    public const CORE = ['tajuk', 'tarikh_dokumen', 'tarikh_luput', 'tahun', 'kod_daerah', 'kod_mukim', 'pemilik_proses_id', 'kata_kunci', 'modul_sumber', 'pautan_induk'];

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $existingExtra  current extra values (on update, partial input keeps them)
     * @return array{core: array<string, mixed>, extra: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function normalize(array $input, DokumenKategori $kategori, array $existingExtra = [], bool $partial = false): array
    {
        $core = [];
        foreach (self::CORE as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $core[$key] = $this->core($key, $input[$key]);
        }

        if (isset($core['tarikh_dokumen']) && ! isset($core['tahun']) && ! $partial) {
            $core['tahun'] = (int) substr((string) $core['tarikh_dokumen'], 0, 4);
        }

        $extraInput = is_array($input['metadata'] ?? null) ? $input['metadata'] : $input;
        $extra = $existingExtra;
        $errors = [];

        foreach (DokumenMedanMetadata::forKategori($kategori->id) as $field) {
            $present = array_key_exists($field->kod, $extraInput);
            $value = $present ? $extraInput[$field->kod] : ($extra[$field->kod] ?? null);

            if ($value === null || $value === '') {
                if ($field->wajib && (! $partial || $present)) {
                    $errors['metadata.'.$field->kod][] = __('dms::mesej.metadata.wajib', ['medan' => $field->label]);
                }
                if ($present) {
                    unset($extra[$field->kod]);
                }

                continue;
            }

            $cast = $this->cast($field, $value);
            if ($cast === null) {
                $errors['metadata.'.$field->kod][] = __('dms::mesej.metadata.'.$field->jenis, ['medan' => $field->label]);

                continue;
            }
            $extra[$field->kod] = $cast;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['core' => $core, 'extra' => $extra];
    }

    private function core(string $key, mixed $value): mixed
    {
        return match ($key) {
            'tarikh_dokumen', 'tarikh_luput' => $this->date($key, $value),
            'tahun' => $value === null || $value === '' ? null : (int) $value,
            'pemilik_proses_id' => $value === null || $value === '' ? null : (int) $value,
            'kata_kunci' => $this->keywords($value),
            'tajuk' => $value === null ? null : mb_substr(trim((string) $value), 0, 255),
            default => $value === null || $value === '' ? null : mb_substr(trim((string) $value), 0, 255),
        };
    }

    /** @return list<string> */
    private function keywords(mixed $value): array
    {
        $list = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            fn ($k) => mb_substr(trim((string) $k), 0, 60),
            $list ?: [],
        ), fn ($k) => $k !== '')));
    }

    private function date(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([$key => [__('validation.date', ['attribute' => $key])]]);
        }
    }

    private function cast(DokumenMedanMetadata $field, mixed $value): mixed
    {
        return match ($field->jenis) {
            'nombor' => is_numeric($value) ? $value + 0 : null,
            'tarikh' => $this->tryDate($value),
            'senarai' => in_array((string) $value, array_map('strval', $field->pilihan ?? []), true) ? (string) $value : null,
            'ya_tidak' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default => mb_substr((string) $value, 0, 2000),
        };
    }

    private function tryDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
