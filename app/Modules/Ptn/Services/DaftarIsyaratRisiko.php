<?php

namespace App\Modules\Ptn\Services;

use App\Modules\Ptn\Contracts\RiskSignal;
use App\Modules\Ptn\Contracts\RiskSignals;
use App\Modules\Ptn\Risiko\IsyaratDokumen;
use App\Modules\Ptn\Risiko\IsyaratPencerobohan;
use App\Modules\Ptn\Risiko\IsyaratPertikaian;
use App\Modules\Ptn\Risiko\IsyaratPotensiHasil;
use App\Modules\Ptn\Risiko\IsyaratTamatKontrak;
use App\Modules\Ptn\Risiko\IsyaratTunggakanCukai;
use Illuminate\Contracts\Container\Container;

/** RiskSignals registry (singleton): built-in signal per category, replaced by a module-registered one. */
class DaftarIsyaratRisiko implements RiskSignals
{
    /** @var array<string, class-string<RiskSignal>> */
    private array $external = [];

    public const ASAS = [
        'pencerobohan' => IsyaratPencerobohan::class,
        'tunggakan_cukai' => IsyaratTunggakanCukai::class,
        'pertikaian' => IsyaratPertikaian::class,
        'dokumen' => IsyaratDokumen::class,
        'tamat_kontrak' => IsyaratTamatKontrak::class,
        'potensi_hasil' => IsyaratPotensiHasil::class,
    ];

    public function __construct(private Container $app) {}

    public function register(string $signal): void
    {
        /** @var RiskSignal $instance */
        $instance = $this->app->make($signal);
        $this->external[$instance->category()] = $signal;
    }

    /** @return array<string, RiskSignal> category => signal */
    public function all(): array
    {
        $out = [];
        foreach (self::ASAS as $category => $class) {
            $out[$category] = $this->app->make($this->external[$category] ?? $class);
        }

        return $out;
    }

    /** @return array<string, string> category => "terbina" | class of the registering module */
    public function sumber(): array
    {
        $out = [];
        foreach (array_keys(self::ASAS) as $category) {
            $out[$category] = isset($this->external[$category]) ? $this->external[$category] : 'terbina';
        }

        return $out;
    }
}
