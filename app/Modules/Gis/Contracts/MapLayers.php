<?php

namespace App\Modules\Gis\Contracts;

/**
 * Registry of map layers (singleton, implemented by MOD-GIS). Modules register
 * their layers lazily so they never force MOD-GIS to boot early:
 *
 *     $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(PajakanLayer::class));
 *
 * A layer may additionally implement VectorTileLayer (served as Mapbox Vector
 * Tiles — recommended for large polygon layers) and/or ClassifiedMapLayer
 * (minimum security classification, SRS-GIS-32). Key prefix = module code
 * ("pjk.pajakan") — MOD-GIS uses it to fill the mandatory layer slots of
 * SRS-GIS-11 (pjk → Status Pajakan, cki → Status Cukai, ptn → Pemantauan,
 * adu → Aduan, kes → Kes Mahkamah, lap → Risiko, ast → Aset Fizikal).
 */
interface MapLayers
{
    /** @param class-string<MapLayer> $layer */
    public function register(string $layer): void;
}
