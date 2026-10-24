<?php

namespace App\Modules\Gis\Contracts;

/**
 * Optional on a MapLayer: its minimum security classification (SRS-GIS-32,
 * SRS §4.2). A layer above "dalaman" needs gis.sulit.lihat / gis.rahsia.lihat
 * on top of its own permission, at the map-service level (tiles, GeoJSON,
 * OGC proxy). Administrators may raise — never lower — it in gis_lapisan.
 */
interface ClassifiedMapLayer extends MapLayer
{
    /** awam | dalaman | sulit | rahsia */
    public function klasifikasi(): string;
}
