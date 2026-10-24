<?php

namespace App\Modules\Pro\Services;

use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Contracts\ProfileSections;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/** Singleton registry of ProfileSection classes contributed by modules (SRS-PRO-26). */
class ProfileSectionRegistry implements ProfileSections
{
    /** @var array<class-string<ProfileSection>, true> */
    private array $classes = [];

    public function __construct(private Container $container) {}

    public function register(string $section): void
    {
        if (! is_subclass_of($section, ProfileSection::class)) {
            throw new InvalidArgumentException($section.' must implement '.ProfileSection::class);
        }
        $this->classes[$section] = true;
    }

    /** @return list<ProfileSection> */
    public function all(): array
    {
        return array_map(fn (string $class) => $this->container->make($class), array_keys($this->classes));
    }

    /** @return list<class-string<ProfileSection>> */
    public function classes(): array
    {
        return array_keys($this->classes);
    }
}
