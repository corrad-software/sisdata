<?php

namespace Tests\Modules\Tkt;

use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Tkt\Contracts\TicketIntake;
use App\Modules\Tkt\Models\Konteks;
use App\Modules\Tkt\Models\Lampiran;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\TiketSearchProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class IntakeTest extends TktTestCase
{
    public function test_SRS_TKT_01_ticket_opens_from_ten_screens_across_modules_with_correct_context(): void
    {
        $user = $this->user();
        $screens = [
            ['pro', 'Profil Hartanah 360°', '/admin/pro/lot/abc'], ['hkm', 'Daftar Hak Milik', '/admin/hkm/hak-milik'], ['pjk', 'Senarai Kontrak', '/admin/pjk/kontrak'],
            ['cki', 'Bil Cukai', '/admin/cki/bil'], ['dms', 'Dokumen', '/admin/dms'], ['gis', 'Peta', '/admin/gis/peta'], ['adu', 'Barisan Aduan', '/admin/adu/aduan'],
            ['wfe', 'Peti Tugasan', '/admin/wfe/tugasan'], ['dsh', 'Dashboard', '/admin/dsh'], ['kba', 'Carian Pengetahuan', '/admin/kba/carian'],
        ];
        foreach ($screens as $i => [$modul, $skrin, $path]) {
            $this->actingAs($user)->post('/api/v1/tkt/tiket', [
                'tajuk' => "Masalah pada skrin {$skrin}",
                'penerangan' => 'Butang simpan tidak bertindak balas selepas diklik.',
                'kategori' => 'ralat',
                'kesan' => 'seorang', 'kesegeraan' => 'sederhana',
                'konteks' => json_encode(['skrin' => $skrin, 'modul' => $modul, 'url' => 'http://localhost:3000'.$path, 'kod_fungsi' => $modul.'.skrin'.$i, 'pelayar' => 'Mozilla/5.0 Ujian', 'id_permintaan' => ['req-'.$i.'-abcdefgh']]),
            ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.modul', $modul);
        }

        $this->assertSame(10, Tiket::query()->count());
        $this->assertSame(10, Tiket::query()->distinct('modul')->count('modul'));
        $roles = app(AccessControl::class)->roles($user);
        foreach (Konteks::query()->get() as $k) {
            $this->assertNotNull($k->skrin);
            $this->assertSame($user->id, $k->id_pengguna);
            $this->assertSame($roles, $k->peranan_pengguna);
            $this->assertSame('1.0.0', $k->versi_sistem, 'system version stamped by the server');
            $this->assertStringStartsWith('http://localhost:3000/admin/', (string) $k->url);
            $this->assertCount(1, $k->id_permintaan ?? []);
            $this->assertNotNull($k->id_permintaan_cipta, 'the creating request id is kept for log correlation');
        }
    }

    public function test_SRS_TKT_02_context_is_captured_and_personal_data_masked(): void
    {
        $user = $this->user();
        $res = $this->actingAs($user)->postJson('/api/v1/tkt/tiket', [
            'tajuk' => 'Carian pemilik gagal untuk 850101-10-5555',
            'penerangan' => 'Carian No. KP 850101105555 dan emel ali@contoh.my memberi ralat 500.',
            'kategori' => 'ralat',
            'konteks' => ['skrin' => 'Carian Pemilik', 'modul' => 'per', 'url' => 'http://localhost:3000/admin/per/pemilik?no_kp=850101105555&halaman=2', 'rujukan_rekod' => 'pemilik:42', 'id_permintaan' => ['bad id!', 'good-req-12345678']],
        ])->assertCreated();

        $t = Tiket::query()->findOrFail($res->json('data.id'));
        $this->assertStringNotContainsString('850101', $t->tajuk.$t->penerangan);
        $this->assertStringNotContainsString('ali@contoh.my', $t->penerangan);
        $k = Konteks::query()->where('id_tiket', $t->id)->firstOrFail();
        $this->assertStringContainsString('no_kp=%2A%2A%2A', (string) $k->url);
        $this->assertStringContainsString('halaman=2', (string) $k->url);
        $this->assertSame(['good-req-12345678'], $k->id_permintaan, 'invalid request ids are dropped');
        $this->assertSame('pemilik:42', $k->rujukan_rekod);
        $this->assertNotNull($k->pelayar);
    }

    public function test_SRS_TKT_02_ticket_links_to_adm_error_log_entries_by_request_id(): void
    {
        $tech = $this->ahli();
        $logViewer = $this->ahli('juruteknik', 'L1_MAIS', [...self::JURUTEKNIK, 'adm.log.lihat']);
        $reporter = $this->user();
        $id = $this->actingAs($reporter)->postJson('/api/v1/tkt/tiket', [
            'tajuk' => 'Ralat 500 semasa simpan', 'penerangan' => 'Ralat pelayan semasa menyimpan rekod.', 'kategori' => 'ralat',
            'konteks' => ['skrin' => 'Borang Kontrak', 'modul' => 'pjk', 'id_permintaan' => ['req-gagal-12345678']],
        ])->assertCreated()->json('data.id');
        DB::table('adm_log_ralat')->insert(['id_korelasi' => 'req-gagal-12345678', 'tarikh_masa' => now(), 'aras_keterukan' => 'error', 'modul' => 'pjk', 'kod_ralat' => 'QueryException', 'mesej' => 'SQLSTATE[23505] pendua']);

        $withLogs = $this->actingAs($logViewer)->getJson("/api/v1/tkt/tiket/{$id}")->assertOk()->json('data.log_ralat');
        $this->assertTrue($withLogs['boleh_lihat']);
        $this->assertSame('QueryException', $withLogs['entri'][0]['kod_ralat']);
        $this->assertSame('/admin/pentadbiran/log-ralat?id_korelasi=req-gagal-12345678', $withLogs['entri'][0]['pautan']);
        $plain = $this->actingAs($tech)->getJson("/api/v1/tkt/tiket/{$id}")->assertOk()->json('data.log_ralat');
        $this->assertSame([], $plain['entri']);
        $this->assertContains('req-gagal-12345678', $plain['id_korelasi']);
        $this->assertArrayNotHasKey('log_ralat', $this->actingAs($reporter)->getJson("/api/v1/tkt/tiket/{$id}")->json('data'));

        // Global search (SRS-GEN-05) only returns visible tickets.
        $provider = app(TiketSearchProvider::class);
        $no = Tiket::query()->findOrFail($id)->nombor_rujukan;
        $this->assertSame([$id], array_column($provider->search($no, $reporter), 'id'));
        $this->assertSame([], $provider->search($no, $this->user()));
        $this->assertSame('/admin/tkt/tiket/'.$id, $provider->link($id, $tech));
    }

    public function test_SRS_TKT_03_screenshot_requires_explicit_consent_and_goes_through_dms(): void
    {
        $user = $this->user();
        $base = ['tajuk' => 'Paparan rosak', 'penerangan' => 'Jadual tidak dipaparkan dengan betul.', 'kategori' => 'ralat'];

        $this->actingAs($user)->post('/api/v1/tkt/tiket', $base + ['tangkapan_skrin' => UploadedFile::fake()->image('skrin.png')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('persetujuan_tangkapan_skrin');
        $this->assertSame(0, Tiket::query()->count());

        $res = $this->actingAs($user)->post('/api/v1/tkt/tiket', $base + [
            'tangkapan_skrin' => UploadedFile::fake()->image('skrin.png'),
            'persetujuan_tangkapan_skrin' => '1',
            'fail' => [UploadedFile::fake()->create('log.txt', 3, 'text/plain')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $res->json('data.id');
        $shot = Lampiran::query()->where('id_tiket', $id)->where('tangkapan_skrin', true)->firstOrFail();
        $this->assertTrue($shot->persetujuan_tangkapan_skrin);
        $this->assertNotNull($shot->persetujuan_pada);
        $this->assertSame(2, Lampiran::query()->where('id_tiket', $id)->count());
        $this->assertCount(2, $this->documents->docs);
        $this->assertSame(['tkt_tiket'], array_values(array_unique(array_column($this->documents->docs, 'owner_type'))));

        // DMS refusal (type / size / antivirus) surfaces as a validation error.
        $this->documents->reject = 'Fail mengandungi virus.';
        $this->actingAs($user)->post("/api/v1/tkt/tiket/{$id}/lampiran", ['fail' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.fail.0', 'Fail mengandungi virus.');
        $this->actingAs($user)->post("/api/v1/tkt/tiket/{$id}/lampiran", ['fail' => UploadedFile::fake()->image('b.png'), 'tangkapan_skrin' => '1'], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_SRS_TKT_04_unique_reference_and_recorded_fields(): void
    {
        $user = $this->user();
        $a = $this->tiket([], $user);
        $b = $this->tiket(['tajuk' => 'Soalan format eksport', 'kategori' => 'soalan', 'keterukan' => null, 'modul' => 'lap'], $user);

        $this->assertMatchesRegularExpression('/^TKT-\d{6}-\d{5}$/', $a->nombor_rujukan);
        $this->assertNotSame($a->nombor_rujukan, $b->nombor_rujukan);
        $this->assertSame(['ralat', 3, 'pjk', $user->id], [$a->kategori, $a->keterukan, $a->modul, $a->id_pelapor]);
        $this->assertSame(4, $b->keterukan, 'a question is severity 4 by the matrix');
        $this->assertSame('baharu', $a->status);
        $this->assertNotNull($a->tarikh_sasaran_respons);
        $this->assertNotNull($a->id_pasukan, 'auto-assigned to the L1 helpdesk');
        $this->assertCount(2, $this->notifier->of('tkt.tiket_diterima'), 'acknowledgement to the reporter');
        $this->assertSame([$user->id], $this->notifier->of('tkt.tiket_diterima')[0]['to']);
    }

    public function test_SRS_TKT_04_severity_matrix_and_security_issue_is_severity_one_and_classified(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/tkt/tiket', ['tajuk' => 'Sistem tidak dapat diakses', 'penerangan' => 'Semua pengguna tidak dapat log masuk sejak pagi.', 'kategori' => 'ralat', 'kesan' => 'semua', 'kesegeraan' => 'tinggi'])
            ->assertCreated()->assertJsonPath('data.keterukan', 1)->assertJsonPath('data.keterukan_dicadang', 1);
        $this->actingAs($user)->postJson('/api/v1/tkt/tiket', ['tajuk' => 'Akaun saya dicapai orang lain', 'penerangan' => 'Ada log masuk dari lokasi tidak dikenali.', 'kategori' => 'capaian', 'keterukan' => 4, 'isu_keselamatan' => true])
            ->assertCreated()->assertJsonPath('data.keterukan', 1)->assertJsonPath('data.klasifikasi', 'sulit');
        $this->actingAs($user)->getJson('/api/v1/tkt/rujukan')->assertOk()->assertJsonPath('data.matriks.sebahagian.tinggi', 2);
    }

    public function test_SRS_TKT_04_duplicate_detection_flags_similar_open_ticket(): void
    {
        $user = $this->user();
        $first = $this->tiket(['tajuk' => 'Invois sewaan tidak dapat dijana'], $user);
        $second = $this->tiket(['tajuk' => 'Invois sewaan tidak dapat dijana langsung'], $this->user());

        $this->assertSame($first->id, $second->id_tiket_pendua_dicadang);
        $this->assertGreaterThan(0.45, $second->skor_pendua);

        // Before submitting, the reporter only sees their OWN similar tickets.
        $own = $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Invois sewaan gagal dijana', 'tajuk' => 'Invois sewaan tidak dapat dijana', 'sesi' => 'sesi-aaaa-0001'])->assertOk()->json('data.tiket_serupa');
        $this->assertSame([$first->nombor_rujukan], array_column($own, 'nombor_rujukan'));
        $other = $this->actingAs($this->user())->postJson('/api/v1/tkt/cadangan', ['teks' => 'Invois sewaan gagal dijana', 'tajuk' => 'Invois sewaan tidak dapat dijana', 'sesi' => 'sesi-aaaa-0002'])->assertOk()->json('data.tiket_serupa');
        $this->assertSame([], $other);
    }

    public function test_SRS_TKT_01_ticket_intake_contract_is_idempotent_on_source_ref(): void
    {
        $intake = app(TicketIntake::class);
        $a = $intake->open(['tajuk' => 'Pertanyaan portal', 'keterangan' => 'Bagaimana menyemak status permohonan?', 'emel_pelapor' => 'awam@contoh.my'], 'portal', null, 'pws:borang:77');
        $b = $intake->open(['tajuk' => 'Pertanyaan portal', 'keterangan' => 'Bagaimana menyemak status permohonan?'], 'portal', null, 'pws:borang:77');
        $c = $intake->open(['tajuk' => 'Amaran pemantauan', 'keterangan' => 'CPU pelayan melebihi 95%', 'keutamaan' => 'kritikal', 'konteks' => ['modul' => 'adm']], 'pemantauan', null, 'mon:1');

        $this->assertSame($a, $b);
        $this->assertSame(2, Tiket::query()->count());
        $t = Tiket::query()->findOrFail($c);
        $this->assertSame([1, 'pemantauan', 'adm', 'ralat'], [$t->keterukan, $t->saluran, $t->modul, $t->kategori]);
        $this->assertSame('awam@contoh.my', Tiket::query()->findOrFail($a)->emel_pelapor);
    }

    public function test_SRS_TKT_01_user_without_permission_cannot_open_a_ticket(): void
    {
        $this->actingAs($this->user([]))->postJson('/api/v1/tkt/tiket', ['tajuk' => 'Percubaan', 'penerangan' => 'Tiada kebenaran membuka tiket.', 'kategori' => 'ralat'])->assertForbidden();
        $this->actingAs($this->user([]))->postJson('/api/v1/tkt/cadangan', ['teks' => 'apa-apa sahaja di sini', 'sesi' => 'sesi-bbbb-0001'])->assertForbidden();
    }
}
