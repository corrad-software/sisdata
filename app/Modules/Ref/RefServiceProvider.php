<?php

namespace App\Modules\Ref;

use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Ref\Console\FetchOsmCommand;
use App\Modules\Ref\Console\SampleLotsCommand;
use App\Modules\Ref\Console\SeedRefCommand;
use App\Support\Modules\ModuleServiceProvider;

/**
 * Data Rujukan (reference / master data, SRS-DAT-05) and the data-track
 * staging area (SRS-DAT-08, SRS Bab 15).
 *
 *  - ref_* code tables (negeri, daerah, mukim, PBT, kategori guna tanah …),
 *    versioned with sah_dari / sah_hingga / versi. MOD-ADM builds the MDM
 *    admin UI on top of these tables.
 *  - ref_sempadan (boundaries) and ref_tempat_ibadat (OSM POIs).
 *  - staging_* tables + migrasi_larian holding SYNTHETIC sample lots, titles
 *    and owners. MOD-PRO imports from staging into lot_tanah.
 */
class RefServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'ref';

    public const PERMISSION_VIEW = 'ref.rujukan.lihat';

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register('ref', 'Data Rujukan', [
            self::PERMISSION_VIEW => 'Lihat data rujukan (daerah, mukim, PBT, kod) dan sempadan',
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchOsmCommand::class,
                SeedRefCommand::class,
                SampleLotsCommand::class,
            ]);
        }
    }
}
