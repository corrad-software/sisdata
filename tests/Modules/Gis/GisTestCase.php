<?php

namespace Tests\Modules\Gis;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Gis\GisServiceProvider;
use App\Modules\Gis\Support\Jadual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Gis\Support\ArrayParameters;
use Tests\Modules\Gis\Support\FakeDocumentStore;
use Tests\TestCase;

/**
 * MOD-GIS test set-up: real RBC (factory permissions / scopes) and AUD, a
 * fake DMS, array parameters, and a tiny synthetic geography — a Selangor
 * rectangle, two daerah (1008 Petaling west, 1009 Sepang east), two mukim,
 * mosques, and lots inserted straight into lot_tanah / data_spatial (MOD-PRO
 * owns those models; GIS only reads them).
 */
abstract class GisTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeDocumentStore $documents;

    protected ArrayParameters $params;

    public const PENGGUNA = ['gis.peta.lihat', 'pro.lot.lihat'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        $this->params->values['gis.tile_cache_saat'] = 0;
        Jadual::flush();
        $this->seedGeography();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions = self::PENGGUNA, ?array $districts = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function specialist(?array $districts = null): User
    {
        return $this->user([...array_keys(GisServiceProvider::PERMISSIONS), 'pro.lot.lihat', 'pro.sulit.lihat', 'pro.rahsia.lihat', 'hkm.hak_milik.lihat', 'per.kes.lihat'], $districts);
    }

    protected function seedGeography(): void
    {
        $now = now();
        $boundary = fn (string $jenis, string $kod, string $nama, string $wkt) => DB::insert(
            "INSERT INTO ref_sempadan (jenis, kod, nama, geometri, anggaran, sumber, created_at, updated_at) VALUES (?, ?, ?, ST_Multi(ST_GeomFromText(?, 4326)), false, 'ujian', ?, ?)",
            [$jenis, $kod, $nama, $wkt, $now, $now],
        );
        $boundary('negeri', '10', 'Selangor', 'POLYGON((100.8 2.6, 101.95 2.6, 101.95 3.85, 100.8 3.85, 100.8 2.6))');
        $boundary('daerah', '1008', 'Petaling', 'POLYGON((101.50 3.00, 101.60 3.00, 101.60 3.20, 101.50 3.20, 101.50 3.00))');
        $boundary('daerah', '1009', 'Sepang', 'POLYGON((101.60 3.00, 101.70 3.00, 101.70 3.20, 101.60 3.20, 101.60 3.00))');
        $boundary('mukim', '100803', 'Damansara', 'POLYGON((101.50 3.00, 101.60 3.00, 101.60 3.20, 101.50 3.20, 101.50 3.00))');
        $boundary('mukim', '100901', 'Dengkil', 'POLYGON((101.60 3.00, 101.70 3.00, 101.70 3.20, 101.60 3.20, 101.60 3.00))');

        foreach ([['Masjid Damansara', 'masjid', 101.5520, 3.1000], ['Surau Dengkil', 'surau', 101.6600, 3.1000]] as $i => [$nama, $jenis, $x, $y]) {
            DB::insert(
                "INSERT INTO ref_tempat_ibadat (osm_jenis, osm_id, nama, jenis, daerah_kod, mukim_kod, geometri, sumber, lesen, diambil_pada, created_at, updated_at)
                 VALUES ('node', ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), 'ujian', 'ODbL', ?, ?, ?)",
                [$i + 1, $nama, $jenis, $x < 101.6 ? '1008' : '1009', $x < 101.6 ? '100803' : '100901', $x, $y, $now->toDateString(), $now, $now],
            );
        }
    }

    protected function square(float $lng, float $lat, float $size = 0.001): string
    {
        $x1 = $lng + $size;
        $y1 = $lat + $size;

        return "MULTIPOLYGON((({$lng} {$lat}, {$x1} {$lat}, {$x1} {$y1}, {$lng} {$y1}, {$lng} {$lat})))";
    }

    /**
     * A lot (+ current geometry when $wkt is given), inserted directly.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function lot(?string $wkt = null, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        $mukim = $attributes['kod_mukim'] ?? '100803';
        DB::table('lot_tanah')->insert($attributes + [
            'id' => $id,
            'no_lot' => 'Lot '.random_int(1000, 999999),
            'kod_mukim' => $mukim,
            'kod_daerah' => substr($mukim, 0, 4),
            'keluasan' => 1234.5,
            'unit_keluasan' => 'm2',
            'kategori_guna_tanah' => 'BANGUNAN',
            'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif',
            'klasifikasi' => 'dalaman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($wkt !== null) {
            $this->geometry($id, $wkt);
        }

        return $id;
    }

    /** Add a geometry version (previous one becomes non-current). */
    protected function geometry(string $lotId, string $wkt, string $status = 'belum_disahkan', bool $valid = true): int
    {
        DB::table('data_spatial')->where('lot_id', $lotId)->update(['semasa' => false]);
        $versi = (int) DB::table('data_spatial')->where('lot_id', $lotId)->max('versi') + 1;
        $geom = $valid ? 'ST_Multi(ST_GeomFromText(?, 4326))' : 'ST_GeomFromText(?, 4326)';
        $row = DB::selectOne(
            "INSERT INTO data_spatial (lot_id, srid_asal, sumber_data, status_pengesahan, semasa, versi, geometri, keluasan_dikira, created_at, updated_at)
             VALUES (?, 4326, 'sintetik', ?, true, ?, {$geom}, ST_Area(ST_GeomFromText(?, 4326)::geography), now(), now()) RETURNING id",
            [$lotId, $status, $versi, $wkt, $wkt],
        );
        DB::update('UPDATE lot_tanah SET koordinat_pusat = (SELECT ST_PointOnSurface(geometri) FROM data_spatial WHERE id = ?) WHERE id = ?', [$row->id, $lotId]);

        return (int) $row->id;
    }
}
