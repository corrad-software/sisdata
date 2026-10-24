<?php

namespace App\Modules\Adm\Services\Kesihatan;

/**
 * One health probe (SRS-ADM-16). Must finish fast (short timeouts) and never
 * throw — a failing dependency is a "gagal" result, not an exception.
 */
interface Semakan
{
    public const OK = 'ok';

    public const AMARAN = 'amaran';

    public const GAGAL = 'gagal';

    public function kod(): string;

    public function label(): string;

    /**
     * @return array{status: string, mesej: ?string, butiran?: array<string, mixed>}
     */
    public function jalankan(): array;
}
