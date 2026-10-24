<?php

namespace App\Modules\Adm\Services\Siem;

/**
 * RFC 5424 syslog over UDP with a CEF payload — the lowest common denominator
 * most SIEMs (QRadar, ArcSight, Splunk, Wazuh) ingest out of the box.
 */
class PenghantarSyslog implements PenghantarSiem
{
    public function __construct(private string $host, private int $port = 514, private string $appName = 'sisdata') {}

    public function hantar(array $event): void
    {
        $socket = @stream_socket_client("udp://{$this->host}:{$this->port}", $errno, $errstr, 1.0);
        if (! $socket) {
            return;
        }

        @fwrite($socket, $this->format($event));
        @fclose($socket);
    }

    /** @param array<string, mixed> $event */
    public function format(array $event): string
    {
        $severity = match ($event['event']['outcome'] ?? null) {
            'failure' => 4, // warning
            default => 6,   // informational
        };
        $pri = 10 * 8 + $severity; // facility authpriv(10)

        $ext = [
            'rt' => (string) ($event['@timestamp'] ?? ''),
            'act' => (string) ($event['event']['action'] ?? ''),
            'outcome' => (string) ($event['event']['outcome'] ?? ''),
            'suid' => (string) ($event['user']['id'] ?? ''),
            'src' => (string) ($event['source']['ip'] ?? ''),
            'requestId' => (string) ($event['trace']['id'] ?? ''),
            'msg' => (string) ($event['message'] ?? ''),
        ];
        $extension = implode(' ', array_map(
            fn ($k, $v) => $k.'='.str_replace(['\\', '=', "\n"], ['\\\\', '\\=', ' '], $v),
            array_keys($ext),
            $ext,
        ));

        $cef = sprintf(
            'CEF:0|MAIS|SISDATA|1.0|%s|%s|%d|%s',
            str_replace('|', '\\|', (string) ($event['event']['action'] ?? 'event')),
            str_replace('|', '\\|', (string) ($event['message'] ?? '')),
            $severity === 4 ? 6 : 3,
            $extension,
        );

        return sprintf('<%d>1 %s %s %s - - - %s', $pri, now()->toIso8601String(), gethostname() ?: '-', $this->appName, $cef);
    }
}
