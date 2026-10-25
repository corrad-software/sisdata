<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-08: response / resolution thresholds per severity (minutes of
 * service time), service hours, warning threshold and escalation hierarchy.
 * A new row with a later tarikh_berkuat_kuasa supersedes the old one, so
 * history is kept (SRS-TKT-21).
 *
 * @property int $id
 * @property int $keterukan
 * @property int $ambang_respons
 * @property int $ambang_penyelesaian
 * @property string $waktu_perkhidmatan
 * @property int $ambang_amaran_peratus
 * @property list<array{peringkat: int, penerima: string, selepas_minit: int}> $hierarki_eskalasi
 * @property Carbon $tarikh_berkuat_kuasa
 * @property bool $aktif
 * @property string|null $catatan
 */
class SlaKonfigurasi extends TktModel
{
    protected $table = 'tkt_sla_konfigurasi';

    protected function casts(): array
    {
        return ['hierarki_eskalasi' => 'array', 'tarikh_berkuat_kuasa' => 'date', 'aktif' => 'boolean', 'keterukan' => 'integer', 'ambang_respons' => 'integer', 'ambang_penyelesaian' => 'integer', 'ambang_amaran_peratus' => 'integer'];
    }

    public static function semasa(int $keterukan): ?self
    {
        return self::query()->where('keterukan', $keterukan)->where('aktif', true)
            ->whereDate('tarikh_berkuat_kuasa', '<=', now())
            ->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->first();
    }
}
