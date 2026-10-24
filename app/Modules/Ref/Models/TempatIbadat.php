<?php

namespace App\Modules\Ref\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Public point of interest from OpenStreetMap: masjid / surau in Selangor
 * (amenity=place_of_worship + religion=muslim). Names are as tagged in OSM.
 *
 * @property int $id
 * @property string $osm_jenis
 * @property int $osm_id
 * @property string|null $nama
 * @property string $jenis
 * @property string|null $daerah_kod
 * @property string|null $mukim_kod
 */
class TempatIbadat extends Model
{
    protected $table = 'ref_tempat_ibadat';

    protected $guarded = ['id'];

    protected $hidden = ['geometri'];

    protected function casts(): array
    {
        return [
            'osm_id' => 'integer',
            'tag' => 'array',
            'diambil_pada' => 'date',
        ];
    }
}
