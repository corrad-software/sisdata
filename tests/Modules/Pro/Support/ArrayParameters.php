<?php

namespace Tests\Modules\Pro\Support;

use App\Modules\Adm\Contracts\Parameters;

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
}
