<?php

namespace Tests\Modules\Dms\Support;

use App\Modules\Adm\Contracts\Parameters;

/** Stand-in for MOD-ADM parameters that a test (the "administrator") can change. */
class ArrayParameters implements Parameters
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void
    {
        $this->values[$key] ??= $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}
