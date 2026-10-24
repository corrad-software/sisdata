<?php

namespace App\Modules\Pro\Contracts;

interface ProfileSections
{
    /** @param class-string<ProfileSection> $section */
    public function register(string $section): void;
}
