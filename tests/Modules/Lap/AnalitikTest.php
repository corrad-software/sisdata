<?php

namespace Tests\Modules\Lap;

use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Lap\Gis\AnalitikLayer;
use App\Modules\Lap\Models\CadanganAi;
use App\Modules\Lap\Models\HasilAnalitik;
use App\Modules\Lap\Models\InsightEksekutif;
use App\Modules\Lap\Services\Analitik;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** SRS-LAP-15..21: analytics, recommendations, executive insight. */
class AnalitikTest extends LapTestCase
{
    public const PENGANALISIS = [...self::PEMBACA, 'lap.analitik.lihat', 'lap.analitik.kemas_kini', 'lap.analitik.lulus', 'lap.insight.lihat'];

    private function visit(string $lotId, string $keadaan, string $date): void
    {
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        DB::table('rekod_lawatan')->insert(['id' => (string) Str::uuid(), 'no_rujukan' => 'LW-'.random_int(1000, 99999), 'lot_id' => $lotId, 'kod_daerah' => $lot->kod_daerah,
            'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => 'dalaman', 'tarikh_lawatan' => $date, 'keadaan_tapak' => $keadaan, 'sintetik' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_SRS_LAP_16_arrears_risk_scores_history_age_and_balance(): void
    {
        $bad = $this->lot(['kod_mukim' => '100803', 'no_lot' => 'Lot Lewat']);
        $this->tax($bad, ['jumlah' => 20000, 'tarikh_akhir' => now()->subDays(400)->toDateString(), 'amaun_denda' => 100]);
        $mild = $this->lot(['kod_mukim' => '100901', 'no_lot' => 'Lot Baru']);
        $this->tax($mild, ['jumlah' => 100, 'tarikh_akhir' => now()->subDays(10)->toDateString()]);
        $this->tax($mild, ['jumlah' => 100, 'tarikh_akhir' => now()->subYear()->toDateString(), 'tarikh_bayar' => now()->subYear()->toDateString(), 'status_bayaran' => 'dibayar', 'amaun_dibayar' => 100]);

        $counts = app(Analitik::class)->run(['risiko_tunggakan']);
        $this->assertSame(2, $counts['risiko_tunggakan']);
        $h = HasilAnalitik::query()->withoutGlobalScope('access')->get()->keyBy('no_lot');
        $this->assertSame('kritikal', $h['Lot Lewat']->aras_risiko);
        $this->assertSame('rendah', $h['Lot Baru']->aras_risiko);
        $this->assertSame('20100.00', $h['Lot Lewat']->faktor['baki_tertunggak']);
        $this->assertSame(1, CadanganAi::query()->withoutGlobalScope('access')->count(), 'only high / critical results produce recommendations');

        $list = $this->actingAs($this->user(self::PENGANALISIS))->getJson('/api/v1/lap/analitik?jenis=risiko_tunggakan')->assertOk();
        $this->assertSame(['Lot Lewat', 'Lot Baru'], array_column($list->json('data'), 'no_lot'), 'ranked by score');
        $this->assertStringContainsString('tidak menggantikan keputusan rasmi MAIS', $list->json('label_cadangan'));

        // Scoped to the reader's district.
        $this->assertSame(['Lot Baru'], array_column($this->actingAs($this->user(self::PENGANALISIS, ['1009']))->getJson('/api/v1/lap/analitik')->json('data'), 'no_lot'));

        // Rerun replaces the current set without duplicating open recommendations.
        app(Analitik::class)->run(['risiko_tunggakan']);
        $this->assertSame(2, HasilAnalitik::query()->withoutGlobalScope('access')->where('semasa', true)->count());
        $this->assertSame(1, CadanganAi::query()->withoutGlobalScope('access')->count());
    }

    public function test_SRS_LAP_17_idle_land_from_lease_status_and_monitoring(): void
    {
        $idle = $this->lot(['no_lot' => 'Lot Terbiar', 'kategori_guna_tanah' => 'PERTANIAN']);
        $this->visit($idle, 'terbiar', now()->subMonths(2)->toDateString());
        $leased = $this->lot(['no_lot' => 'Lot Disewa']);
        $this->contract($leased);
        app(Analitik::class)->run(['tanah_tidak_produktif']);

        $h = HasilAnalitik::query()->withoutGlobalScope('access')->where('jenis_analitik', 'tanah_tidak_produktif')->get()->keyBy('no_lot');
        $this->assertArrayNotHasKey('Lot Disewa', $h->all());
        $this->assertSame('tinggi', $h['Lot Terbiar']->aras_risiko, '40 no lease + 30 site idle');
        $this->assertSame('terbiar', $h['Lot Terbiar']->faktor['keadaan_tapak']);
        $c = CadanganAi::query()->withoutGlobalScope('access')->where('id_lot', $idle)->sole();
        $this->assertSame('jadual_lawatan_pemantauan', $c->jenis_tindakan);
    }

    public function test_SRS_LAP_15_revenue_potential_hides_valuation_derived_money_without_rahsia(): void
    {
        $lot = $this->lot(['no_lot' => 'Lot Komersial', 'kategori_guna_tanah' => 'BANGUNAN']);
        DB::table('penilaian')->insert(['id' => (string) Str::uuid(), 'lot_id' => $lot, 'tarikh_penilaian' => '2025-01-01', 'nilai_pasaran' => 3000000, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);
        app(Analitik::class)->run(['potensi_hasil']);

        $h = HasilAnalitik::query()->withoutGlobalScope('access')->where('jenis_analitik', 'potensi_hasil')->sole();
        $this->assertSame('150000.00', $h->faktor['anggaran_hasil_tahunan'], '5% of RM3,000,000 (bcmath)');
        $this->assertSame('kritikal', $h->aras_risiko);

        $plain = $this->actingAs($this->user(self::PENGANALISIS))->getJson('/api/v1/lap/analitik?jenis=potensi_hasil')->json('data.0.faktor');
        $this->assertArrayNotHasKey('anggaran_hasil_tahunan', $plain);
        $full = $this->actingAs($this->user([...self::PENGANALISIS, 'pro.rahsia.lihat']))->getJson('/api/v1/lap/analitik?jenis=potensi_hasil')->json('data.0.faktor');
        $this->assertSame('150000.00', $full['anggaran_hasil_tahunan']);
    }

    public function test_SRS_LAP_18_development_potential_is_published_as_a_scoped_gis_layer(): void
    {
        $this->lot(['no_lot' => 'Lot Besar', 'keluasan' => 3, 'unit_keluasan' => 'hektar', 'kod_mukim' => '100803']);
        $this->lot(['no_lot' => 'Lot Kecil', 'keluasan' => 100, 'kod_mukim' => '100803']);
        $this->lot(['no_lot' => 'Lot Sepang', 'keluasan' => 10000, 'kod_mukim' => '100901']);
        app(Analitik::class)->run(['potensi_pembangunan']);
        $this->assertEqualsCanonicalizing(['Lot Besar', 'Lot Sepang'], HasilAnalitik::query()->withoutGlobalScope('access')->pluck('no_lot')->all());

        app(MapLayers::class);
        $layer = app(AnalitikLayer::class);
        $this->assertSame('lap.analitik', $layer->key());
        $state = $layer->features($this->user(self::PENGANALISIS), null, ['jenis' => 'potensi_pembangunan']);
        $this->assertCount(2, $state['features']);
        $district = $layer->features($this->user(self::PENGANALISIS, ['1008']), null, ['jenis' => 'potensi_pembangunan']);
        $this->assertSame(['Lot Besar — Potensi pembangunan'], array_map(fn ($f) => $f['properties']['label'], $district['features']));
        $this->assertSame([], $layer->features($this->user(self::PEMBACA), null, [])['features'], 'needs lap.analitik.lihat');
    }

    public function test_SRS_LAP_19_recommendation_is_accepted_or_rejected_with_justification_and_audited(): void
    {
        $lot = $this->lot();
        $this->tax($lot, ['jumlah' => 50000, 'tarikh_akhir' => now()->subDays(500)->toDateString()]);
        $this->actingAs($this->user(self::PENGANALISIS))->postJson('/api/v1/lap/analitik/jalankan', ['jenis' => ['risiko_tunggakan']])->assertOk();
        $rec = $this->actingAs($this->user(self::PENGANALISIS))->getJson('/api/v1/lap/cadangan')->assertOk()->json('data.0');
        $this->assertSame('notis_tuntutan_tunggakan', $rec['tindakan']);

        $officer = $this->user(self::PENGANALISIS);
        $this->actingAs($officer)->putJson("/api/v1/lap/cadangan/{$rec['id']}/keputusan", ['keputusan' => 'diterima'])->assertStatus(422);
        $this->actingAs($this->user([...self::PEMBACA, 'lap.analitik.lihat']))->putJson("/api/v1/lap/cadangan/{$rec['id']}/keputusan", ['keputusan' => 'diterima', 'justifikasi' => 'Notis akan dikeluarkan minggu ini.'])->assertForbidden();
        $this->actingAs($this->user(self::PENGANALISIS, ['1009']))->putJson("/api/v1/lap/cadangan/{$rec['id']}/keputusan", ['keputusan' => 'ditolak', 'justifikasi' => 'Bukan dalam daerah saya.'])->assertNotFound();

        $this->actingAs($officer)->putJson("/api/v1/lap/cadangan/{$rec['id']}/keputusan", ['keputusan' => 'ditolak', 'justifikasi' => 'Lot dalam proses pelupusan; tiada tindakan.'])->assertOk();
        $c = CadanganAi::query()->withoutGlobalScope('access')->findOrFail($rec['id']);
        $this->assertSame('ditolak', $c->status_keputusan);
        $this->assertSame($officer->id, $c->id_pegawai);
        $this->assertAudited('tolak', ['modul' => 'lap', 'nama_entiti' => 'lap_cadangan_ai', 'id_rekod' => $c->id]);
        $this->actingAs($officer)->putJson("/api/v1/lap/cadangan/{$rec['id']}/keputusan", ['keputusan' => 'diterima', 'justifikasi' => 'Ubah fikiran sekali lagi.'])->assertStatus(422);
    }

    public function test_SRS_LAP_20_executive_insight_is_generated_on_schedule_and_per_scope(): void
    {
        $k = $this->contract($this->lot(['kod_mukim' => '100803']), ['tarikh_tamat' => now()->addDays(30)->toDateString()]);
        $this->charge($k, now()->subDays(500)->toDateString(), '5000.00');
        $this->tax($this->lot(['kod_mukim' => '100901']), ['jumlah' => 700]);

        $this->artisan('lap:insight-eksekutif')->assertSuccessful();
        $i = InsightEksekutif::query()->whereNull('id_pengguna')->sole();
        $this->assertStringContainsString('RM 5,700.00', $i->isu_utama[0]['teks']);
        $this->assertNotEmpty($i->risiko);
        $this->assertNotEmpty($i->tindakan_segera);
        $this->assertSame('lap.insight_eksekutif', $this->notifier->sent[0]['type']);

        $this->artisan('lap:insight-eksekutif')->assertSuccessful();
        $this->assertSame(1, InsightEksekutif::query()->count(), 'not due again this month');

        // A district manager sees only their own scoped insight, never the state-wide one.
        $mgr = $this->user([...self::PEMBACA, 'lap.insight.lihat'], ['1009']);
        $this->assertSame([], $this->actingAs($mgr)->getJson('/api/v1/lap/insight')->assertOk()->json('data'));
        $this->actingAs($mgr)->postJson('/api/v1/lap/insight')->assertCreated();
        $mine = $this->actingAs($mgr)->getJson('/api/v1/lap/insight')->json('data');
        $this->assertCount(1, $mine);
        $this->assertStringContainsString('RM 700.00', $mine[0]['isu_utama'][0]['teks']);
        $this->assertCount(1, $this->actingAs($this->user([...self::PEMBACA, 'lap.insight.lihat']))->getJson('/api/v1/lap/insight')->json('data'));
    }

    public function test_SRS_LAP_21_analytics_and_ai_run_locally_and_are_labelled_system_suggestions(): void
    {
        Http::fake();
        $this->lot();
        app(Analitik::class)->run();
        $user = $this->user([...self::PENGANALISIS]);
        $this->actingAs($user)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'bilangan aduan mengikut daerah tahun ini'])->assertOk();
        $this->actingAs($user)->getJson('/api/v1/lap/carian?q=tanah wakaf Petaling')->assertOk();
        Http::assertNothingSent();

        $this->assertStringContainsString('tidak menggantikan keputusan rasmi MAIS', $this->actingAs($user)->getJson('/api/v1/lap/analitik/ringkasan')->json('label_cadangan'));
        $this->assertStringContainsString('tidak menggantikan keputusan rasmi MAIS', $this->jana($user, ['katalog' => 'RIS-04'])['label_cadangan']);
        $this->assertNull($this->jana($user, ['katalog' => 'POR-01'])['label_cadangan']);
    }
}
