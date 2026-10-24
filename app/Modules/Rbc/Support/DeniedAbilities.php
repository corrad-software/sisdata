<?php

namespace App\Modules\Rbc\Support;

/** Request-scoped note of the last permission the Gate refused. */
class DeniedAbilities
{
    private ?string $last = null;

    public function record(string $ability): void
    {
        $this->last = $ability;
    }

    public function last(): ?string
    {
        return $this->last;
    }

    public function reset(): void
    {
        $this->last = null;
    }
}
