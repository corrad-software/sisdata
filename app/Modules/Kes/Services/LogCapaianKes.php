<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Kes\Models\LogCapaian;
use Illuminate\Support\Facades\Auth;

/**
 * SRS-KES-13 / §4.2 "diaudit setiap paparan": every view, download, print
 * and export of a case record or document is written twice — to the case
 * access log (kes_log_capaian: user, IP, timestamp — the legal division's
 * report) and to the central audit trail (MOD-AUD, classification rahsia).
 */
class LogCapaianKes
{
    private const AUD = ['lihat' => 'lihat', 'lihat_senarai' => 'lihat', 'lihat_pihak' => 'lihat', 'muat_turun' => 'muat_turun', 'cetak' => 'cetak', 'eksport' => 'eksport', 'ditolak' => 'akses_ditolak'];

    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $meta */
    public function rekod(string $jenis, ?string $kesId = null, ?string $dokumenId = null, ?string $keterangan = null, array $meta = [], ?User $user = null): void
    {
        $user ??= Auth::user();
        $request = request();
        LogCapaian::query()->create([
            'kes_mahkamah_id' => $kesId,
            'id_dokumen' => $dokumenId,
            'id_pengguna' => $user instanceof User ? $user->id : null,
            'jenis_tindakan' => $jenis,
            'cap_masa' => now(),
            'alamat_ip' => $request->ip(),
            'ejen_pengguna' => mb_substr((string) $request->userAgent(), 0, 255),
            'keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 500),
        ]);
        $this->audit->record(
            self::AUD[$jenis] ?? $jenis,
            'kes',
            $dokumenId !== null ? 'dokumen' : ($kesId !== null ? 'kes_mahkamah' : null),
            $dokumenId ?? $kesId,
            $jenis === 'ditolak' ? 'gagal' : 'berjaya',
            $jenis === 'ditolak' ? $keterangan : null,
            meta: ['klasifikasi' => 'rahsia', 'jenis_capaian' => $jenis, 'kes_mahkamah_id' => $kesId, ...$meta],
        );
    }
}
