<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Contracts\CentralComplaintSystem;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Adu\Integrasi\MockSistemAduanPusat;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\KronologiAduan;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Models\PenyegerakanPusat;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\SegerakPusat;
use Illuminate\Http\UploadedFile;

class IntakeTest extends AduTestCase
{
    public function test_SRS_ADU_01_internal_intake_lands_in_the_single_queue_with_timeline_and_sla(): void
    {
        $officer = $this->user(self::UNIT_ADUAN);
        $lot = $this->lot();

        $res = $this->actingAs($officer)->postJson('/api/v1/adu/aduan', [
            'saluran_penerimaan' => 'kaunter',
            'kategori' => 'PENCEROBOHAN',
            'keterangan' => 'Penghuni haram membina pondok di atas tanah wakaf ini.',
            'lot_id' => $lot,
            'pengadu' => ['nama' => 'Siti binti Ahmad', 'no_telefon' => '013-1112222'],
        ])->assertCreated();

        $id = $res->json('data.id');
        $this->assertMatchesRegularExpression('/^ADU-\d{4}-\d{6}$/', $res->json('data.no_rujukan_aduan'));
        $this->assertSame('kaunter', $res->json('data.saluran_penerimaan'));
        $this->assertSame($lot, $res->json('data.lot_id'));
        $this->assertSame('1008', $res->json('data.kod_daerah'));
        $this->assertNotNull($res->json('data.sla.masa_sasaran_selesai'));
        $this->assertSame(['terima', 'triaj'], KronologiAduan::query()->where('aduan_id', $id)->orderBy('id')->limit(2)->pluck('jenis_tindakan')->all());

        $list = $this->actingAs($officer)->getJson('/api/v1/adu/aduan?status=terbuka')->assertOk();
        $this->assertSame([$id], array_column($list->json('data'), 'id'));
    }

    public function test_SRS_ADU_03_reference_numbers_are_unique_across_channels(): void
    {
        $a = $this->aduan([], 'kaunter');
        $b = $this->aduan([], 'telefon', null);
        $c = app(ComplaintIntake::class)->open(['kategori' => 'LAIN', 'keterangan' => 'Dari pemantauan tanah'], 'ptn', null, 'ptn_lawatan:1');
        $refs = [$a->no_rujukan_aduan, $b->no_rujukan_aduan, Aduan::query()->find($c)->no_rujukan_aduan];

        $this->assertCount(3, array_unique($refs));
        foreach ($refs as $ref) {
            $this->assertMatchesRegularExpression('/^ADU-\d{4}-\d{6}$/', $ref);
        }
    }

    public function test_SRS_ADU_06_channel_is_recorded_for_every_complaint_and_reported(): void
    {
        foreach (['kaunter', 'emel', 'telefon', 'lapangan'] as $saluran) {
            $this->aduan([], $saluran);
        }
        $user = $this->user(['adu.aduan.lihat', 'adu.laporan.lihat']);
        $rows = $this->actingAs($user)->getJson('/api/v1/adu/laporan/saluran')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['kaunter', 'emel', 'telefon', 'lapangan'], array_column($rows, 'kod'));
    }

    public function test_SRS_ADU_02_field_intake_with_gps_and_photos_goes_to_dms(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $this->lot();

        $res = $this->actingAs($officer)->post('/api/v1/adu/aduan', [
            'saluran_penerimaan' => 'lapangan',
            'kategori' => 'BINAAN_HARAM',
            'keterangan' => 'Struktur gerai baru didirikan di tepi jalan.',
            'lat' => self::PUSAT_PETALING[0],
            'lng' => self::PUSAT_PETALING[1],
            'tanpa_nama' => true,
            'fail' => [UploadedFile::fake()->image('tapak.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('koordinat', $res->json('data.kaedah_padanan_lot'));
        $doc = array_values($this->documents->docs)[0];
        $this->assertSame(['aduan', $res->json('data.id'), 'gambar_tapak'], [$doc['owner_type'], $doc['owner_id'], $doc['category']]);
        $this->assertDatabaseHas('dokumen_aduan', ['aduan_id' => $res->json('data.id'), 'jenis_dokumen' => 'gambar']);
    }

    public function test_SRS_ADU_07_central_system_complaints_join_the_queue_idempotently_and_status_flows_back(): void
    {
        MockSistemAduanPusat::queue(['id_luaran' => 'SAP-1', 'kategori' => 'KEROSAKAN_ASET', 'keterangan' => 'Bumbung dewan bocor', 'tarikh' => now()->toIso8601String(), 'nama' => 'Ahmad', 'emel' => 'ahmad@contoh.test']);
        MockSistemAduanPusat::queue(['id_luaran' => 'SAP-1', 'kategori' => 'KEROSAKAN_ASET', 'keterangan' => 'Bumbung dewan bocor', 'tarikh' => now()->toIso8601String()]);

        $out = app(SegerakPusat::class)->import();
        $this->assertSame(['diterima' => 1, 'sedia_ada' => 1, 'gagal' => 0], $out);

        $aduan = Aduan::query()->where('rujukan_sumber', 'SAP-1')->firstOrFail();
        $this->assertSame('sistem_aduan_pusat', $aduan->saluran_penerimaan);
        $this->assertSame('SAP-1', $aduan->id_aduan_sistem_luaran);

        // Inbound push endpoint (machine account) — same id is idempotent.
        $machine = $this->user(['adu.integrasi.cipta']);
        $this->actingAs($machine)->postJson('/api/v1/adu/integrasi/pusat/aduan', ['id_luaran' => 'SAP-1', 'kategori' => 'KEROSAKAN_ASET', 'keterangan' => 'x'])
            ->assertOk()->assertJsonPath('data.sedia_ada', true);
        $this->actingAs($machine)->postJson('/api/v1/adu/integrasi/pusat/aduan', ['id_luaran' => 'SAP-2', 'kategori' => 'TIDAK_WUJUD', 'keterangan' => 'Sampah dibuang'])
            ->assertCreated();
        $this->assertSame(1, Aduan::query()->where('rujukan_sumber', 'SAP-1')->count());

        // Status changes are pushed back.
        $officer = $this->user(self::UNIT_ADUAN);
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/tugaskan", ['id_pegawai' => $officer->id])->assertOk();
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/mula-siasatan")->assertOk();
        $pushed = MockSistemAduanPusat::pushed();
        $this->assertSame('SAP-1', end($pushed)['id']);
        $this->assertSame('dalam_siasatan', end($pushed)['payload']['status']);
    }

    public function test_SRS_ADU_07_failed_status_push_is_kept_in_outbox_and_retried(): void
    {
        $aduan = app(SegerakPusat::class)->terimaSatu(app(AduanService::class), ['id_luaran' => 'SAP-9', 'kategori' => 'LAIN', 'keterangan' => 'Ujian', 'tanpa_nama' => true]);
        $down = new class implements CentralComplaintSystem
        {
            public function fetchNew(?string $since = null): array
            {
                return [];
            }

            public function pushStatus(string $externalId, array $payload): void
            {
                throw new \RuntimeException('Sistem Aduan berpusat tidak dapat dihubungi');
            }
        };
        $this->app->instance(CentralComplaintSystem::class, $down);

        $officer = $this->user(self::UNIT_ADUAN);
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/tugaskan", ['id_pegawai' => $officer->id])->assertOk();
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/mula-siasatan")->assertOk();
        $row = PenyegerakanPusat::query()->where('aduan_id', $aduan->id)->latest('id')->firstOrFail();
        $this->assertSame('gagal', $row->status);
        $this->assertSame(1, $row->cubaan);

        $this->app->forgetInstance(CentralComplaintSystem::class);
        $this->app->bind(CentralComplaintSystem::class, MockSistemAduanPusat::class);
        $this->assertSame(['berjaya' => 1, 'gagal' => 0], app(SegerakPusat::class)->ulangCuba());
        $this->assertSame('berjaya', $row->refresh()->status);
    }

    public function test_complaint_intake_contract_is_idempotent_on_source_ref(): void
    {
        $intake = app(ComplaintIntake::class);
        $a = $intake->open(['kategori' => 'PENCEROBOHAN', 'keterangan' => 'Penemuan pemantauan: binaan di atas lot', 'pelapor_nama' => 'Pegawai PTN', 'pelapor_hubungan' => 'ptn@mais.test'], 'ptn', null, 'ptn_lawatan:42');
        $b = $intake->open(['kategori' => 'PENCEROBOHAN', 'keterangan' => 'ulang'], 'ptn', null, 'ptn_lawatan:42');

        $this->assertSame($a, $b);
        $this->assertSame(1, Aduan::query()->where('rujukan_sumber', 'ptn_lawatan:42')->count());
        $this->assertSame('ptn@mais.test', Pengadu::query()->where('aduan_id', $a)->value('emel'));
    }
}
