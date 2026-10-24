<?php

namespace App\Modules\Adm\Listeners;

use App\Modules\Adm\Services\Siem\LogKeselamatan;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Events\UserLoggedOut;
use Illuminate\Events\Dispatcher;

/** SRS-ADM-27: MOD-RBC's security events → SIEM stream. */
class HantarPeristiwaKeselamatan
{
    public function __construct(private LogKeselamatan $log) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserLoggedIn::class, fn (UserLoggedIn $e) => $this->log->rekod('log_masuk', 'success', $e->user->id, 'Log masuk', ['kaedah' => $e->method]));
        $events->listen(UserLoggedOut::class, fn (UserLoggedOut $e) => $this->log->rekod('log_keluar', 'success', $e->user->id, 'Log keluar'));
        $events->listen(LoginFailed::class, fn (LoginFailed $e) => $this->log->rekod('log_masuk_gagal', 'failure', $e->user?->id, 'Log masuk gagal', ['sebab' => $e->reason]));
        $events->listen(AccessDenied::class, fn (AccessDenied $e) => $this->log->rekod('capaian_ditolak', 'failure', $e->user?->id, 'Capaian ditolak', ['kebenaran' => $e->permission, 'sumber' => $e->resource]));
        $events->listen(AccountStatusChanged::class, fn (AccountStatusChanged $e) => $this->log->rekod('status_akaun', 'success', $e->user->id, 'Status akaun berubah', ['dari' => $e->from, 'kepada' => $e->to]));
    }
}
