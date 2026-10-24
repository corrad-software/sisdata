<?php

namespace App\Modules\Ntf\Listeners;

use App\Modules\Aud\Events\AuditIntegrityBroken;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\SuspiciousActivityDetected;

/**
 * Event-based triggers owned by MOD-NTF itself (SRS-NTF-06). Other modules
 * follow the same pattern: listen to their own event and call Notifier.
 */
class SystemEventNotifier
{
    /** MOD-RBC alert types (SuspiciousActivityDetected::$type) in Malay. */
    private const JENIS_AMARAN = [
        'gagal_berulang' => 'Cubaan log masuk gagal berulang',
        'peranti_baharu' => 'Log masuk dari peranti baharu',
        'lokasi_baharu' => 'Log masuk dari lokasi baharu',
        'capaian_tanpa_kebenaran' => 'Percubaan capaian tanpa kebenaran',
    ];

    public function __construct(private Notifier $notifier) {}

    public function auditIntegrityBroken(AuditIntegrityBroken $event): void
    {
        // Recipients come from the type's rules (admins + auditors, editable).
        $this->notifier->send('ntf.integriti_audit', [], [
            'jujukan' => $event->firstBadSequence,
        ], '/admin/aud', 'integriti:'.$event->firstBadSequence);
    }

    public function accountStatusChanged(AccountStatusChanged $event): void
    {
        $this->notifier->send('ntf.status_akaun', $event->user, [
            'status_lama' => $event->from,
            'status_baharu' => $event->to,
        ], '/admin', 'status_akaun:'.$event->user->id.':'.$event->to);
    }

    /** SRS-RBC-22: suspicious login/access activity → Pentadbir Sistem (type's recipient rules). */
    public function suspiciousActivity(SuspiciousActivityDetected $event): void
    {
        $this->notifier->send('ntf.amaran_keselamatan', [], [
            'jenis' => self::JENIS_AMARAN[$event->type] ?? $event->type,
            'pengguna' => $event->user->email ?? (string) ($event->details['pengecam'] ?? '-'),
            'alamat_ip' => $event->ip ?? '-',
            'masa' => now()->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i'),
        ], '/admin/rbc/log-masuk', 'amaran_keselamatan:'.$event->type.':'.($event->user->id ?? $event->ip));
    }
}
