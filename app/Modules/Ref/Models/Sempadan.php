<?php

namespace App\Modules\Ref\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Boundary polygon of a negeri / daerah / mukim / PBT (SRID 4326).
 * `anggaran` marks derived, non-gazetted shapes (all mukim polygons today).
 *
 * @property int $id
 * @property string $jenis
 * @property string $kod
 * @property string $nama
 * @property bool $anggaran
 * @property string $sumber
 * @property string|null $sumber_url
 * @property string|null $lesen
 * @property int|null $osm_id
 */
class Sempadan extends Model
{
    public const JENIS = ['negeri', 'daerah', 'mukim', 'pbt'];

    protected $table = 'ref_sempadan';

    protected $guarded = ['id'];

    protected $hidden = ['geometri'];

    protected function casts(): array
    {
        return [
            'anggaran' => 'boolean',
            'luas_km2' => 'float',
            'toleransi_permudah' => 'float',
            'diambil_pada' => 'date',
            'osm_id' => 'integer',
        ];
    }

    /**
     * GeoJSON Feature (geometry read straight from PostGIS).
     *
     * @return array<string, mixed>
     */
    public function toFeature(int $precision = 6): array
    {
        $geojson = DB::table($this->table)->where('id', $this->id)->selectRaw('ST_AsGeoJSON(geometri, ?) AS g', [$precision])->value('g');

        return [
            'type' => 'Feature',
            'properties' => [
                'jenis' => $this->jenis,
                'kod' => $this->kod,
                'nama' => $this->nama,
                'anggaran' => $this->anggaran,
                'luas_km2' => $this->getAttribute('luas_km2'),
                'sumber' => $this->sumber,
                'lesen' => $this->lesen,
                'atribusi' => $this->lesen ? '© OpenStreetMap contributors' : null,
            ],
            'geometry' => json_decode((string) $geojson, true),
        ];
    }
}
