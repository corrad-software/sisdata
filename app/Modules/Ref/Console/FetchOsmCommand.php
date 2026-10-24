<?php

namespace App\Modules\Ref\Console;

use App\Modules\Ref\Services\NameNormaliser;
use App\Modules\Ref\Services\OsmGeometry;
use App\Modules\Ref\Services\OverpassClient;
use App\Modules\Ref\Support\RefData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Downloads public OpenStreetMap data for Selangor through Overpass, caches
 * the raw responses in storage/app/ref-raw/osm (gitignored), and writes the
 * processed, simplified GeoJSON that is committed in Database/data/.
 * Idempotent: without --refresh it re-processes the cached downloads only.
 *
 * Data © OpenStreetMap contributors, ODbL 1.0 — see Database/data/MANIFEST.md.
 */
class FetchOsmCommand extends Command
{
    protected $signature = 'ref:fetch-osm
        {--step=* : negeri, daerah, pbt, mukim, masjid (default: all, in that order)}
        {--refresh : Download again from Overpass instead of using the cache}
        {--tolerance=0.0005 : Simplification tolerance in degrees (~55 m)}';

    protected $description = 'Fetch Selangor boundaries and mosques/surau from OpenStreetMap (Overpass) into Ref data files';

    private const STEPS = ['negeri', 'daerah', 'pbt', 'mukim', 'masjid'];

    private const LICENCE = 'ODbL 1.0 — © OpenStreetMap contributors';

    private const OSM_URL = 'https://www.openstreetmap.org/copyright';

    private const OVERPASS = 'https://overpass-api.de/api/interpreter';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('PostGIS (pgsql) connection required.');

            return self::FAILURE;
        }

        $steps = $this->option('step') ?: self::STEPS;
        $client = new OverpassClient(self::OVERPASS);

        foreach (self::STEPS as $step) {
            if (in_array($step, $steps, true)) {
                $this->components->task("OSM: {$step}", fn () => $this->{'step'.ucfirst($step)}($client));
            }
        }

        $this->line('Data © OpenStreetMap contributors (ODbL). Files: '.RefData::path());

        return self::SUCCESS;
    }

    private function tolerance(): float
    {
        return (float) $this->option('tolerance');
    }

    private function stepNegeri(OverpassClient $client): void
    {
        $negeri = collect(RefData::json('negeri.json'))->keyBy('kod_iso');
        $data = $client->query('negeri', '[out:json][timeout:300];rel["boundary"="administrative"]["admin_level"="4"]["ISO3166-2"~"^MY-(10|14|16)$"];out geom;', $this->option('refresh'));

        OsmGeometry::createTemp();
        foreach ($data['elements'] as $rel) {
            $iso = $rel['tags']['ISO3166-2'] ?? null;
            if ($iso && isset($negeri[$iso])) {
                OsmGeometry::insertRelation($negeri[$iso]['kod'], $negeri[$iso]['nama'], $rel['id'], OsmGeometry::relationLines($rel));
            }
        }
        OsmGeometry::simplify($this->tolerance(), coverage: true);
        $this->write('sempadan_negeri.geojson', 'sempadan_negeri', 'negeri', $client);
    }

    private function stepDaerah(OverpassClient $client): void
    {
        $daerah = collect(RefData::json('daerah.json'))->keyBy('osm_relation_id');
        $ids = $daerah->keys()->implode(',');
        $data = $client->query('daerah', "[out:json][timeout:300];rel(id:{$ids});out geom;", $this->option('refresh'));

        OsmGeometry::createTemp();
        foreach ($data['elements'] as $rel) {
            $d = $daerah[$rel['id']] ?? null;
            if ($d) {
                OsmGeometry::insertRelation($d['kod'], $d['nama'], $rel['id'], OsmGeometry::relationLines($rel));
            }
        }
        OsmGeometry::simplify($this->tolerance(), coverage: true);
        $this->write('sempadan_daerah.geojson', 'sempadan_daerah', 'daerah', $client);
    }

    private function stepPbt(OverpassClient $client): void
    {
        $pbt = collect(RefData::json('pbt.json'))->filter(fn ($p) => $p['osm_relation_id'])->keyBy('osm_relation_id');
        $ids = $pbt->keys()->implode(',');
        $data = $client->query('pbt', "[out:json][timeout:300];rel(id:{$ids});out geom;", $this->option('refresh'));

        OsmGeometry::createTemp();
        foreach ($data['elements'] as $rel) {
            $p = $pbt[$rel['id']] ?? null;
            if ($p) {
                OsmGeometry::insertRelation($p['kod'], $p['nama'], $rel['id'], OsmGeometry::relationLines($rel));
            }
        }
        // PBT areas overlap the OSM city/place relations, so no coverage simplification.
        OsmGeometry::simplify($this->tolerance(), coverage: false);
        $this->write('sempadan_pbt.geojson', 'sempadan_pbt', 'pbt', $client, note: 'Hanya PBT yang mempunyai sempadan dalam OSM; sempadan OSM (Sinar Project) mungkin tidak sama dengan warta.');
    }

    /**
     * OSM has no complete mukim layer for Selangor, so mukim areas are
     * APPROXIMATED: one seed point per mukim (an OSM place node of the same
     * name, or a manual point from mukim_titik.json), Voronoi cells clipped
     * to the district boundary. Flagged anggaran = true everywhere.
     */
    private function stepMukim(OverpassClient $client): void
    {
        $places = $client->query('tempat', '[out:json][timeout:300];area["ISO3166-2"="MY-10"]["admin_level"="4"]->.s;node(area.s)["place"~"^(city|town|village|suburb|hamlet|neighbourhood|quarter|locality)$"]["name"];out;', $this->option('refresh'));
        $overrides = RefData::exists('mukim_titik.json') ? RefData::json('mukim_titik.json') : [];
        $mukim = RefData::json('mukim.json');
        $daerahFc = RefData::json('sempadan_daerah.geojson');

        DB::statement('DROP TABLE IF EXISTS tmp_ref_daerah');
        DB::statement('CREATE TEMP TABLE tmp_ref_daerah (kod text PRIMARY KEY, g geometry(MultiPolygon, 4326))');
        foreach ($daerahFc['features'] as $f) {
            DB::insert('INSERT INTO tmp_ref_daerah VALUES (?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))', [$f['properties']['kod'], json_encode($f['geometry'])]);
        }
        DB::statement('DROP TABLE IF EXISTS tmp_ref_titik');
        DB::statement('CREATE TEMP TABLE tmp_ref_titik (kod text PRIMARY KEY, daerah_kod text, nama text, asal text, g geometry(Point, 4326))');

        $rank = ['town' => 1, 'city' => 1, 'suburb' => 2, 'village' => 3, 'quarter' => 4, 'neighbourhood' => 5, 'hamlet' => 6, 'locality' => 7];
        $missing = [];

        foreach ($mukim as $m) {
            $override = $overrides[$m['kod']] ?? null;
            $wanted = NameNormaliser::variants($override['tempat'] ?? $m['nama'], $override ? [] : $m['nama_lain']);

            $point = null;
            if (isset($override['lon'], $override['lat'])) {
                $point = [$override['lon'], $override['lat'], 'manual: '.($override['catatan'] ?? 'titik anggaran')];
            } else {
                $candidates = collect($places['elements'])
                    ->filter(fn ($n) => in_array(NameNormaliser::normalise($n['tags']['name']), $wanted, true))
                    ->sortBy(fn ($n) => $rank[$n['tags']['place']] ?? 9);
                foreach ($candidates as $node) {
                    $inside = DB::selectOne('SELECT ST_Contains(g, ST_SetSRID(ST_MakePoint(?, ?), 4326)) AS ok FROM tmp_ref_daerah WHERE kod = ?', [$node['lon'], $node['lat'], $m['daerah_kod']]);
                    if ($inside?->ok) {
                        $point = [$node['lon'], $node['lat'], "osm node/{$node['id']} ({$node['tags']['place']}: {$node['tags']['name']})"];
                        break;
                    }
                }
            }

            if (! $point) {
                $missing[] = "{$m['kod']} {$m['nama']}";

                continue;
            }

            DB::insert('INSERT INTO tmp_ref_titik VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$m['kod'], $m['daerah_kod'], $m['nama'], $point[2], $point[0], $point[1]]);
        }

        if ($missing !== []) {
            $this->newLine();
            $this->warn('No seed point for: '.implode(', ', $missing).' — add them to mukim_titik.json');
        }

        OsmGeometry::createTemp();
        DB::statement(<<<'SQL'
            INSERT INTO tmp_ref_sempadan (kod, nama, osm_id, g)
            SELECT t.kod, t.nama, NULL, ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Intersection(c.cell, d.g)), 3))
            FROM tmp_ref_daerah d
            CROSS JOIN LATERAL (
                SELECT (ST_Dump(ST_VoronoiPolygons(ST_Collect(t2.g), 0, ST_Expand(d.g, 0.5)))).geom AS cell
                FROM tmp_ref_titik t2 WHERE t2.daerah_kod = d.kod
            ) c
            JOIN tmp_ref_titik t ON t.daerah_kod = d.kod AND ST_Contains(c.cell, t.g)
        SQL);

        $seeds = collect(DB::select('SELECT kod, asal FROM tmp_ref_titik'))->mapWithKeys(fn ($r) => [$r->kod => ['anggaran' => true, 'titik_asal' => $r->asal]])->all();
        $this->write('sempadan_mukim_anggaran.geojson', 'sempadan_mukim_anggaran', 'tempat', $client, $seeds,
            note: 'ANGGARAN: sel Voronoi daripada satu titik tempat OSM bagi setiap mukim, dipotong mengikut sempadan daerah. BUKAN sempadan mukim yang diwartakan.');
    }

    private function stepMasjid(OverpassClient $client): void
    {
        $data = $client->query('masjid', '[out:json][timeout:300];area["ISO3166-2"="MY-10"]["admin_level"="4"]->.s;nwr(area.s)["amenity"="place_of_worship"]["religion"="muslim"];out center tags;', $this->option('refresh'));

        // Only descriptive tags of the place itself — no contact details.
        $keep = ['name', 'name:ms', 'name:en', 'name:ar', 'alt_name', 'official_name', 'building', 'denomination', 'operator', 'wikidata', 'wikipedia'];
        $features = [];

        foreach ($data['elements'] as $el) {
            $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
            $lon = $el['lon'] ?? $el['center']['lon'] ?? null;
            if ($lat === null || $lon === null) {
                continue;
            }
            $tags = $el['tags'] ?? [];
            $name = $tags['name'] ?? $tags['name:ms'] ?? null;
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'osm_jenis' => $el['type'],
                    'osm_id' => $el['id'],
                    'nama' => $name,
                    'jenis' => self::classify($name, $tags),
                    'tag' => array_intersect_key($tags, array_flip($keep)) ?: null,
                ],
                'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $lon, 6), round((float) $lat, 6)]],
            ];
        }

        usort($features, fn ($a, $b) => [$a['properties']['osm_jenis'], $a['properties']['osm_id']] <=> [$b['properties']['osm_jenis'], $b['properties']['osm_id']]);
        RefData::write('tempat_ibadat.geojson', ['type' => 'FeatureCollection', 'features' => $features]);
        RefData::manifest('tempat_ibadat', $this->manifestEntry($client, 'masjid', count($features), 'Overpass: nwr(area MY-10)[amenity=place_of_worship][religion=muslim]; out center tags'));
    }

    /**
     * @param  array<string, string>  $tags
     */
    public static function classify(?string $name, array $tags): string
    {
        $n = strtolower((string) $name);
        if (preg_match('/\b(surau|musolla|musalla|musholla|madrasah)\b/', $n)) {
            return 'surau';
        }
        if (preg_match('/\b(masjid|mosque)\b/', $n) || ($tags['building'] ?? null) === 'mosque') {
            return 'masjid';
        }

        return 'lain';
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     */
    private function write(string $file, string $dataset, string $cacheName, OverpassClient $client, array $properties = [], ?string $note = null): void
    {
        $tolerance = $this->tolerance();
        $props = [];
        foreach (DB::select('SELECT kod FROM tmp_ref_sempadan') as $row) {
            $props[$row->kod] = array_merge(['toleransi_permudah' => $tolerance], $properties[$row->kod] ?? []);
        }

        $fc = OsmGeometry::featureCollection($props);
        RefData::write($file, $fc);
        RefData::manifest($dataset, $this->manifestEntry($client, $cacheName, count($fc['features']), "Overpass cache: {$cacheName}.json; simplified {$tolerance}°", $note));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestEntry(OverpassClient $client, string $cacheName, int $count, string $method, ?string $note = null): array
    {
        return array_filter([
            'sumber' => 'OpenStreetMap melalui Overpass API',
            'sumber_url' => self::OVERPASS,
            'lesen' => self::LICENCE,
            'atribusi' => self::OSM_URL,
            'tarikh_data_osm' => $client->timestamp($cacheName),
            'tarikh_proses' => now()->toDateString(),
            'bilangan' => $count,
            'kaedah' => $method,
            'catatan' => $note,
        ], fn ($v) => $v !== null);
    }
}
