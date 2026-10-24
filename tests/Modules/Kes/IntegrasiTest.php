<?php

namespace Tests\Modules\Kes;

use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Dsh\Services\KpiRegistry;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Services\LayerRegistry;
use App\Modules\Kes\Contracts\CourtFilingSystem;
use App\Modules\Kes\Contracts\CourtSystemUnavailable;
use App\Modules\Kes\Contracts\LegalCases;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Risiko\IsyaratPerundangan;
use App\Modules\Kes\Services\KesLegalCaseIntake;
use App\Modules\Kes\Services\KesSearchProvider;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Services\KosService;
use App\Modules\Ptn\Contracts\LegalCaseIntake;
use App\Modules\Ptn\Services\DaftarIsyaratRisiko;
use Illuminate\Support\Facades\DB;

class IntegrasiTest extends KesTestCase
{
    public function test_SRS_KES_19_complaint_raised_to_a_case_carries_lots_documents_and_chronology(): void
    {
        $lots = [$this->lot(), $this->lot()];
        $by = $this->user();
        $cases = app(LegalCases::class);
        $id = $cases->open([
            'tajuk' => 'Aduan ADU-2026-000123 dinaikkan ke tindakan undang-undang', 'keterangan' => 'Pencerobohan berulang.', 'jenis' => 'pencerobohan',
            'lot_ids' => $lots, 'dokumen' => [['id_dokumen_dms' => 'dok-aduan-1', 'nama_fail' => 'gambar.jpg'], ['id_dokumen_dms' => 'dok-aduan-2']],
            'kronologi' => [['tarikh' => '2026-09-01 10:00:00', 'keterangan' => 'Aduan diterima'], ['tarikh' => '2026-09-05 15:00:00', 'keterangan' => 'Lawatan tapak: binaan haram disahkan']],
            'sumber' => 'adu',
        ], $by, 'aduan:123');
        $this->assertSame($id, $cases->open(['tajuk' => 'ulang', 'sumber' => 'adu'], $by, 'aduan:123'), 'idempotent on sourceRef');

        $k = KesMahkamah::query()->withoutGlobalScope('access')->findOrFail($id);
        $this->assertSame('adu', $k->sumber);
        $this->assertSame('didaftar', $k->status);
        $this->assertEqualsCanonicalizing($lots, DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $id)->pluck('lot_id')->all());
        $this->assertSame(2, DB::table('kes_dokumen')->where('kes_mahkamah_id', $id)->where('sumber', 'adu')->count());
        $this->assertSame(2, DB::table('kes_kronologi')->where('kes_mahkamah_id', $id)->where('jenis', 'aduan')->count());
        $this->assertSame(['dalam' => true], ['dalam' => $cases->underLegalAction([$lots[0]])[$lots[0]]]);
        $this->assertTrue($cases->exists($id));
        $this->assertFalse($cases->exists('bukan-uuid'));
        $this->assertSame('/admin/kes/kes/'.$id, $cases->summary($id, $this->user())['pautan'] ?? null);
        $this->assertNull($cases->summary($id, $this->user(['kes.kes.lihat'])), 'no Rahsia grant → nothing');
    }

    public function test_ptn_legal_case_intake_is_bound_to_mod_kes(): void
    {
        $intake = app(LegalCaseIntake::class);
        $this->assertInstanceOf(KesLegalCaseIntake::class, $intake);
        $this->assertTrue($intake->available());
        $lot = $this->lot();
        $by = $this->user(['ptn.isu.kemas_kini']);
        $id = $intake->open(['lot_id' => $lot, 'tajuk' => 'Isu pemantauan ISU-2026-000001', 'keterangan' => 'Pencerobohan dikesan', 'kategori' => 'pencerobohan'], $by, 'ptn_isu:1');
        $this->assertSame($id, $intake->open(['lot_id' => $lot, 'tajuk' => 'x', 'keterangan' => 'x', 'kategori' => 'pencerobohan'], $by, 'ptn_isu:1'));
        $k = KesMahkamah::query()->withoutGlobalScope('access')->findOrFail($id);
        $this->assertSame('ptn', $k->sumber);
        $this->assertSame('pencerobohan', $k->jenis);
    }

    public function test_ptn_risk_score_uses_the_legal_signal(): void
    {
        $this->assertSame(IsyaratPerundangan::class, app(DaftarIsyaratRisiko::class)->sumber()['pertikaian']);
        [$a, $b, $c] = [$this->lot(), $this->lot(), $this->lot()];
        $this->kes([$a], null, ['jenis' => 'sivil']);
        $this->kes([$b], null, ['jenis' => 'pencerobohan']);
        $this->kes([$b], null, ['jenis' => 'sivil']);
        $scores = app(IsyaratPerundangan::class)->scores([$a, $b, $c]);
        $this->assertSame(70.0, $scores[$a]['skor']);
        $this->assertSame(100.0, $scores[$b]['skor']);
        $this->assertArrayNotHasKey($c, $scores);
        $this->assertStringNotContainsString('BA-', $scores[$b]['butiran'] ?? '', 'no court numbers in risk details');
    }

    public function test_gis_layer_fills_the_kes_slot_as_rahsia_and_respects_access(): void
    {
        $layer = app(LayerRegistry::class)->get('kes.kes_mahkamah');
        $this->assertInstanceOf(ClassifiedMapLayer::class, $layer);
        $this->assertSame('rahsia', $layer->klasifikasi());
        $lot = $this->lot();
        $selesai = $this->lot();
        $this->kes([$lot]);
        app(KesService::class)->tukarStatus($this->kes([$selesai]), 'selesai', $this->user(KesTestCase::KETUA));

        $fc = $layer->features($this->user());
        $props = collect($fc['features'])->keyBy('id')->map(fn ($f) => $f['properties']);
        $this->assertSame('aktif', $props[$lot]['status_kes']);
        $this->assertSame('selesai', $props[$selesai]['status_kes']);
        $this->assertContains(collect($fc['features'])->first()['geometry']['type'], ['Polygon', 'MultiPolygon']);
        $this->assertSame([], $layer->features($this->user(['kes.kes.lihat']))['features']);
        $this->assertSame([], $layer->features($this->user(KesTestCase::PEGAWAI, ['1009']))['features'], 'district scope');
    }

    public function test_SRS_KES_22_kpis_registered_with_the_executive_dashboard(): void
    {
        $registry = app(KpiRegistry::class);
        foreach (['kes.kes_aktif', 'kes.prosiding_30_hari', 'kes.kos_guaman_tahun_ini', 'kes.kes_mengikut_jenis'] as $key) {
            $this->assertNotNull($registry->get($key), $key);
        }
        $k = $this->kes([$this->lot()]);
        Prosiding::query()->create(['kes_mahkamah_id' => $k->id, 'tarikh_prosiding' => now()->addDays(3), 'jenis_prosiding' => 'bicara', 'status' => 'dijadualkan']);
        app(KosService::class)->tambah($k, ['kategori_kos' => 'fi_peguam', 'amaun' => '1000.50', 'tarikh' => now()->toDateString()], $this->user());
        $u = $this->user();
        $this->assertSame(1, $registry->get('kes.kes_aktif')->compute($u)['nilai']);
        $this->assertSame(1, $registry->get('kes.prosiding_30_hari')->compute($u)['nilai']);
        $this->assertEqualsWithDelta(1000.50, $registry->get('kes.kos_guaman_tahun_ini')->compute($u)['nilai'], 0.001);
        $this->assertSame(1, $registry->get('kes.kes_mengikut_jenis')->compute($u)['pecahan'][0]['nilai']);
        $this->assertSame('kes.rahsia.lihat', $registry->get('kes.kes_aktif')->permission());
        $this->assertSame(0, $registry->get('kes.kes_aktif')->compute($this->user(KesTestCase::PEGAWAI, ['1009']))['nilai']);
        $provider = $registry->get('kes.prosiding_30_hari');
        $this->assertInstanceOf(KpiDrillDown::class, $provider);
        $drill = $provider->records($u, [], null);
        $this->assertSame('/admin/kes/kes/'.$k->id.'?tab=prosiding', $drill['baris'][0]['pautan']);
    }

    public function test_SRS_GEN_05_global_search_for_legal_users_only(): void
    {
        $k = $this->kes([$this->lot()]);
        $provider = app(KesSearchProvider::class);
        $this->assertSame($k->id, $provider->search((string) $k->no_rujukan, $this->user())[0]['id']);
        $this->assertSame([], $provider->search((string) $k->no_rujukan, $this->user(['kes.kes.lihat'])));
    }

    public function test_court_efiling_adapter_imports_hearing_dates_with_manual_fallback(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/segerak-mahkamah')->assertOk()->assertJsonPath('data.baharu', 2);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/segerak-mahkamah')->assertOk()->assertJsonPath('data.baharu', 0);
        $this->assertSame(2, Prosiding::query()->where('kes_mahkamah_id', $k->id)->where('sumber', 'efiling')->count());
        $this->assertNotNull($k->refresh()->tarikh_prosiding_berikutnya);

        $this->app->instance(CourtFilingSystem::class, new class implements CourtFilingSystem
        {
            public function name(): string
            {
                return 'rosak';
            }

            public function caseInfo(string $noKes, ?string $mahkamah = null): ?array
            {
                throw new CourtSystemUnavailable('masa tamat');
            }

            public function fileDocument(string $noKes, string $kategori, string $namaFail, string $kandungan): string
            {
                throw new CourtSystemUnavailable('masa tamat');
            }
        });
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/segerak-mahkamah')->assertUnprocessable()->assertJsonValidationErrors('mahkamah');
        // Manual entry still works.
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/prosiding', ['tarikh_prosiding' => now()->addDays(60)->toDateTimeString(), 'jenis_prosiding' => 'bicara'])->assertCreated();

        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/rujuk-agc', ['jenis_rujukan' => 'nasihat', 'ringkasan' => 'Mohon pandangan undang-undang.'])->assertOk();
        $this->assertDatabaseHas('kes_kronologi', ['kes_mahkamah_id' => $k->id, 'sumber' => 'agc']);
    }
}
