<?php

namespace App\Modules\Ptn\Contracts;

interface RiskSignals
{
    /** @param class-string<RiskSignal> $signal a later registration for the same category replaces the built-in one */
    public function register(string $signal): void;
}
