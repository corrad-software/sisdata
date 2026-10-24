<?php

namespace App\Modules\Adm\Services\Siem;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Services\Logging\Redactor;
use Throwable;

/**
 * SRS-ADM-27: application security events (logins, failed logins, access
 * denied, account status, critical config decisions, maintenance mode…) are
 * shipped to the SIEM through the adapter chosen by parameter adm.siem_pemacu
 * (fail | syslog | tiada). MOD-AUD ships its own audit stream separately.
 */
class LogKeselamatan
{
    public function __construct(private Parameters $parameters) {}

    /**
     * @param  string  $outcome  success | failure
     * @param  array<string, mixed>  $meta
     */
    public function rekod(string $action, string $outcome, ?int $userId = null, string $message = '', array $meta = []): void
    {
        try {
            $this->penghantar()->hantar(Redactor::array([
                '@timestamp' => now()->toIso8601String(),
                'event' => ['kind' => 'event', 'category' => 'authentication', 'action' => $action, 'outcome' => $outcome, 'module' => 'sisdata'],
                'user' => ['id' => $userId],
                'source' => ['ip' => app()->bound('request') ? request()->ip() : null],
                'user_agent' => ['original' => app()->bound('request') ? (string) request()->userAgent() : null],
                'trace' => ['id' => AssignRequestId::current()],
                'message' => $message,
                'labels' => $meta,
            ]));
        } catch (Throwable) {
            // Shipping must never break the request.
        }
    }

    public function penghantar(): PenghantarSiem
    {
        $driver = (string) $this->parameters->get('adm.siem_pemacu', 'fail');

        if ($driver === 'syslog') {
            $target = (string) $this->parameters->get('adm.siem_hos', '');
            [$host, $port] = array_pad(explode(':', $target, 2), 2, '514');
            if ($host !== '') {
                return new PenghantarSyslog($host, (int) $port);
            }
        }

        if ($driver === 'tiada') {
            return new class implements PenghantarSiem
            {
                public function hantar(array $event): void {}
            };
        }

        return new PenghantarFail((string) config('adm.siem.fail', storage_path('logs/siem-keselamatan.log')));
    }
}
