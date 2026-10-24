<?php

namespace Tests\Modules\Ref;

use App\Modules\Ref\Support\RefData;
use PHPUnit\Framework\TestCase;

/**
 * The committed data files: provenance recorded, ODbL attribution present,
 * counts honest, size within budget, no personal data.
 */
class ReferenceDataFilesTest extends TestCase
{
    public function test_SRS_DAT_08_manifest_records_source_licence_date_and_count_for_every_osm_dataset(): void
    {
        $manifest = RefData::json('manifest.json');
        $files = [
            'sempadan_negeri' => 'sempadan_negeri.geojson',
            'sempadan_daerah' => 'sempadan_daerah.geojson',
            'sempadan_pbt' => 'sempadan_pbt.geojson',
            'sempadan_mukim_anggaran' => 'sempadan_mukim_anggaran.geojson',
            'tempat_ibadat' => 'tempat_ibadat.geojson',
        ];

        foreach ($files as $dataset => $file) {
            $this->assertArrayHasKey($dataset, $manifest);
            $entry = $manifest[$dataset];
            foreach (['sumber', 'sumber_url', 'lesen', 'tarikh_data_osm', 'tarikh_proses', 'bilangan'] as $key) {
                $this->assertArrayHasKey($key, $entry, "{$dataset}.{$key}");
            }
            $this->assertStringContainsString('ODbL', $entry['lesen']);
            $this->assertCount($entry['bilangan'], RefData::json($file)['features'], $dataset);
        }

        $md = (string) file_get_contents(RefData::path('MANIFEST.md'));
        $this->assertStringContainsString('© OpenStreetMap contributors', $md);
    }

    public function test_every_code_row_names_its_source(): void
    {
        foreach (['negeri.json', 'daerah.json', 'mukim.json'] as $file) {
            foreach (RefData::json($file) as $row) {
                $this->assertNotEmpty($row['sumber'], "{$file} {$row['kod']}");
            }
        }
        foreach (RefData::json('senarai.json') as $list => $def) {
            $this->assertNotEmpty($def['sumber'], $list);
        }
    }

    public function test_committed_data_stays_small_and_carries_no_contact_details(): void
    {
        $bytes = 0;
        foreach (glob(RefData::path('*')) ?: [] as $file) {
            $bytes += filesize($file);
        }
        $this->assertLessThan(10 * 1024 * 1024, $bytes);

        $poi = (string) file_get_contents(RefData::path('tempat_ibadat.geojson'));
        foreach (['addr:', 'phone', 'contact:', 'email'] as $tag) {
            $this->assertStringNotContainsString('"'.$tag, $poi);
        }
    }
}
