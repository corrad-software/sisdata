<?php

namespace App\Modules\Adm\Services\Kesihatan;

use App\Modules\Adm\Contracts\Parameters;
use Throwable;

/** Local storage usage vs threshold adm.ambang_storan_peratus (SRS-ADM-16/17). */
class SemakanStoran implements Semakan
{
    public function __construct(private Parameters $parameters, private ?string $path = null) {}

    public function kod(): string
    {
        return 'storan';
    }

    public function label(): string
    {
        return 'Ruang storan pelayan';
    }

    public function jalankan(): array
    {
        try {
            $path = $this->path ?? storage_path();
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if (! $total || $free === false) {
                return ['status' => self::AMARAN, 'mesej' => 'Unknown'];
            }

            $usedPct = round(($total - $free) / $total * 100, 1);
            $threshold = (int) $this->parameters->get('adm.ambang_storan_peratus', 85);

            return [
                'status' => $usedPct > $threshold ? self::AMARAN : self::OK,
                'mesej' => $usedPct > $threshold ? __('adm::adm.kesihatan.storage_over', ['peratus' => $usedPct, 'ambang' => $threshold]) : null,
                'butiran' => ['jumlah_bait' => (int) $total, 'bebas_bait' => (int) $free, 'guna_peratus' => $usedPct, 'ambang' => $threshold],
            ];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
