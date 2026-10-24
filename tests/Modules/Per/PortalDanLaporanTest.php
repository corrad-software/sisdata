<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Integration\MockWakafPortal;
use App\Modules\Per\Integration\PemerolehanProfileSection;
use App\Modules\Per\Integration\PemerolehanSearchProvider;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\Saluran;
use Illuminate\Support\Facades\DB;

class PortalDanLaporanTest extends PerTestCase
{
    /** Kriteria penerimaan 8. */
    public function test_SRS_PER_25_portal_submission_lands_as_pending_wakaf_case_without_touching_master(): void
    {
        $portal = $this->officer(['per.portal_wakaf.cipta']);
        $lotsBefore = DB::table('lot_tanah')->count();
        $lotId = $this->lot('PT 5151', '100803');
        $lotRow = (array) DB::table('lot_tanah')->where('id', $lotId)->first();

        $payload = ['rujukan_portal' => 'PHWS-2026-0001', 'nama_pewakaf' => 'Pewakaf Awam Sintetik', 'no_pengenalan' => '700101-10-1234',
            'jenis_wakaf' => 'am', 'tujuan_wakaf' => 'Tapak surau', 'no_lot' => 'PT 9898', 'kod_mukim' => '100803', 'nilai_anggaran' => 300000];
        $this->actingAs($portal)->postJson('/api/v1/per/portal-wakaf/permohonan', $payload)->assertCreated()
            ->assertJsonPath('data.status', Pemerolehan::MENUNGGU_SEMAKAN);
        // Idempotent per portal reference.
        $this->actingAs($portal)->postJson('/api/v1/per/portal-wakaf/permohonan', $payload)->assertOk();

        $case = Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', 'PHWS-2026-0001')->sole();
        $this->assertSame('wakaf', $case->kodSaluran());
        $this->assertSame('portal_wakaf', $case->sumber_rekod);
        $this->assertNull($case->lot_id);
        $this->assertNull($case->peringkat_semasa);
        $this->assertNull($case->id_pendaftar);
        $this->assertSame('pewakaf', $case->pihak()->value('peranan_pihak'));
        $this->assertSame($lotsBefore + 1, DB::table('lot_tanah')->count(), 'Tiada rekod lot induk dicipta oleh portal.');
        $this->assertEquals($lotRow, (array) DB::table('lot_tanah')->where('id', $lotId)->first());

        $this->actingAs($this->officer(self::PEGAWAI))->postJson('/api/v1/per/portal-wakaf/permohonan', $payload + ['rujukan_portal' => 'X'])->assertForbidden();
    }

    public function test_SRS_PER_25_pending_public_case_cannot_progress_until_accepted(): void
    {
        $portal = $this->officer(['per.portal_wakaf.cipta']);
        $this->actingAs($portal)->postJson('/api/v1/per/portal-wakaf/permohonan', ['rujukan_portal' => 'PHWS-2', 'nama_pewakaf' => 'A Sintetik'])->assertCreated();
        $case = Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', 'PHWS-2')->sole();

        $officer = $this->officer();
        $this->advance($officer, $case)->assertStatus(409)->assertJsonPath('message', __('per::per.perlu_semakan_awam'));
        $this->actingAs($officer)->postJson("/api/v1/per/kes/{$case->id}/semakan-awam", ['terima' => true])->assertOk()
            ->assertJsonPath('data.status', Pemerolehan::DALAM_PROSES)
            ->assertJsonPath('data.peringkat_semasa.kod', 'penerimaan_ikrar');
        $this->assertSame($officer->id, $case->refresh()->id_pendaftar);
        $this->assertAudited('sah', ['modul' => 'per', 'id_rekod' => $case->id]);

        $this->actingAs($portal)->postJson('/api/v1/per/portal-wakaf/permohonan', ['rujukan_portal' => 'PHWS-3', 'nama_pewakaf' => 'B Sintetik'])->assertCreated();
        $rejected = Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', 'PHWS-3')->sole();
        $this->actingAs($officer)->postJson("/api/v1/per/kes/{$rejected->id}/semakan-awam", ['terima' => false])->assertStatus(422);
        $this->actingAs($officer)->postJson("/api/v1/per/kes/{$rejected->id}/semakan-awam", ['terima' => false, 'catatan' => 'Bukan tanah milik pemohon'])->assertOk()
            ->assertJsonPath('data.status', Pemerolehan::DIBATALKAN);
    }

    public function test_SRS_PER_12_portal_adapter_pull_creates_pending_cases(): void
    {
        MockWakafPortal::queue(['rujukan_portal' => 'PHWS-PULL-1', 'nama_pewakaf' => 'Tarik Sintetik', 'jenis_wakaf' => 'khas']);
        $this->artisan('per:portal-wakaf --contoh=2')->expectsOutputToContain('3 permohonan wakaf baharu')->assertSuccessful();
        $this->assertSame(3, Pemerolehan::query()->withoutGlobalScope('access')->where('status_pemerolehan', Pemerolehan::MENUNGGU_SEMAKAN)->count());
        $this->assertSame(1, DB::table('adm_log_integrasi')->where('kod_integrasi', 'portal_wakaf')->count());
        $this->artisan('per:portal-wakaf')->expectsOutputToContain('0 permohonan')->assertSuccessful();
    }

    /** Kriteria penerimaan 7. */
    public function test_SRS_PER_27_report_by_channel_district_period_and_status_matches_database(): void
    {
        $user = $this->officer();
        $this->newCase($user, 'wakaf', ['lot_id' => $this->lot('PT 1', '100803'), 'nilai_pemerolehan' => 100000, 'tarikh_mula_kes' => '2025-03-01']);
        $this->newCase($user, 'wakaf', ['lot_id' => $this->lot('PT 2', '100101'), 'nilai_pemerolehan' => 50000, 'tarikh_mula_kes' => '2026-02-01']);
        $this->newCase($user, 'faraid', ['lot_id' => $this->lot('PT 3', '100803'), 'nilai_pemerolehan' => 20000, 'tarikh_mula_kes' => '2026-03-01', 'sumber_dana' => 'Dana Baitulmal']);
        $cancel = $this->newCase($this->officer([...self::PEGAWAI, 'per.kes.padam']), 'tanah_warta', ['lot_id' => $this->lot('PT 4', '100803'), 'tarikh_mula_kes' => '2026-04-01']);
        $this->actingAs($this->officer([...self::PEGAWAI, 'per.kes.padam']))->postJson("/api/v1/per/kes/{$cancel->id}/batal", ['sebab' => 'Batal ujian laporan'])->assertOk();

        $viewer = $this->officer(['per.laporan.lihat']);
        $all = $this->actingAs($viewer)->getJson('/api/v1/per/laporan')->assertOk()->json('data');
        $this->assertSame(DB::table('pemerolehan')->whereNull('deleted_at')->count(), $all['jumlah']['kes']);
        $this->assertEquals((float) DB::table('pemerolehan')->sum('nilai_pemerolehan'), $all['jumlah']['nilai']);
        $byChannel = collect($all['mengikut_saluran'])->keyBy('kod');
        $this->assertCount(11, $byChannel);
        $this->assertSame(2, $byChannel['wakaf']['bilangan']);
        $this->assertEquals(150000, $byChannel['wakaf']['nilai']);
        $this->assertSame(Saluran::byCode('wakaf')->warna, $byChannel['wakaf']['warna']);
        $this->assertSame(3, collect($all['mengikut_daerah'])->firstWhere('kod', '1008')['bilangan']);
        $this->assertSame(1, collect($all['mengikut_status'])->firstWhere('status', 'dibatalkan')['bilangan']);
        $this->assertSame([2025, 2026], array_column($all['mengikut_tahun'], 'tahun'));
        $this->assertSame('Dana Baitulmal', collect($all['mengikut_sumber_dana'])->firstWhere('sumber_dana', 'Dana Baitulmal')['sumber_dana']);

        $filtered = $this->actingAs($viewer)->getJson('/api/v1/per/laporan?saluran=wakaf&daerah=1008&dari=2025-01-01&hingga=2025-12-31&status=dalam_proses')->assertOk()->json('data');
        $this->assertSame(1, $filtered['jumlah']['kes']);
        $this->assertEquals(100000, $filtered['jumlah']['nilai']);

        $list = $this->actingAs($user)->getJson('/api/v1/per/kes?saluran=faraid&daerah=1008')->assertOk();
        $this->assertSame(1, $list->json('meta.total'));
        $this->assertSame('faraid', $list->json('data.0.saluran.kod'));
        $this->assertSame('#7C3AED', $list->json('data.0.saluran.warna'));

        $this->actingAs($viewer)->getJson('/api/v1/per/laporan?hingga=2020-01-01&dari=2021-01-01')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/per/laporan')->assertForbidden();
    }

    public function test_SRS_PER_27_special_lists_and_scope(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'tanah_warta', ['lot_id' => $this->lot('PT 7', '100101')]);
        $viewer = $this->officer(['per.laporan.lihat']);
        $missing = $this->actingAs($viewer)->getJson('/api/v1/per/laporan/khas/dokumen_kurang')->assertOk()->json('data');
        $this->assertSame($case->no_kes, $missing[0]['no_kes']);
        $this->assertContains('Petikan Warta Kerajaan Negeri Selangor', $missing[0]['kekurangan']);
        $this->actingAs($viewer)->getJson('/api/v1/per/laporan/khas/tiada')->assertNotFound();

        $scoped = $this->officer(['per.laporan.lihat'], ['daerah' => ['1008']]);
        $this->assertSame(0, $this->actingAs($scoped)->getJson('/api/v1/per/laporan')->json('data.jumlah.kes'));
        $this->assertSame([], $this->actingAs($scoped)->getJson('/api/v1/per/laporan/khas/dokumen_kurang')->json('data'));
    }

    public function test_SRS_PER_27_export_csv_is_audited(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'wakaf');
        $exporter = $this->officer(['per.laporan.eksport']);
        $response = $this->actingAs($exporter)->get('/api/v1/per/laporan/eksport?saluran=wakaf')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('No. Kes', $csv);
        $this->assertStringContainsString($case->no_kes, $csv);
        $this->assertStringContainsString('Dalam proses', $csv);
        $this->assertAudited('eksport', ['modul' => 'per', 'id_pengguna' => $exporter->id]);
        $this->actingAs($this->officer(['per.laporan.lihat']))->get('/api/v1/per/laporan/eksport', ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_SRS_PER_28_lot_profile_section_shows_history_with_channel_colour_and_timeline(): void
    {
        $user = $this->officer();
        $lotId = $this->lot('PT 360', '100803');
        $case = $this->newCase($user, 'wakaf', ['lot_id' => $lotId, 'tarikh_mula_kes' => '2026-01-05']);
        $section = app(PemerolehanProfileSection::class);

        $this->assertSame('pemerolehan', $section->key());
        $this->assertSame('identiti', $section->tab());
        $block = $section->forLot($lotId, $user);
        $this->assertNotNull($block);
        $this->assertSame($case->no_kes, $block['items'][0]['no_kes']);
        $this->assertSame('#15803D', $block['items'][0]['warna']);
        $this->assertSame('/admin/per/kes/'.$case->id, $block['items'][0]['link']);
        $this->assertNotEmpty($block['flags']);
        $events = $section->timeline($lotId, $user);
        $this->assertSame('2026-01-05', $events[0]['date']);

        $this->assertNull($section->forLot($lotId, $this->officer(['per.laporan.lihat'])));
        $this->assertNull($section->forLot($lotId, $this->officer(['per.kes.lihat'], ['daerah' => ['1001']])));
    }

    public function test_SRS_PER_01_sample_cases_command_covers_all_channels_and_stages(): void
    {
        $this->lot('PT 90000', '100803');
        $this->artisan('per:sample-cases --count=33 --seed=7')->assertSuccessful();

        $cases = Pemerolehan::query()->withoutGlobalScope('access')->with('saluran')->get();
        $this->assertCount(33, $cases);
        $this->assertCount(11, $cases->pluck('saluran.kod_saluran')->unique());
        $this->assertTrue($cases->every(fn ($c) => $c->sumber_rekod === 'sintetik'));
        $this->assertGreaterThan(3, $cases->pluck('peringkat_semasa')->unique()->count());
        $this->assertGreaterThan(0, $cases->where('status_pemerolehan', Pemerolehan::SELESAI)->count() + $cases->where('status_pemerolehan', Pemerolehan::DILULUSKAN)->count());
    }

    public function test_SRS_PER_28_global_search_finds_cases_within_scope(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'wakaf', ['tajuk' => 'Wakaf tapak madrasah Kg. Sintetik']);
        $provider = app(PemerolehanSearchProvider::class);
        $this->assertSame($case->id, $provider->search('madrasah', $user)[0]['id']);
        $this->assertSame([], $provider->search('madrasah', $this->officer(['per.kes.lihat'], ['daerah' => ['1001']])));
    }
}
