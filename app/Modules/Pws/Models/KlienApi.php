<?php

namespace App\Modules\Pws\Models;

use Illuminate\Support\Carbon;

/**
 * An API client of the public gateway (the portal's web server). The secret
 * is stored only as a hash; tokens are short-lived (SRS-PWS-23).
 *
 * @property int $id
 * @property string $nama
 * @property string $id_klien
 * @property string $cincangan_rahsia
 * @property string|null $cincangan_rahsia_lama
 * @property Carbon|null $rahsia_lama_luput
 * @property list<string> $skop
 * @property list<string>|null $ip_dibenarkan
 * @property int $had_kadar_minit
 * @property Carbon|null $tarikh_luput
 * @property string $status
 * @property Carbon|null $tarikh_putaran
 * @property Carbon|null $terakhir_digunakan
 * @property int|null $dicipta_oleh
 * @property Carbon $created_at
 */
class KlienApi extends PwsModel
{
    protected $table = 'pws_klien_api';

    protected $hidden = ['cincangan_rahsia', 'cincangan_rahsia_lama'];

    /** Scopes a client can hold. */
    public const SKOP = [
        'baca' => 'Baca katalog hartanah, agihan manfaat, kandungan, statistik dan soalan lazim',
        'permohonan' => 'Hantar permohonan hasrat wakaf dan sewaan/pajakan; semak status',
        'bayaran' => 'Mulakan bayaran dalam talian dan semak status bayaran',
        'aduan' => 'Hantar aduan awam dan semak status aduan',
        'sokongan' => 'Hantar permintaan sokongan',
        'analitik' => 'Hantar statistik lawatan portal',
    ];

    protected function casts(): array
    {
        return [
            'skop' => 'array',
            'ip_dibenarkan' => 'array',
            'tarikh_luput' => 'date',
            'rahsia_lama_luput' => 'datetime',
            'tarikh_putaran' => 'datetime',
            'terakhir_digunakan' => 'datetime',
            'had_kadar_minit' => 'integer',
        ];
    }

    public function aktif(): bool
    {
        return $this->status === 'aktif' && ($this->tarikh_luput === null || $this->tarikh_luput->copy()->endOfDay()->isFuture());
    }

    public function ada(string $skop): bool
    {
        return in_array($skop, $this->skop ?? [], true);
    }
}
