<?php

namespace Tests\Modules\Hkm;

use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Hkm\Models\SekatanKepentingan;
use App\Modules\Hkm\Profile\HakMilikSection;
use App\Modules\Hkm\Profile\RisikoHakMilikSection;
use App\Modules\Hkm\Services\HakMilikSearchProvider;
use App\Modules\Hkm\Services\VaultService;

/** SRS-HKM-11/14/15/20 — search + export, global search, 360° profile sections, DMS links, colour legend. */
class ProfileAndSearchTest extends HkmTestCase
{
    /** @return array<string, string> ids keyed by label */
    private function fixtures(): array
    {
        $a = $this->title(['no_hak_milik' => 'GRN 1001', 'jenis_hak_milik' => 'GRN', 'kategori_tanah' => 'BANGUNAN', 'keluasan' => '500', 'pemilik_berdaftar' => 'Majlis Agama Islam Selangor', 'syarat_nyata' => 'Masjid'], $this->lot(['no_lot' => 'Lot 11', 'bahagian_pemilik' => 'wakaf']));
        $b = $this->title(['no_hak_milik' => 'PN 2002', 'jenis_hak_milik' => 'PN', 'kategori_tanah' => 'PERTANIAN', 'keluasan' => '25000', 'tarikh_tamat_tempoh' => '2090-01-01', 'pemilik_berdaftar' => 'Baitulmal MAIS'], $this->lot(['no_lot' => 'Lot 22', 'bahagian_pemilik' => 'baitulmal', 'kod_mukim' => '100901']));
        $c = $this->title(['no_hak_milik' => 'HS(D) 3003', 'jenis_hak_milik' => 'HSD', 'kategori_tanah' => 'BANGUNAN', 'keluasan' => '800', 'tarikh_tamat_tempoh' => now()->addDays(60)->toDateString()], $this->lot(['no_lot' => 'PT 33', 'bahagian_pemilik' => 'hartanah']));

        return ['a' => $a->id, 'b' => $b->id, 'c' => $c->id];
    }

    /** @return list<string> */
    private function search(array $query): array
    {
        return collect($this->actingAs($this->user())->getJson('/api/v1/hkm/hak-milik?'.http_build_query($query))->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    /** Acceptance criterion 5: all nine A-5.06 criteria, singly and combined, exportable. */
    public function test_SRS_HKM_14_search_by_each_of_the_nine_criteria(): void
    {
        $id = $this->fixtures();
        $sorted = fn (string ...$ids) => collect($ids)->sort()->values()->all();

        $this->assertSame([$id['a']], $this->search(['no_hak_milik' => 'grn1001']));            // 1 title number
        $this->assertSame([$id['b']], $this->search(['no_lot' => 'lot 22']));                    // 2 lot number
        $this->assertSame([$id['b']], $this->search(['kod_mukim' => '100901']));                 // 3 mukim
        $this->assertSame($sorted($id['a'], $id['c']), $this->search(['kod_daerah' => '1008'])); // 4 daerah
        $this->assertSame([$id['b']], $this->search(['jenis_tanah' => 'baitulmal']));            // 5 jenis tanah (wakaf/baitulmal/hartanah)
        $this->assertSame([$id['c']], $this->search(['jenis_hak_milik' => 'HSD']));              //   …and title type
        $this->assertSame([$id['b']], $this->search(['kategori_tanah' => 'PERTANIAN']));         // 6 land-use category
        $this->assertSame($sorted($id['a'], $id['b'], $id['c']), $this->search(['status' => 'aktif'])); // 7 status
        $this->assertSame([$id['b']], $this->search(['keluasan_min' => 10000]));                 // 8 area
        $this->assertSame([$id['a']], $this->search(['q' => 'masjid']));                         // 9 keyword
    }

    public function test_SRS_HKM_14_combined_criteria_and_export(): void
    {
        $id = $this->fixtures();
        $this->assertSame([$id['c']], $this->search(['kod_daerah' => '1008', 'kategori_tanah' => 'BANGUNAN', 'keluasan_min' => 600]));
        $this->assertSame([], $this->search(['kod_daerah' => '1009', 'jenis_hak_milik' => 'GRN']));
        $this->assertSame([$id['c']], $this->search(['tamat_dalam_hari' => 90]));

        $user = $this->user();
        $csv = (string) $this->actingAs($user)->get('/api/v1/hkm/eksport/hak-milik?format=csv&kod_daerah=1008')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringContainsString('GRN 1001', $csv);
        $this->assertStringContainsString('HS(D) 3003', $csv);
        $this->assertStringNotContainsString('PN 2002', $csv);
        $this->actingAs($user)->get('/api/v1/hkm/eksport/hak-milik?format=xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertAudited('eksport', ['modul' => 'hkm', 'nama_entiti' => 'hak_milik', 'id_pengguna' => $user->id]);

        $this->actingAs($this->user(['hkm.hak_milik.lihat']))->get('/api/v1/hkm/eksport/hak-milik')->assertForbidden();
    }

    public function test_SRS_HKM_14_global_search_provider_respects_scope(): void
    {
        $id = $this->fixtures();
        $provider = app(HakMilikSearchProvider::class);

        $this->assertSame('hak_milik', $provider->entity());
        $hits = $provider->search('2002', $this->user());
        $this->assertSame($id['b'], $hits[0]['id']);
        $this->assertSame('/admin/hkm/hak-milik/'.$id['b'], $hits[0]['link']);
        $this->assertSame([], $provider->search('2002', $this->user(self::PENDAFTAR, ['1008'])));
        $this->assertSame([], $provider->search('2002', $this->user(['dms.dokumen.lihat'])));
        $this->assertSame($id['a'], $provider->searchWithFilters('', ['no_lot' => 'Lot 11'], $this->user())[0]['id']);
        $this->assertNull($provider->link($id['b'], $this->user(self::PENDAFTAR, ['1008'])));
        $this->assertNull($provider->link('bukan-uuid', $this->user()));
    }

    /** Acceptance criterion 6 (HKM's part): the 360° profile gets title, vault location, risk flags and timeline. */
    public function test_SRS_HKM_15_profile_sections_show_title_risks_and_timeline(): void
    {
        $custodian = $this->user(self::PENJAGA);
        $lotId = $this->lot();
        $title = $this->title(['no_hak_milik' => 'GRN 9', 'tarikh_pendaftaran' => '1999-01-01'], $lotId);
        app(VaultService::class)->deposit($title, ['peti_besi_id' => $this->vault($custodian)->id, 'lokasi_rak' => 'R1', 'no_fail' => 'F9'], $custodian);
        SekatanKepentingan::query()->create(['hak_milik_id' => $title->id, 'jenis_sekatan' => 'gadaian', 'no_rujukan_sekatan' => 'GD 1/2020', 'status_sekatan' => 'aktif']);
        $viewer = $this->user(['hkm.hak_milik.lihat']);

        $identity = app(HakMilikSection::class);
        $this->assertSame('identiti', $identity->tab());
        $block = $identity->forLot($lotId, $viewer);
        $this->assertSame('GRN 9', $block['items'][0]['no_hak_milik']);
        $this->assertSame('PB-01 · R1 · F9', $block['items'][0]['lokasi_simpanan']);
        $this->assertSame('/admin/hkm/hak-milik/'.$title->id, $block['link']);

        $timeline = $identity->timeline($lotId, $viewer);
        $this->assertSame(['hkm.pemilikan', 'hkm.status'], collect($timeline)->pluck('type')->sort()->values()->all());
        $this->assertSame('1999-01-01', $timeline[0]['date']);

        $risk = app(RisikoHakMilikSection::class);
        $this->assertSame('risiko', $risk->tab());
        $riskBlock = $risk->forLot($lotId, $viewer);
        $this->assertSame('gadaian', $riskBlock['items'][0]['jenis_sekatan']);
        $this->assertStringContainsString('Gadaian', collect($riskBlock['flags'])->pluck('label')->implode(' '));

        // Nothing for users without permission or out of scope.
        $this->assertNull($identity->forLot($lotId, $this->user(['dms.dokumen.lihat'])));
        $this->assertNull($risk->forLot($lotId, $this->user(self::PENDAFTAR, ['1009'])));
        $this->assertSame([], $identity->timeline($lotId, $this->user(self::PENDAFTAR, ['1009'])));
    }

    /** SRS-HKM-11: supporting documents through DMS; documents drill back to the title. */
    public function test_SRS_HKM_11_dms_record_link_points_back_to_the_title(): void
    {
        $title = $this->title();
        $links = app(RecordLinks::class);

        $this->assertSame(['label' => 'Hak Milik', 'link' => '/admin/hkm/hak-milik/'.$title->id], $links->resolve('hak_milik', $title->id, $this->user(['hkm.hak_milik.lihat'])));
        $this->assertNull($links->resolve('hak_milik', $title->id, $this->user(['dms.dokumen.lihat']))['link']);
    }

    /** SRS-HKM-20: one colour legend per title type and land-use category, shared with GIS and reports. */
    public function test_SRS_HKM_20_colour_legend_covers_every_reference_code(): void
    {
        $legend = (string) file_get_contents(base_path('frontend/lib/legend.ts'));
        $ref = json_decode((string) file_get_contents(base_path('app/Modules/Ref/Database/data/senarai.json')), true);

        foreach ($ref['ref_jenis_hak_milik']['rows'] as $row) {
            $this->assertMatchesRegularExpression('/\b'.preg_quote($row['kod'], '/').':\s*\{/', $legend, "legend.ts has no colour for jenis hak milik {$row['kod']}");
        }
        foreach ($ref['ref_kategori_guna_tanah']['rows'] as $row) {
            $this->assertMatchesRegularExpression('/\b'.preg_quote($row['kod'], '/').':\s*\{/', $legend, "legend.ts has no colour for kategori {$row['kod']}");
        }
        preg_match_all('/#[0-9A-Fa-f]{6}\b/', $legend, $m);
        $this->assertNotEmpty($m[0]);
    }
}
