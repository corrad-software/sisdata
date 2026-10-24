<?php

namespace App\Modules\Adm\Services\Siem;

/**
 * JSON-lines file a SIEM agent (Filebeat, Wazuh, Splunk UF…) tails. Default
 * locally: storage/logs/siem-keselamatan.log.
 */
class PenghantarFail implements PenghantarSiem
{
    public function __construct(private string $path) {}

    public function hantar(array $event): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($this->path, json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
    }

    public function path(): string
    {
        return $this->path;
    }
}
