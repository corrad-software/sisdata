<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use App\Modules\Gis\Models\Lapisan;
use App\Modules\Gis\Models\MetadataLapisan;
use App\Modules\Gis\Support\KatalogWajib;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Layer-level access control at the map-service level (SRS-GIS-32,
 * SRS-RBC-08): every feature, tile, export and OGC request goes through
 * authorize(); the UI hiding a layer is never relied upon. A denied direct
 * request is recorded in the audit trail (acceptance criterion 4).
 */
class LayerService
{
    public const LEVELS = ['awam' => 0, 'dalaman' => 1, 'sulit' => 2, 'rahsia' => 3];

    public const LEVEL_PERMISSION = ['sulit' => 'gis.sulit.lihat', 'rahsia' => 'gis.rahsia.lihat'];

    /** Default stacking order (bottom → top) and initial visibility. */
    private const DEFAULTS = [
        'gis.lot' => [10, true], 'gis.kategori_guna_tanah' => [12, false], 'gis.zoning' => [14, false], 'gis.hak_milik' => [16, false],
        'gis.status_tanah' => [18, false], 'gis.risiko' => [20, false], 'gis.set_data' => [40, false], 'gis.aset_fizikal' => [60, false],
        'gis.pemerolehan' => [70, false], 'gis.tempat_ibadat' => [80, false], 'gis.sempadan_mukim' => [90, false],
        'gis.sempadan_daerah' => [91, true], 'gis.sempadan_pbt' => [92, false],
    ];

    /** @var array<string, Lapisan> */
    private array $configs = [];

    public function __construct(
        private LayerRegistry $registry,
        private AccessControl $access,
        private AuditLogger $audit,
    ) {}

    public function config(MapLayer $layer): Lapisan
    {
        if (isset($this->configs[$layer->key()])) {
            return $this->configs[$layer->key()];
        }
        [$order, $visible] = self::DEFAULTS[$layer->key()] ?? [50, false];

        $config = Lapisan::query()->firstOrCreate(['kod_lapisan' => $layer->key()], [
            'nama' => $layer->label(),
            'kumpulan' => $layer->group(),
            'jenis_geometri' => $layer->geometryType(),
            'klasifikasi_keselamatan' => $this->declaredLevel($layer),
            'urutan_paparan' => $order,
            'papar_lalai' => $visible,
            'terbit_ogc' => $layer->key() === 'gis.lot',
        ]);

        // Pick up column defaults (opacity, status…) on a freshly created row.
        return $this->configs[$layer->key()] = $config->wasRecentlyCreated ? $config->refresh() : $config;
    }

    public function forget(): void
    {
        $this->configs = [];
    }

    public function declaredLevel(MapLayer $layer): string
    {
        $level = $layer instanceof ClassifiedMapLayer ? $layer->klasifikasi() : 'dalaman';

        return array_key_exists($level, self::LEVELS) ? $level : 'dalaman';
    }

    /** The stricter of the layer's declared level and the configured one. */
    public function level(MapLayer $layer): string
    {
        $declared = $this->declaredLevel($layer);
        $configured = $this->config($layer)->klasifikasi_keselamatan;

        return self::LEVELS[$configured] > self::LEVELS[$declared] ? $configured : $declared;
    }

    public function can(User $user, MapLayer $layer): bool
    {
        if (! $this->access->allows($user, 'gis.peta.lihat')) {
            return false;
        }
        $status = $this->config($layer)->status_terbit;
        if ($status === 'tidak_aktif' || ($status === 'draf' && ! $this->access->allows($user, 'gis.lapisan.kemas_kini'))) {
            return false;
        }
        if (! $this->access->allows($user, $layer->permission())) {
            return false;
        }
        $permission = self::LEVEL_PERMISSION[$this->level($layer)] ?? null;

        return $permission === null || $this->access->allows($user, $permission);
    }

    /**
     * Resolve + authorise a layer for a direct map-service request.
     *
     * @throws AuthorizationException|NotFoundHttpException
     */
    public function authorize(User $user, string $key, string $channel): MapLayer
    {
        $layer = $this->registry->get($key);
        if ($layer === null) {
            throw new NotFoundHttpException(__('gis::gis.lapisan.tidak_wujud'));
        }
        if (! $this->can($user, $layer)) {
            $this->audit->record('akses_ditolak', 'gis', 'gis_lapisan', $key, 'gagal', __('gis::gis.lapisan.ditolak_audit'), meta: [
                'saluran' => $channel, 'klasifikasi' => $this->level($layer),
            ]);

            throw new AuthorizationException(__('gis::gis.lapisan.ditolak'));
        }

        return $layer;
    }

    /** @return list<MapLayer> layers $user may use, in display order */
    public function visible(User $user): array
    {
        $layers = array_values(array_filter($this->registry->all(), fn (MapLayer $l) => $this->can($user, $l)));
        usort($layers, fn (MapLayer $a, MapLayer $b) => $this->config($a)->urutan_paparan <=> $this->config($b)->urutan_paparan);

        return $layers;
    }

    /** @return array<string, mixed> */
    public function describe(MapLayer $layer): array
    {
        $config = $this->config($layer);
        $meta = MetadataLapisan::query()->where('kod_lapisan', $layer->key())->first();

        return [
            'kod' => $layer->key(),
            'nama' => $config->nama ?: $layer->label(),
            'kumpulan' => $config->kumpulan ?: $layer->group(),
            'jenis_geometri' => $layer->geometryType(),
            'legenda' => $layer->legend(),
            'klasifikasi' => $this->level($layer),
            'sumber' => $layer instanceof VectorTileLayer ? 'mvt' : 'geojson',
            'urutan' => $config->urutan_paparan,
            'ketelusan' => (float) $config->ketelusan_lalai,
            'papar_lalai' => $config->papar_lalai,
            'status_terbit' => $config->status_terbit,
            'terbit_ogc' => $config->terbit_ogc,
            'gaya' => $this->styleHints($layer),
            'metadata' => $meta?->only(MetadataLapisan::FIELDS),
        ];
    }

    /** @return array<string, mixed> rendering hints the UI understands */
    private function styleHints(MapLayer $layer): array
    {
        return match ($layer->key()) {
            'gis.hak_milik' => ['garis_putus' => 'tanpa_hak_milik_sah'],
            'gis.zoning' => ['garis_tebal' => true],
            'gis.risiko' => ['peta_haba' => 'skor_risiko'],
            'gis.sempadan_daerah', 'gis.sempadan_mukim', 'gis.sempadan_pbt' => ['garis_sahaja' => true],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    public function mandatory(User $user): array
    {
        $visible = array_map(fn (MapLayer $l) => $l->key(), $this->visible($user));

        return array_map(function ($slot) use ($visible) {
            $slot['dibenarkan'] = array_values(array_intersect($slot['lapisan'], $visible));

            return $slot;
        }, KatalogWajib::status(array_keys($this->registry->all())));
    }
}
