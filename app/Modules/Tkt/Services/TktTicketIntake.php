<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Tkt\Contracts\TicketIntake;
use App\Modules\Tkt\Support\Lalai;
use InvalidArgumentException;

/**
 * MOD-TKT's TicketIntake: tickets from the public portal (MOD-PWS), e-mail
 * or a monitoring system enter the same flow as the in-app help button —
 * severity, assignment, SLA, acknowledgement. Idempotent on
 * (channel, sourceRef): re-sending returns the existing ticket id.
 */
class TktTicketIntake implements TicketIntake
{
    private const KEUTAMAAN = ['kritikal' => 1, 'tinggi' => 2, 'sederhana' => 3, 'rendah' => 4];

    public function __construct(private TiketService $service) {}

    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        if (trim($data['tajuk']) === '' || trim($data['keterangan']) === '') {
            throw new InvalidArgumentException('TicketIntake::open memerlukan tajuk dan keterangan.');
        }
        if (! array_key_exists($channel, Lalai::SALURAN)) {
            throw new InvalidArgumentException("Saluran tiket tidak dikenali: {$channel}");
        }
        $priority = $data['keutamaan'] ?? null;
        $severity = is_numeric($priority) ? (int) $priority : (self::KEUTAMAAN[strtolower((string) $priority)] ?? null);
        $konteks = is_array($data['konteks'] ?? null) ? $data['konteks'] : [];

        return $this->service->buka([
            'tajuk' => (string) $data['tajuk'],
            'penerangan' => (string) $data['keterangan'],
            'kategori' => $data['kategori'] ?? ($channel === 'pemantauan' ? 'ralat' : 'soalan'),
            'keterukan' => $severity !== null && $severity >= 1 && $severity <= 4 ? $severity : null,
            'modul' => $konteks['modul'] ?? null,
            'emel_pelapor' => $data['emel_pelapor'] ?? null,
        ], $channel, $reporter, $sourceRef !== null ? mb_substr($sourceRef, 0, 120) : null, $konteks)->id;
    }
}
