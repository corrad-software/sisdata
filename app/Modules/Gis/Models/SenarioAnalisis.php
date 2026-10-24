<?php

namespace App\Modules\Gis\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-GIS-20 named analysis scenario.
 *
 * @property int $id
 * @property string $nama
 * @property string $jenis_analisis
 * @property array<string, mixed> $parameter_json
 * @property array<string, mixed> $ringkasan_hasil
 * @property int $id_pengguna
 * @property string $kebenaran_kongsi
 * @property list<int> $dikongsi_dengan
 * @property Carbon|null $created_at
 */
class SenarioAnalisis extends GisModel
{
    protected $table = 'gis_senario_analisis';

    public const JENIS = ['penimbal', 'tindihan', 'pertanyaan', 'keluasan', 'kedekatan', 'kluster'];

    public const KONGSI = ['peribadi', 'pengguna', 'semua'];

    protected function casts(): array
    {
        return ['parameter_json' => 'array', 'ringkasan_hasil' => 'array', 'dikongsi_dengan' => 'array'];
    }

    public function visibleTo(int $userId): bool
    {
        return $this->id_pengguna === $userId
            || $this->kebenaran_kongsi === 'semua'
            || ($this->kebenaran_kongsi === 'pengguna' && in_array($userId, array_map('intval', $this->dikongsi_dengan), true));
    }
}
