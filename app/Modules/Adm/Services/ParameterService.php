<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Models\Parameter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * SRS-ADM-01: system parameters. Modules declare key, type, default, label
 * and whether a change is critical (SRS-ADM-18); admins override values from
 * the UI. Overrides live in adm_parameter and are read through the cache
 * (Redis in dev/prod) as one plain array, so a change takes effect on the next
 * read everywhere — no deploy, no restart (acceptance criterion 1).
 */
class ParameterService implements Parameters
{
    public const CACHE_KEY = 'adm:parameter:nilai:v1';

    public const TYPES = ['int', 'bool', 'string', 'json'];

    /** @var array<string, array{kunci: string, modul: string, jenis: string, lalai: mixed, label: string, kritikal: bool}> */
    private array $declared = [];

    private bool $tableReady = false;

    public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown parameter type [{$type}] for [{$key}].");
        }

        $this->declared[$key] = [
            'kunci' => $key,
            'modul' => str_contains($key, '.') ? strstr($key, '.', true) : 'umum',
            'jenis' => $type,
            'lalai' => $default,
            'label' => $label,
            'kritikal' => $critical,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $declaration = $this->declared[$key] ?? null;
        $overrides = $this->overrides();

        if (array_key_exists($key, $overrides) && $overrides[$key] !== null) {
            return $this->cast(json_decode($overrides[$key], true), $declaration['jenis'] ?? null);
        }

        return $declaration !== null ? $declaration['lalai'] : $default;
    }

    /**
     * @return array<string, array{kunci: string, modul: string, jenis: string, lalai: mixed, label: string, kritikal: bool}>
     */
    public function declarations(): array
    {
        ksort($this->declared);

        return $this->declared;
    }

    /**
     * @return array{kunci: string, modul: string, jenis: string, lalai: mixed, label: string, kritikal: bool}|null
     */
    public function definition(string $key): ?array
    {
        return $this->declared[$key] ?? null;
    }

    public function isOverridden(string $key): bool
    {
        return array_key_exists($key, $this->overrides());
    }

    /**
     * Validate and normalise a value for a declared parameter.
     *
     * @throws ValidationException
     */
    public function normalise(string $key, mixed $value): mixed
    {
        $definition = $this->definition($key) ?? throw ValidationException::withMessages(['kunci' => __('adm::adm.parameter.unknown')]);

        $rule = match ($definition['jenis']) {
            'int' => ['required', 'integer'],
            'bool' => ['required', 'boolean'],
            'string' => ['present', 'nullable', 'string', 'max:2000'],
            default => ['present', 'array'],
        };

        if ($definition['jenis'] === 'json' && is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        Validator::make(['nilai' => $value], ['nilai' => $rule], [], ['nilai' => __('adm::adm.parameter.value')])->validate();

        return $this->cast($value, $definition['jenis']);
    }

    /** Apply a (validated) value. Critical parameters only reach here via an approved request. */
    public function store(string $key, mixed $value, ?string $justification, ?User $by): void
    {
        $definition = $this->definition($key);

        $row = Parameter::query()->firstOrNew(['kunci' => $key]);
        $row->fill([
            'modul' => $definition['modul'] ?? (str_contains($key, '.') ? strstr($key, '.', true) : 'umum'),
            'nilai' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'justifikasi' => $justification,
            'dikemas_kini_oleh' => $by?->id,
        ])->save();

        $this->flush();
    }

    /** Drop an override so the declared default applies again. */
    public function reset(string $key, ?string $justification, ?User $by): void
    {
        $row = Parameter::query()->where('kunci', $key)->first();
        if ($row) {
            // Record the justification on the row first so the audited delete carries it.
            $row->update(['justifikasi' => $justification, 'dikemas_kini_oleh' => $by?->id]);
            $row->delete();
        }

        $this->flush();
    }

    public function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Cache down: readers fall back to the database anyway.
        }
    }

    /**
     * @return array<string, string|null> kunci => JSON-encoded value
     */
    private function overrides(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => $this->load());
        } catch (Throwable) {
            try {
                return $this->load();
            } catch (Throwable) {
                return [];
            }
        }
    }

    /** @return array<string, string|null> */
    private function load(): array
    {
        return Parameter::query()->pluck('nilai', 'kunci')->all();
    }

    private function tableReady(): bool
    {
        if ($this->tableReady) {
            return true;
        }

        try {
            return $this->tableReady = Schema::hasTable('adm_parameter');
        } catch (Throwable) {
            return false;
        }
    }

    private function cast(mixed $value, ?string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'string' => $value === null ? null : (string) $value,
            'json' => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
