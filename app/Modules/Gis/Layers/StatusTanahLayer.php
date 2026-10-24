<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Support\PenunjukLot as P;

/**
 * SRS-GIS-13: land by status — kosong, disewa, wakaf, dalam tindakan,
 * bermasalah, cukai tertunggak, berpotensi dibangunkan. One status per lot,
 * by priority (legal action first). Indicators read other modules' tables.
 */
class StatusTanahLayer extends LotLayer
{
    public const PALET = [
        'dalam_tindakan' => ['#7F1D1D', 'Dalam tindakan (kes perundangan)'],
        'bermasalah' => ['#DC2626', 'Bermasalah (aduan terbuka / bertindih)'],
        'cukai_tertunggak' => ['#EA580C', 'Cukai tertunggak'],
        'disewa' => ['#2563EB', 'Disewa / dipajak'],
        'berpotensi' => ['#9333EA', 'Berpotensi dibangunkan'],
        'wakaf' => ['#059669', 'Wakaf'],
        'kosong' => ['#A1A1AA', 'Kosong'],
    ];

    public function key(): string
    {
        return 'gis.status_tanah';
    }

    public function label(): string
    {
        return 'Status Tanah';
    }

    /** Court cases are Rahsia (SRS §4.2): the "dalam tindakan" status is only derived for users who may see them. */
    private bool $withCases = false;

    public function source(User $user, array $filters = []): array
    {
        $this->withCases = in_array('rahsia', $this->scope->levels($user), true);

        return parent::source($user, $filters);
    }

    public function scopeSignature(User $user): string
    {
        return $this->scope->signature($user, $this->key());
    }

    protected function extraSelect(): string
    {
        $cases = $this->withCases ? P::kesAktif() : 'FALSE';

        return 'CASE WHEN '.$cases." THEN 'dalam_tindakan'
                     WHEN ".P::aduanTerbuka().' OR '.P::bertindih()." THEN 'bermasalah'
                     WHEN ".P::cukaiTertunggak()." THEN 'cukai_tertunggak'
                     WHEN ".P::pajakanAktif()." THEN 'disewa'
                     WHEN ".P::berpotensi()." THEN 'berpotensi'
                     WHEN l.bahagian_pemilik = 'wakaf' THEN 'wakaf'
                     ELSE 'kosong' END AS status_tanah";
    }

    protected function extraColumns(): array
    {
        return ['status_tanah'];
    }

    public function legend(): array
    {
        $entries = [];
        foreach (self::PALET as $value => [$colour, $label]) {
            $entries[] = ['nilai' => $value, 'label' => $label, 'warna' => $colour];
        }

        return ['medan' => 'status_tanah', 'entri' => $entries];
    }
}
