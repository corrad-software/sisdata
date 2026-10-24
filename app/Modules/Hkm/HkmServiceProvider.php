<?php

namespace App\Modules\Hkm;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Hkm\Console\ImportStagingCommand;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Hkm\Profile\HakMilikSection;
use App\Modules\Hkm\Profile\RisikoHakMilikSection;
use App\Modules\Hkm\Reminders\DokumenLewatDipulangkan;
use App\Modules\Hkm\Reminders\HakMilikSementaraTamat;
use App\Modules\Hkm\Reminders\LotTanpaHakMilikDisahkan;
use App\Modules\Hkm\Reminders\TolPerluDiperbaharui;
use App\Modules\Hkm\Services\ChangeService;
use App\Modules\Hkm\Services\HakMilikSearchProvider;
use App\Modules\Hkm\Services\HkmTitleRegistry;
use App\Modules\Hkm\Services\ProposalService;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 * MOD-HKM · Hak Milik Tanah (SRS-HKM-01..22). Implements TitleRegistry (for
 * MOD-PER) and TitleChangeProposals (for integrations); uses RBC, AUD, ADM,
 * NTF, DMS, WFE and PRO only through their contracts.
 */
class HkmServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'hkm';

    public const PERMISSIONS = [
        'hkm.hak_milik.lihat' => 'Lihat rekod hak milik (Sulit) mengikut skop',
        'hkm.hak_milik.cipta' => 'Daftar hak milik baharu',
        'hkm.hak_milik.kemas_kini' => 'Mohon perubahan hak milik, status tanah, pemilik, sekatan dan waris; rekod urusan Pejabat Tanah',
        'hkm.hak_milik.eksport' => 'Eksport keputusan carian dan laporan hak milik',
        'hkm.pengesahan.lulus' => 'Sahkan rekod hak milik berbanding rekod Pejabat Tanah',
        'hkm.sulit.lihat' => 'Lihat nombor pengenalan penuh waris dan pemilik bersama',
        'hkm.peti_besi.lihat' => 'Lihat daftar peti besi dan sejarah pengeluaran dokumen asal',
        'hkm.peti_besi.cipta' => 'Daftar peti besi dan terima dokumen asal ke dalam simpanan',
        'hkm.peti_besi.kemas_kini' => 'Kemas kini peti besi, lokasi dan status dokumen asal',
        'hkm.peti_besi.eksport' => 'Cetak senarai inventori daftar peti besi',
        'hkm.pengeluaran.cipta' => 'Keluarkan dokumen asal daripada peti besi',
        'hkm.pengeluaran.kemas_kini' => 'Terima pemulangan dokumen asal',
        'hkm.pemantauan.lihat' => 'Terima amaran dan pantau dokumen asal lewat dipulangkan (ketua bahagian)',
        'hkm.permohonan.lihat' => 'Lihat permohonan hak milik kekal',
        'hkm.permohonan.cipta' => 'Daftar permohonan hak milik kekal',
        'hkm.permohonan.kemas_kini' => 'Kemas kini senarai semak, peringkat, bayaran dan penerimaan hak milik kekal',
        'hkm.cadangan.lihat' => 'Lihat cadangan perubahan daripada integrasi luaran',
        'hkm.cadangan.cipta' => 'Hantar cadangan perubahan (akaun integrasi)',
        'hkm.cadangan.kemas_kini' => 'Sahkan atau tolak cadangan perubahan daripada integrasi',
    ];

    public function register(): void
    {
        $this->app->bind(TitleRegistry::class, HkmTitleRegistry::class);
        $this->app->bind(TitleChangeProposals::class, ProposalService::class);
        $this->app->scoped(RefLookup::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SubjectLinks $links, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('hkm', 'Hak Milik Tanah', self::PERMISSIONS);

            $parameters->declare(StatusTanah::PARAMETER, 'json', StatusTanah::LALAI, 'Senarai status tanah (SRS-HKM-08): [{kod, nama, aktif}]');
            $parameters->declare('hkm.sasaran_hari_hak_milik_disahkan', 'int', 180, 'Tempoh sasaran (hari) lot mesti mempunyai hak milik disahkan (SRS-HKM-17)');
            $parameters->declare('hkm.hari_amaran_tamat', 'int', 180, 'Hak milik ditanda "hampir tamat tempoh" dalam tempoh (hari)');

            $this->registerNotificationTypes($types);

            // SRS-HKM-19: two-stage approval with MFA — stages configured in WFE (seeded by HkmAliranSeeder).
            $flows->declare(ChangeService::FLOW, 'Perubahan hak milik (dua peringkat, MFA)', 'hkm', ChangeService::SUBJECT, ['kod_daerah', 'kod_mukim', 'jenis_perubahan', 'jenis_hak_milik']);
            $links->register(ChangeService::SUBJECT, '/admin/hkm/perubahan/{id}');

            $search->register(HakMilikSearchProvider::class);

            $profile->register(HakMilikSection::class);
            $profile->register(RisikoHakMilikSection::class);
        });

        // SRS-HKM-07 / SRS-HKM-17 scheduled reminders (MOD-NTF runs them daily).
        if ($this->app->bound(Reminders::class)) {
            $reminders = $this->app->make(Reminders::class);
            foreach ([DokumenLewatDipulangkan::class, HakMilikSementaraTamat::class, TolPerluDiperbaharui::class, LotTanpaHakMilikDisahkan::class] as $source) {
                $reminders->register($source);
            }
        }

        // SRS-HKM-11 / SRS-DMS-16: documents attached to a title drill back to it.
        // Registered lazily so the registry isn't built (with its dependencies) during boot.
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('hak_milik', 'Hak Milik', '/admin/hkm/hak-milik/{id}', 'hkm.hak_milik.lihat'));

        Event::listen(WorkflowCompleted::class, [ChangeService::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([ImportStagingCommand::class]);
        }
    }

    private function registerNotificationTypes(NotificationTypes $types): void
    {
        $types->register(DokumenLewatDipulangkan::TYPE, 'Dokumen asal lewat dipulangkan', NotificationTypes::TINGGI,
            'Dokumen asal belum dipulangkan: {{kod_peti_besi}} / {{no_fail}}',
            'Dokumen asal daripada peti besi {{kod_peti_besi}} (fail {{no_fail}}) yang dikeluarkan kepada {{penerima}} dijangka dipulangkan pada {{tarikh_jangka_pulang}}. Lewat: {{hari_lewat}} hari. Sila ambil tindakan.',
            ['kod_peti_besi', 'no_fail', 'penerima', 'tarikh_jangka_pulang', 'hari_lewat']);
        $types->register(HakMilikSementaraTamat::TYPE, 'Hak milik sementara hampir tamat tempoh', NotificationTypes::TINGGI,
            '{{jenis}} tamat tempoh dalam {{baki_hari}} hari',
            'Satu {{jenis}} di daerah {{kod_daerah}} akan tamat tempoh pada {{tarikh_tamat}} ({{baki_hari}} hari lagi). Sila semak permohonan hak milik kekal.',
            ['jenis', 'kod_daerah', 'tarikh_tamat']);
        $types->register(TolPerluDiperbaharui::TYPE, 'TOL perlu diperbaharui', NotificationTypes::TINGGI,
            'TOL perlu diperbaharui dalam {{baki_hari}} hari',
            'Satu {{jenis}} di daerah {{kod_daerah}} tamat pada {{tarikh_tamat}} ({{baki_hari}} hari lagi). Sila mohon pembaharuan.',
            ['jenis', 'kod_daerah', 'tarikh_tamat']);
        $types->register(LotTanpaHakMilikDisahkan::TYPE, 'Lot tanpa hak milik disahkan', NotificationTypes::BIASA,
            'Lot {{no_lot}} belum mempunyai hak milik disahkan',
            'Lot {{no_lot}} (mukim {{kod_mukim}}) melebihi tempoh sasaran {{sasaran_hari}} hari tanpa hak milik yang disahkan.',
            ['no_lot', 'kod_mukim', 'sasaran_hari']);
        $types->register('hkm.perubahan_keputusan', 'Keputusan perubahan hak milik', NotificationTypes::BIASA,
            'Perubahan hak milik {{no_hak_milik}}: {{keputusan}}',
            'Permohonan "{{jenis_perubahan}}" bagi hak milik {{no_hak_milik}} telah {{keputusan}}.',
            ['no_hak_milik', 'jenis_perubahan', 'keputusan']);
    }
}
