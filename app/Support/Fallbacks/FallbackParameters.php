<?php

namespace App\Support\Fallbacks;

use App\Modules\Adm\Contracts\Parameters;

/** Stand-in for Parameters until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackParameters implements Parameters
{
    /** @var array<string, mixed> */
    private array $defaults = [];

    public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void
    {
        $this->defaults[$key] = $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->defaults[$key] ?? $default;
    }
}
