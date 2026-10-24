<?php

namespace App\Modules\Dms;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Console\ArkibAutoCommand;
use App\Modules\Dms\Console\SemakKuotaCommand;
use App\Modules\Dms\Console\SemakLuputCommand;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dms\Http\Middleware\BolehDms;
use App\Modules\Dms\Services\Crypto\EnvelopeEncryptor;
use App\Modules\Dms\Services\DmsDocumentStore;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\DokumenSearchProvider;
use App\Modules\Dms\Services\Lifecycle\DisposalService;
use App\Modules\Dms\Services\RecordLinkRegistry;
use App\Modules\Dms\Services\Scanning\ClamdScanner;
use App\Modules\Dms\Services\Scanning\FakeVirusScanner;
use App\Modules\Dms\Services\Scanning\VirusScanner;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Dms\Services\Text\TextExtractor;
use App\Modules\Dms\Services\Watermark\Watermarker;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-DMS · Dokumen Digital (SRS-DMS-01..26). Owns the DocumentStore and
 * RecordLinks contracts; uses RBC, AUD, NTF, ADM and WFE only through theirs.
 */
class DmsServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'dms';

    public const PERMISSIONS = [
        'dms.dokumen.lihat' => 'Lihat, cari, pratonton dan muat turun dokumen (mengikut skop)',
        'dms.dokumen.cipta' => 'Muat naik dokumen dan versi baharu',
        'dms.dokumen.kemas_kini' => 'Pinda metadata dokumen',
        'dms.dokumen.padam' => 'Padam dokumen (pemadaman logik)',
        'dms.sulit.lihat' => 'Capai dokumen berklasifikasi Sulit',
        'dms.rahsia.lihat' => 'Capai dokumen berklasifikasi Rahsia',
        'dms.kebenaran.kemas_kini' => 'Urus kebenaran eksplisit dokumen Sulit/Rahsia',
        'dms.arkib.kemas_kini' => 'Arkibkan dokumen secara manual',
        'dms.pelupusan.cipta' => 'Mohon pelupusan dokumen diarkib',
        'dms.konfigurasi.lihat' => 'Lihat konfigurasi DMS',
        'dms.konfigurasi.kemas_kini' => 'Urus kategori, medan metadata dan polisi retensi',
        'dms.kuota.lihat' => 'Lihat kuota storan dan terima amaran kuota',
        'dms.kuarantin.lihat' => 'Lihat fail kuarantin dan terima amaran kuarantin',
        'dms.kuarantin.padam' => 'Musnahkan fail kuarantin',
    ];

    public function register(): void
    {

        $this->app->singleton(EnvelopeEncryptor::class, fn () => new EnvelopeEncryptor(
            config('dms.encryption_key'),
            (string) config('app.key'),
        ));
        $this->app->singleton(DocumentVault::class, fn ($app) => new DocumentVault($app->make(EnvelopeEncryptor::class), (string) config('dms.disk')));
        $this->app->singleton(VirusScanner::class, fn () => config('dms.scanner') === 'fake'
            ? new FakeVirusScanner
            : new ClamdScanner((string) config('dms.clamav.socket'), (int) config('dms.clamav.timeout')));
        $this->app->singleton(TextExtractor::class, fn () => new TextExtractor(
            (string) config('dms.binaries.pdftotext'),
            (string) config('dms.binaries.pdftoppm'),
            (string) config('dms.binaries.tesseract'),
            (int) config('dms.ocr_max_pages'),
            (int) config('dms.text_max_chars'),
        ));
        $this->app->singleton(Watermarker::class, fn () => new Watermarker((string) config('dms.binaries.pdftoppm'), (int) config('dms.preview_max_pages')));
        $this->app->singleton(RecordLinks::class, RecordLinkRegistry::class);
        $this->app->bind(DocumentStore::class, DmsDocumentStore::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app['router']->aliasMiddleware('dms.boleh', BolehDms::class);

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search) {
            $permissions->register('dms', 'Dokumen Digital', self::PERMISSIONS);
            DmsSettings::declareAll($parameters);
            $this->registerNotificationTypes($types);

            // SRS-DMS-23: the WFE admin configures this flow with two approval stages.
            $flows->declare(
                DisposalService::FLOW,
                'Pelupusan dokumen diarkib (dua peringkat)',
                'dms',
                DisposalService::SUBJECT,
                ['kategori', 'klasifikasi', 'modul_sumber', 'kod_daerah'],
            );

            $search->register(DokumenSearchProvider::class);
        });
        Event::listen(WorkflowCompleted::class, [DisposalService::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([SemakLuputCommand::class, ArkibAutoCommand::class, SemakKuotaCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('dms:semak-luput')->dailyAt('06:00')->withoutOverlapping();
            $schedule->command('dms:arkib-auto')->dailyAt('01:30')->withoutOverlapping();
            $schedule->command('dms:semak-kuota')->hourly()->withoutOverlapping();
        });
    }

    private function registerNotificationTypes(NotificationTypes $types): void
    {
        $types->register(
            'dms.kuarantin',
            'Fail dikuarantin (imbasan keselamatan gagal)',
            NotificationTypes::KRITIKAL,
            'Fail dikuarantin: {{nama_fail}}',
            'Fail "{{nama_fail}}" yang dimuat naik oleh {{pemuat_naik}} pada {{masa}} telah dikuarantin kerana imbasan keselamatan mengesan ancaman ({{ancaman}}). Fail tidak disimpan dalam repositori.',
            ['nama_fail', 'ancaman', 'pemuat_naik', 'masa'],
            mandatory: true,
        );
        $types->register(
            'dms.luput_hampir',
            'Dokumen hampir luput',
            NotificationTypes::TINGGI,
            'Dokumen {{no_rujukan}} akan luput pada {{tarikh_luput}}',
            'Dokumen {{tajuk}} ({{no_rujukan}}) akan luput dalam {{baki_hari}} hari, pada {{tarikh_luput}}. Sila ambil tindakan pembaharuan jika perlu.',
            ['no_rujukan', 'tajuk', 'tarikh_luput', 'baki_hari'],
        );
        $types->register(
            'dms.kuota_amaran',
            'Amaran kuota storan dokumen',
            NotificationTypes::TINGGI,
            'Storan dokumen telah mencapai {{peratus}}% kuota',
            'Penggunaan storan dokumen SISDATA ialah {{guna_gb}} GB daripada {{kuota_gb}} GB ({{peratus}}%). Sila tambah kuota atau laksanakan pengarkiban.',
            ['peratus', 'guna_gb', 'kuota_gb'],
        );
        $types->register(
            'dms.pelupusan_keputusan',
            'Keputusan permohonan pelupusan dokumen',
            NotificationTypes::BIASA,
            'Pelupusan dokumen {{no_rujukan}} {{keputusan}}',
            'Permohonan pelupusan dokumen {{no_rujukan}} telah {{keputusan}}.',
            ['no_rujukan', 'keputusan'],
        );
    }
}
