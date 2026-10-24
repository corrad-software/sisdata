<?php

namespace App\Support\Fallbacks;

use App\Modules\Pro\Contracts\ProfileSections;

/** Stand-in for ProfileSections until MOD-PRO binds the real one. */
class FallbackProfileSections implements ProfileSections
{
    public function register(string $section): void {}
}
