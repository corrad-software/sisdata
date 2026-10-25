<?php

namespace App\Modules\Lap;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Lap\Console\AnalitikCommand;
use App\Modules\Lap\Console\InsightCommand;
use App\Modules\Lap\Console\JadualCommand;
use App\Modules\Lap\Gis\AnalitikLayer;
use App\Modules\Lap\Services\Baca;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * MOD-LAP · Laporan dan Analitik (SRS-LAP-01..23): central report catalogue,
 * standard / composite (board) reports, ad hoc builder over curated datasets,
 * AI-prompt reports and smart search (rule-based, local), PDF / Excel / CSV /
 * image export, background and scheduled generation with NTF distribution,
 * generation log + audit, rule-based analytics with recommendations,
 * executive insight, and Metabase OSS embedding.
 *
 * Reads other modules ONLY through the `laporan` schema views (row-level
 * security by session settings, see the migration) — never writes them and
 * never imports their classes. Uses RBC, AUD, ADM, NTF, GIS via contracts.
 * Configuration: config/lap.php (read replica, Metabase embedding, disk).
 */
class LapServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'lap';

    public const PERMISSIONS = [
        'lap.laporan.lihat' => 'Lihat katalog laporan dan jana laporan (tertakluk kebenaran set data modul sumber)',
        'lap.laporan.eksport' => 'Eksport laporan (PDF / Excel / CSV / imej) dan jana di latar belakang',
        'lap.adhoc.cipta' => 'Bina, simpan dan kongsi laporan ad hoc; guna AI prompt laporan',
        'lap.jadual.cipta' => 'Urus jadual penjanaan dan edaran laporan berkala',
        'lap.katalog.kemas_kini' => 'Urus katalog laporan: pemilik, definisi metrik, peranan dibenarkan, pengesahan CRP-2',
        'lap.log.lihat' => 'Lihat jejak penjanaan dan eksport laporan semua pengguna',
        'lap.analitik.lihat' => 'Lihat hasil analitik dan cadangan sistem',
        'lap.analitik.kemas_kini' => 'Jalankan semula analitik',
        'lap.analitik.lulus' => 'Terima / tolak cadangan sistem dengan justifikasi',
        'lap.insight.lihat' => 'Lihat dan jana executive insight summary',
        'lap.bi.lihat' => 'Lihat papan pemuka BI (Metabase) yang diluluskan',
        'lap.bi.kemas_kini' => 'Daftar papan pemuka BI (Metabase) untuk dibenamkan',
    ];

    public function boot(): void
    {
        parent::boot();
        Baca::configure();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types) {
            $permissions->register('lap', 'Laporan dan Analitik', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $types->register('lap.laporan_siap', 'Laporan latar belakang siap', NotificationTypes::BIASA,
                'Laporan "{{tajuk}}" {{status}}',
                "Laporan \"{{tajuk}}\" yang anda minta pada {{masa}}: {{status}} ({{bilangan_rekod}} rekod).\nMuat turun fail dalam SISDATA (Laporan → Log Penjanaan).",
                ['tajuk', 'status', 'bilangan_rekod', 'masa']);
            $types->register('lap.laporan_berjadual', 'Laporan berjadual', NotificationTypes::BIASA,
                'Laporan {{kekerapan}}: {{tajuk}} — {{tarikh}}',
                "Laporan berjadual \"{{tajuk}}\" ({{kekerapan}}) telah dijana pada {{tarikh}} dalam format {{format}} ({{bilangan_rekod}} rekod), mengikut skop capaian anda.\nLog masuk ke SISDATA untuk memuat turun.",
                ['tajuk', 'kekerapan', 'tarikh', 'format', 'bilangan_rekod'], true);
            $types->register('lap.insight_eksekutif', 'Executive insight summary', NotificationTypes::TINGGI,
                'Executive insight SISDATA — {{tempoh}}',
                "Ringkasan eksekutif {{tempoh}} telah dijana: {{bil_isu}} isu utama, {{bil_risiko}} risiko dan {{bil_tindakan}} tindakan segera.\nCadangan sistem — bukan keputusan rasmi MAIS. Butiran dalam SISDATA.",
                ['tempoh', 'bil_isu', 'bil_risiko', 'bil_tindakan']);
        });

        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(AnalitikLayer::class));

        if ($this->app->runningInConsole()) {
            $this->commands([JadualCommand::class, AnalitikCommand::class, InsightCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('lap:jadual')->everyFiveMinutes()->withoutOverlapping()->name('lap:jadual');
            $schedule->command('lap:analitik')->dailyAt('02:30')->withoutOverlapping()->name('lap:analitik');
            $schedule->command('lap:insight-eksekutif')->dailyAt('06:30')->withoutOverlapping()->name('lap:insight-eksekutif');
        });
    }

    private function declareParameters(Parameters $parameters): void
    {
        $parameters->declare('lap.cache_saat', 'int', 300, 'Tempoh cache hasil laporan (saat; 0 = tiada cache)');
        $parameters->declare('lap.had_baris', 'int', 5000, 'Had baris hasil laporan berkumpulan dipaparkan');
        $parameters->declare('lap.had_eksport', 'int', 50000, 'Had rekod bagi satu eksport laporan');
        $parameters->declare('lap.had_segerak', 'int', 5000, 'Eksport melebihi bilangan rekod ini dijana di latar belakang');
        $parameters->declare('lap.had_masa_saat', 'int', 30, 'Had masa satu pertanyaan pelaporan (saat) — melindungi sistem transaksi', true);
        $parameters->declare('lap.insight_kekerapan', 'string', 'bulanan', 'Kekerapan executive insight summary: mingguan / bulanan / suku_tahun');
        $parameters->declare('lap.ambang_baki_tunggakan', 'int', 10000, 'Baki tunggakan (RM) yang memberi skor penuh dalam analitik risiko tunggakan');
        $parameters->declare('lap.kadar_pulangan_sewa_peratus', 'int', 5, 'Kadar pulangan sewa tahunan (% nilai pasaran) bagi analitik potensi hasil');
        $parameters->declare('lap.ambang_hasil_tinggi', 'int', 120000, 'Anggaran hasil tahunan (RM) yang memberi skor penuh potensi hasil');
        $parameters->declare('lap.keluasan_min_pembangunan_m2', 'int', 4047, 'Keluasan minimum (m²) lot dinilai bagi potensi pembangunan');
    }
}
