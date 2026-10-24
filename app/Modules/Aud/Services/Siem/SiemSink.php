<?php

namespace App\Modules\Aud\Services\Siem;

/**
 * SRS-AUD-22: ships audit records to the MAIS SIEM, giving a copy outside the
 * control of SISDATA administrators. The real SIEM endpoint isn't available
 * yet; the local sinks write the agreed formats (JSON lines / CEF) to a file
 * or syslog that a SIEM forwarder (Filebeat, rsyslog, Wazuh agent…) tails.
 */
interface SiemSink
{
    /**
     * @param  array<string, mixed>  $record  audit_rekod columns + id + perubahan (list of field changes)
     */
    public function send(array $record): void;

    public function name(): string;
}
