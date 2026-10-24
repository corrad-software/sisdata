<?php

namespace App\Modules\Aud\Services\Siem;

use Illuminate\Support\Facades\File;

/**
 * ArcSight Common Event Format over syslog (or a file a forwarder tails):
 * CEF:0|MAIS|SISDATA|1.0|<tindakan>|<ringkasan>|<severity>|key=value …
 */
class CefSiemSink implements SiemSink
{
    public function __construct(private bool $useSyslog, private string $path) {}

    public function send(array $record): void
    {
        $line = self::format($record);

        if ($this->useSyslog) {
            openlog('sisdata-audit', LOG_PID, LOG_AUTHPRIV);
            syslog(LOG_INFO, $line);
            closelog();

            return;
        }

        File::ensureDirectoryExists(dirname($this->path));
        file_put_contents($this->path, $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @param  array<string, mixed>  $r
     */
    public static function format(array $r): string
    {
        $status = (string) ($r['status_transaksi'] ?? '');
        $severity = match (true) {
            in_array($status, ['gagal', 'ditolak'], true) => 7,
            in_array($r['klasifikasi'] ?? null, ['sulit', 'rahsia'], true) => 5,
            default => 3,
        };

        $ext = [
            'rt' => isset($r['tarikh_masa_utc']) ? (string) (strtotime((string) $r['tarikh_masa_utc']) * 1000) : null,
            'suid' => $r['id_pengguna'] ?? null,
            'suser' => $r['pengecam_pengguna'] ?? null,
            'src' => $r['alamat_ip'] ?? null,
            'requestClientApplication' => $r['user_agent'] ?? null,
            'act' => $r['jenis_tindakan'] ?? null,
            'outcome' => $status,
            'cs1Label' => 'modul', 'cs1' => $r['modul'] ?? null,
            'cs2Label' => 'entiti', 'cs2' => $r['nama_entiti'] ?? null,
            'cs3Label' => 'idRekod', 'cs3' => $r['id_rekod'] ?? null,
            'cs4Label' => 'idKorelasi', 'cs4' => $r['id_korelasi'] ?? null,
            'cs5Label' => 'cincangan', 'cs5' => $r['cincangan_rekod'] ?? null,
            'cs6Label' => 'saluran', 'cs6' => $r['saluran_capaian'] ?? null,
            'cn1Label' => 'noUrutan', 'cn1' => $r['no_urutan'] ?? null,
            'reason' => $r['sebab_perubahan'] ?? null,
        ];

        $pairs = [];
        foreach ($ext as $key => $value) {
            if ($value !== null && $value !== '') {
                $pairs[] = $key.'='.self::escapeExtension((string) $value);
            }
        }

        $name = trim(($r['modul'] ?? '').' '.($r['jenis_tindakan'] ?? '').' '.($r['nama_entiti'] ?? ''));

        return sprintf(
            'CEF:0|MAIS|SISDATA|1.0|%s|%s|%d|%s',
            self::escapeHeader((string) ($r['jenis_tindakan'] ?? 'audit')),
            self::escapeHeader($name),
            $severity,
            implode(' ', $pairs),
        );
    }

    private static function escapeHeader(string $value): string
    {
        return str_replace(['\\', '|', "\n", "\r"], ['\\\\', '\\|', ' ', ' '], $value);
    }

    private static function escapeExtension(string $value): string
    {
        return str_replace(['\\', '=', "\n", "\r"], ['\\\\', '\\=', '\\n', '\\r'], $value);
    }

    public function name(): string
    {
        return 'cef';
    }
}
