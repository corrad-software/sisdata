<?php

namespace App\Modules\Dms\Services\Scanning;

final class ScanResult
{
    public const CLEAN = 'bersih';

    public const INFECTED = 'dijangkiti';

    public const ERROR = 'ralat';

    private function __construct(
        public readonly string $outcome,
        public readonly string $engine,
        public readonly ?string $signatureVersion = null,
        public readonly ?string $threat = null,
        public readonly ?string $error = null,
    ) {}

    public static function clean(string $engine, ?string $version = null): self
    {
        return new self(self::CLEAN, $engine, $version);
    }

    public static function infected(string $engine, string $threat, ?string $version = null): self
    {
        return new self(self::INFECTED, $engine, $version, $threat);
    }

    public static function error(string $engine, string $error): self
    {
        return new self(self::ERROR, $engine, null, null, $error);
    }

    public function isClean(): bool
    {
        return $this->outcome === self::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->outcome === self::INFECTED;
    }
}
