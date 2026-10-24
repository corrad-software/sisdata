<?php

namespace App\Modules\Gis;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Gis\Console\GeoServerSetupCommand;
use App\Modules\Gis\Console\SemakKualitiCommand;
use App\Modules\Gis\Contracts\Geocoder;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Gis\Layers\AsetFizikalLayer;
use App\Modules\Gis\Layers\HakMilikLayer;
use App\Modules\Gis\Layers\KategoriGunaTanahLayer;
use App\Modules\Gis\Layers\LotBahagianLayer;
use App\Modules\Gis\Layers\PemerolehanLayer;
use App\Modules\Gis\Layers\RisikoLayer;
use App\Modules\Gis\Layers\SempadanDaerahLayer;
use App\Modules\Gis\Layers\SempadanMukimLayer;
use App\Modules\Gis\Layers\SempadanPbtLayer;
use App\Modules\Gis\Layers\SetDataLayer;
use App\Modules\Gis\Layers\StatusTanahLayer;
use App\Modules\Gis\Layers\TempatIbadatLayer;
use App\Modules\Gis\Layers\ZoningLayer;
use App\Modules\Gis\Services\Geocoding\CompositeGeocoder;
use App\Modules\Gis\Services\LayerRegistry;
use App\Modules\Gis\Services\LayerService;
use App\Modules\Gis\Services\Ogr;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * MOD-GIS · GIS Mapping dan Kepintaran Spatial (SRS-GIS-01..35). Owns the
 * MapLayers registry (other modules register their layers through it), the
 * map services (GeoJSON, vector tiles, OGC proxy), spatial analysis, spatial
 * import/export and spatial QA. Reads lot geometry from MOD-PRO's tables
 * (read-only) and uses RBC, AUD, ADM, DMS only through their contracts.
 */
class GisServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'gis';

    public const PERMISSIONS = [
        'gis.peta.lihat' => 'Lihat peta GIS dan lapisan yang dibenarkan',
        'gis.peta.eksport' => 'Cetak / eksport peta dan data lapisan (PDF, imej, GeoJSON, KML, SHP, GPKG)',
        'gis.analisis.cipta' => 'Jalankan analisis spatial dan simpan senario',
        'gis.analisis.eksport' => 'Eksport senarai lot hasil analisis (Excel / CSV)',
        'gis.import.lihat' => 'Lihat log import data spatial dan laporan ralat',
        'gis.import.cipta' => 'Import fail spatial (SHP, GeoJSON, KML/KMZ, DXF, GPKG)',
        'gis.kualiti.lihat' => 'Lihat laporan kualiti data spatial',
        'gis.kualiti.kemas_kini' => 'Jalankan semakan kualiti spatial dan kemas kini status pembetulan',
        'gis.lapisan.kemas_kini' => 'Urus konfigurasi lapisan peta dan metadata',
        'gis.aset.cipta' => 'Tanda lokasi aset fizikal di peta',
        'gis.aset.kemas_kini' => 'Kemas kini / padam titik aset fizikal',
        'gis.ogc.lihat' => 'Capai perkhidmatan peta OGC (WMS/WFS) melalui proksi',
        'gis.sulit.lihat' => 'Capai lapisan peta berklasifikasi Sulit',
        'gis.rahsia.lihat' => 'Capai lapisan peta berklasifikasi Rahsia',
    ];

    /** @var list<class-string> */
    public const LAYERS = [
        LotBahagianLayer::class, KategoriGunaTanahLayer::class, ZoningLayer::class, HakMilikLayer::class, StatusTanahLayer::class,
        RisikoLayer::class, PemerolehanLayer::class, AsetFizikalLayer::class, SetDataLayer::class, TempatIbadatLayer::class,
        SempadanDaerahLayer::class, SempadanMukimLayer::class, SempadanPbtLayer::class,
    ];

    public function register(): void
    {
        // Always resolve through the contract, so every module's
        // callAfterResolving(MapLayers::class, …) registration runs first.
        $this->app->singleton(MapLayers::class, fn ($app) => new LayerRegistry($app));
        $this->app->singleton(LayerRegistry::class, fn ($app) => $app->make(MapLayers::class));
        $this->app->scoped(LayerService::class);
        $this->app->bind(Geocoder::class, CompositeGeocoder::class);
        $this->app->bind(Ogr::class, fn () => new Ogr((string) config('gis.ogr2ogr'), (string) config('gis.ogrinfo')));
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters) {
            $permissions->register('gis', 'GIS Mapping dan Kepintaran Spatial', self::PERMISSIONS);
            $this->declareParameters($parameters);
        });

        $this->callAfterResolving(MapLayers::class, function (MapLayers $layers) {
            foreach (self::LAYERS as $layer) {
                $layers->register($layer);
            }
        });

        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('gis_log_import', 'Import Data Spatial', '/admin/gis/import/{id}', 'gis.import.lihat'));

        RateLimiter::for('gis-tiles', fn (Request $r) => Limit::perMinute(3000)->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())));
        RateLimiter::for('gis-ogc', fn (Request $r) => Limit::perMinute(600)->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())));
        RateLimiter::for('gis-import', fn (Request $r) => Limit::perMinute(10)->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([SemakKualitiCommand::class, GeoServerSetupCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('gis:semak-kualiti')->weeklyOn(1, '05:30')->withoutOverlapping();
        });
    }

    private function declareParameters(Parameters $parameters): void
    {
        $parameters->declare('gis.had_ciri', 'int', 5000, 'Bilangan ciri maksimum bagi satu permintaan GeoJSON peta');
        $parameters->declare('gis.tile_cache_saat', 'int', 300, 'Tempoh cache jubin vektor (saat) bagi setiap lapisan dan skop pengguna');
        $parameters->declare('gis.had_hasil_analisis', 'int', 2000, 'Bilangan baris maksimum hasil satu analisis spatial');
        $parameters->declare('gis.toleransi_ketepatan_m', 'int', 50, 'Toleransi ketepatan koordinat (m) bagi semakan luar sempadan Selangor / mukim (SRS-GIS-27)');
        $parameters->declare('gis.ambang_pertindihan_peratus', 'int', 1, 'Ambang pertindihan antara lot (% poligon lebih kecil) untuk laporan kualiti spatial');
        $parameters->declare('gis.ambang_jurang_m2', 'int', 500, 'Keluasan maksimum (m²) ruang kosong di antara lot yang dilapor sebagai jurang');
        $parameters->declare('gis.import_saiz_maks_mb', 'int', 50, 'Saiz maksimum fail import spatial (MB)');
        $parameters->declare('gis.import_ciri_maks', 'int', 20000, 'Bilangan ciri maksimum bagi satu fail import spatial');
        $parameters->declare('gis.import_srid_lalai', 'int', 3380, 'SRID diandaikan apabila fail tiada sistem koordinat (cth. DXF) — 3380 GDM2000 / Selangor Cassini');
        $parameters->declare('gis.eksport_ciri_maks', 'int', 50000, 'Bilangan ciri maksimum bagi satu eksport lapisan');
        $parameters->declare('gis.geocoder', 'string', 'tempatan', 'Penyedia carian lokasi: tempatan | nominatim');
        $parameters->declare('gis.nominatim_url', 'string', '', 'URL perkhidmatan geocoder serasi Nominatim (kosong = tidak digunakan)');
        $parameters->declare('gis.peta_asas_satelit_url', 'string', '', 'URL templat jubin imej satelit berlesen ({z}/{x}/{y}); kosong = tidak tersedia');
        $parameters->declare('gis.peta_asas_satelit_atribusi', 'string', '', 'Atribusi / notis lesen peta asas satelit');
    }
}
