<?php

namespace App\Modules\Pws\Models;

use Illuminate\Support\Carbon;

/**
 * Outbox + status mirror of one application sent from the portal. The
 * reference is issued by SISDATA (SRS-PWS-08); the case itself lives in
 * MOD-PER (wakaf) or MOD-PJK (sewaan). `kandungan` is encrypted and emptied
 * once delivered (SRS-PWS-20).
 *
 * @property int $id
 * @property string $no_rujukan
 * @property string $jenis
 * @property int|null $klien_id
 * @property array<string, mixed>|null $kandungan
 * @property string|null $cincangan_emel
 * @property string|null $kod_daerah
 * @property string|null $kategori
 * @property string $status_penghantaran
 * @property int $bilangan_cuba
 * @property string|null $ralat_terakhir
 * @property string|null $rujukan_sisdata
 * @property string $status
 * @property string|null $status_nama
 * @property list<array{status: string, nama: string|null, pada: string}>|null $sejarah_status
 * @property Carbon|null $tarikh_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Permohonan extends PwsModel
{
    protected $table = 'pws_permohonan';

    protected $hidden = ['kandungan', 'cincangan_emel'];

    protected function casts(): array
    {
        return [
            'kandungan' => 'encrypted:array',
            'sejarah_status' => 'array',
            'tarikh_status' => 'datetime',
            'bilangan_cuba' => 'integer',
        ];
    }

    /** Record a status change (two-way status, SRS-PWS-21 iii); no-op when unchanged. */
    public function catatStatus(string $status, ?string $nama = null): void
    {
        if ($status === $this->status && $nama === $this->status_nama) {
            return;
        }
        $history = $this->sejarah_status ?? [];
        $history[] = ['status' => $status, 'nama' => $nama, 'pada' => now()->toIso8601String()];
        $this->forceFill(['status' => $status, 'status_nama' => $nama, 'sejarah_status' => $history, 'tarikh_status' => now()])->save();
    }
}
