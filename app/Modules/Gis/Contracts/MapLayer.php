<?php

namespace App\Modules\Gis\Contracts;

use App\Models\User;

/**
 * A module's contribution to the GIS map (SRS-GIS-01/08/09). MOD-GIS lists
 * registered layers, styles them with the legend, and asks the layer for
 * features in the current view. The layer MUST filter by the user's access
 * (SRS-RBC-08: GIS cannot bypass authorisation) — typically by querying its
 * own model, which carries ScopedByAccess.
 */
interface MapLayer
{
    /** Stable key, e.g. "pro.lot", "adu.aduan". */
    public function key(): string;

    public function label(): string;

    /** Layer group in the layer switcher: asas | hartanah | operasi | risiko | rujukan. */
    public function group(): string;

    /** polygon | line | point */
    public function geometryType(): string;

    /** Permission needed to see the layer at all. */
    public function permission(): string;

    /**
     * Legend entries; `nilai` matches the feature property named by `medan`.
     *
     * @return array{medan: string|null, entri: list<array{nilai: string, label: string, warna: string}>}
     */
    public function legend(): array;

    /**
     * GeoJSON features (SRID 4326) inside the bounding box, filtered by access.
     * Each feature's `properties` must include `id`, `label` and `pautan`
     * (frontend deep link, e.g. the lot's 360° profile — SRS-GIS-09).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox  minLng, minLat, maxLng, maxLat
     * @param  array<string, mixed>  $filters  e.g. kod_daerah, kod_mukim, status
     * @return array{type: 'FeatureCollection', features: list<array<string, mixed>>}
     */
    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array;
}
