<?php

namespace App\Modules\Pws;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Per\Contracts\WakafPortal;
use App\Modules\Pws\Console\CiptaKlienCommand;
use App\Modules\Pws\Console\CubaSemulaCommand;
use App\Modules\Pws\Console\SegerakStatusCommand;
use App\Modules\Pws\Http\Middleware\PortalCors;
use App\Modules\Pws\Integrasi\PwsWakafPortal;
use App\Modules\Pws\Services\KandunganService;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;

/**
 * MOD-PWS · Portal Hartanah Wakaf Selangor (SRS-PWS-01..28) — SISDATA side.
 *
 * The portal is a separate WordPress system (repo folder pws/). This module
 * is its only door into SISDATA: the versioned public API /api/v1/pws/awam/*
 * (client credentials, scopes, IP allowlist, rate limits, idempotency,
 * integration log, Awam data only) plus the officers' governance screens.
 *
 * Uses: PJK (public catalogue/distribution, portal application & payment
 * endpoints via the integration account, Contracts\PaymentGateway), PER
 * (Contracts\WakafPortal — bound here, replacing PER's mock), ADU (public
 * complaint API, Contracts\CaptchaVerifier), TKT (Contracts\TicketIntake),
 * KBA (Contracts\KnowledgeBase with user null, Contracts\LlmGateway — local
 * model only), WFE (publication approval), DMS (supporting documents), RBC,
 * AUD, ADM (parameters, integration log), NTF.
 */
class PwsServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'pws';

    public const PERMISSIONS = [
        'pws.klien.lihat' => 'Lihat klien API Portal Hartanah Wakaf Selangor',
        'pws.klien.kemas_kini' => 'Cipta, ubah, putar dan batal kunci API portal (MFA)',
        'pws.kandungan.lihat' => 'Lihat kandungan portal (termasuk draf)',
        'pws.kandungan.cipta' => 'Tulis draf kandungan portal (termasuk draf berbantu AI) dan hantar untuk semakan',
        'pws.kandungan.kemas_kini' => 'Tarik balik kandungan portal yang telah diterbitkan',
        'pws.statistik.lihat' => 'Lihat statistik awam portal',
        'pws.statistik.cipta' => 'Sediakan statistik awam dan hantar untuk kelulusan terbitan',
        'pws.pemantauan.lihat' => 'Pantau permohonan portal, log integrasi, bayaran dan analitik portal',
        'pws.pemantauan.kemas_kini' => 'Cuba semula penghantaran permohonan portal yang gagal',
    ];

    public const NTF_GAGAL = 'pws.penghantaran_gagal';

    public function register(): void
    {
        // Real submissions from the public API reach MOD-PER: decorate PER's
        // adapter (its mock stays usable for `per:portal-wakaf --contoh`).
        if ($this->app->bound(WakafPortal::class)) {
            $this->app->extend(WakafPortal::class, fn (WakafPortal $inner) => $inner instanceof PwsWakafPortal ? $inner : new PwsWakafPortal($inner));
        } else {
            $this->app->bind(WakafPortal::class, fn () => new PwsWakafPortal);
        }
    }

    public function boot(): void
    {
        parent::boot();

        // Outermost, so it has the last word on CORS for /api/v1/pws/awam/* only.
        $this->app->make(HttpKernel::class)->prependMiddleware(PortalCors::class);

        $this->app->call(function (PermissionRegistry $permissions, NotificationTypes $types, Parameters $parameters, WorkflowFlows $flows) {
            $permissions->register('pws', 'Portal Hartanah Wakaf Selangor', self::PERMISSIONS);
            $types->register(self::NTF_GAGAL, 'Penghantaran permohonan portal gagal', NotificationTypes::TINGGI,
                'Permohonan Portal Hartanah Wakaf Selangor gagal dihantar: {{no_rujukan}}',
                'Permohonan {{no_rujukan}} ({{jenis}}) belum diterima oleh SISDATA selepas {{bilangan_cuba}} cubaan. Sila semak log integrasi portal.',
                ['no_rujukan', 'jenis', 'bilangan_cuba']);
            $parameters->declare('pws.had_hantar_ip_sejam', 'int', 10, 'Had penghantaran borang portal per IP pengguna awam sejam');
            $parameters->declare('pws.had_semak_ip_sejam', 'int', 60, 'Had semakan status portal per IP pengguna awam sejam');
            $parameters->declare('pws.mfa_kunci_api', 'bool', true, 'Wajibkan MFA untuk pengurusan kunci API portal', true);
            $flows->declare(KandunganService::ALIRAN_KANDUNGAN, 'Kelulusan terbitan kandungan Portal Hartanah Wakaf Selangor', 'pws', KandunganService::SUBJEK_KANDUNGAN, ['jenis', 'dijana_ai']);
            $flows->declare(KandunganService::ALIRAN_STATISTIK, 'Kelulusan terbitan statistik awam Portal Hartanah Wakaf Selangor', 'pws', KandunganService::SUBJEK_STATISTIK, ['tempoh']);
        });

        $this->callAfterResolving(SubjectLinks::class, function (SubjectLinks $links) {
            $links->register(KandunganService::SUBJEK_KANDUNGAN, '/admin/pws/kandungan/{id}');
            $links->register(KandunganService::SUBJEK_STATISTIK, '/admin/pws/statistik');
        });

        Event::listen(WorkflowCompleted::class, [KandunganService::class, 'onWorkflowCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([CubaSemulaCommand::class, SegerakStatusCommand::class, CiptaKlienCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pws:cuba-semula')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('pws:segerak-status')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
