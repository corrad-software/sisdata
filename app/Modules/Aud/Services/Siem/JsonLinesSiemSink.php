<?php

namespace App\Modules\Aud\Services\Siem;

use Illuminate\Support\Facades\File;

/** One JSON object per line (ECS-like keys plus the original SISDATA fields). */
class JsonLinesSiemSink implements SiemSink
{
    public function __construct(private string $path) {}

    public function send(array $record): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        $line = [
            '@timestamp' => $record['tarikh_masa_utc'] ?? null,
            'event' => [
                'dataset' => 'sisdata.audit',
                'action' => $record['jenis_tindakan'] ?? null,
                'outcome' => $record['status_transaksi'] ?? null,
                'module' => $record['modul'] ?? null,
                'sequence' => $record['no_urutan'] ?? null,
                'hash' => $record['cincangan_rekod'] ?? null,
            ],
            'user' => ['id' => $record['id_pengguna'] ?? null, 'name' => $record['pengecam_pengguna'] ?? null],
            'source' => ['ip' => $record['alamat_ip'] ?? null],
            'trace' => ['id' => $record['id_korelasi'] ?? null],
            'sisdata' => $record,
        ];

        file_put_contents($this->path, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
    }

    public function name(): string
    {
        return 'jsonl';
    }
}
