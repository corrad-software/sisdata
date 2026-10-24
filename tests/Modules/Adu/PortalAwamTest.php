<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Models\ResolusiAduan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;

class PortalAwamTest extends AduTestCase
{
    /** @return array<string, mixed> */
    private function borang(array $extra = []): array
    {
        return $extra + [
            'kategori' => 'PEMBUANGAN_SAMPAH',
            'keterangan' => 'Longgokan sampah dan sisa binaan dibuang di kawasan lapang tanah wakaf.',
            'lat' => self::PUSAT_PETALING[0],
            'lng' => self::PUSAT_PETALING[1],
            'nama' => 'Aminah binti Yusof',
            'emel' => 'aminah@contoh.test',
            'no_telefon' => '019-8887777',
            'captcha' => 'lulus-captcha',
        ];
    }

    public function test_SRS_ADU_02_public_portal_accepts_a_complaint_without_an_account_and_shows_the_reference(): void
    {
        $lot = $this->lot();
        $res = $this->post('/api/v1/adu/awam/aduan', $this->borang(['fail' => [UploadedFile::fake()->image('sampah.jpg')]]), ['Accept' => 'application/json'])
            ->assertCreated();

        $no = $res->json('data.no_rujukan_aduan');
        $this->assertMatchesRegularExpression('/^ADU-\d{4}-\d{6}$/', $no);
        $this->assertStringContainsString($no, $res->json('message'));
        $aduan = Aduan::query()->where('no_rujukan_aduan', $no)->firstOrFail();
        $this->assertSame('portal', $aduan->saluran_penerimaan);
        $this->assertSame($lot, $aduan->lot_id);
        $this->assertCount(1, $this->documents->docs);

        // Acceptance #1: reference also sent by e-mail.
        $this->assertSame('aminah@contoh.test', $this->messenger->sent[0]['ke']);
        $this->assertStringContainsString($no, $this->messenger->sent[0]['teks']);
    }

    public function test_SRS_ADU_03_reference_is_returned_immediately_in_the_submit_response(): void
    {
        $res = $this->postJson('/api/v1/adu/awam/aduan', $this->borang())->assertCreated();
        $this->assertNotEmpty($res->json('data.no_rujukan_aduan'));
        $this->assertSame('Aduan diterima', $res->json('data.status'));
    }

    public function test_SRS_ADU_04_status_check_needs_the_reference_plus_email_or_phone(): void
    {
        $no = $this->postJson('/api/v1/adu/awam/aduan', $this->borang())->json('data.no_rujukan_aduan');

        $this->postJson('/api/v1/adu/awam/semak', ['no_rujukan_aduan' => $no])->assertNotFound();
        $this->postJson('/api/v1/adu/awam/semak', ['no_rujukan_aduan' => $no, 'pengesahan' => 'salah@contoh.test'])->assertNotFound();
        $this->postJson('/api/v1/adu/awam/semak', ['no_rujukan_aduan' => $no, 'pengesahan' => 'AMINAH@contoh.test'])->assertOk()
            ->assertJsonPath('data.status', 'Aduan diterima');
        $this->postJson('/api/v1/adu/awam/semak', ['no_rujukan_aduan' => strtolower($no), 'pengesahan' => '+6019 888 7777'])->assertOk()
            ->assertJsonMissingPath('data.keterangan');
    }

    public function test_SRS_ADU_22_anonymous_complaint_stores_no_identity_gets_no_notices_and_is_checked_by_reference_only(): void
    {
        $res = $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['tanpa_nama' => true, 'nama' => 'Tidak Disimpan', 'emel' => 'x@contoh.test']))->assertCreated();
        $aduan = Aduan::query()->where('no_rujukan_aduan', $res->json('data.no_rujukan_aduan'))->firstOrFail();

        $this->assertTrue($aduan->status_tanpa_nama);
        $this->assertTrue($res->json('data.tanpa_nama'));
        $this->assertSame(0, Pengadu::query()->where('aduan_id', $aduan->id)->count());
        $this->assertSame([], $this->messenger->sent);
        $this->postJson('/api/v1/adu/awam/semak', ['no_rujukan_aduan' => $aduan->no_rujukan_aduan])->assertOk()->assertJsonPath('data.tanpa_nama', true);

        // Clearly marked in the queue.
        $user = $this->user(['adu.aduan.lihat']);
        $row = $this->actingAs($user)->getJson('/api/v1/adu/aduan?tanpa_nama=1')->assertOk()->json('data.0');
        $this->assertSame($aduan->id, $row['id']);
        $this->assertTrue($row['status_tanpa_nama']);

        // No status notice even when closed.
        $aduan->update(['status' => 'menunggu_kelulusan', 'id_instans_aliran' => 'wf-x']);
        ResolusiAduan::query()->create(['aduan_id' => $aduan->id, 'kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Dibersihkan', 'justifikasi' => 'Lawatan', 'status' => 'menunggu', 'status_makluman_pengadu' => 'tidak_berkenaan']);
        $this->completeWorkflow($aduan->refresh());
        $this->assertSame('ditutup', $aduan->refresh()->status);
        $this->assertSame([], $this->messenger->sent);
    }

    public function test_SRS_ADU_24_captcha_rate_limits_and_file_limits_protect_the_portal(): void
    {
        $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['captcha' => 'salah']))->assertUnprocessable()->assertJsonValidationErrors('captcha');
        $this->postJson('/api/v1/adu/awam/aduan', array_diff_key($this->borang(), ['captcha' => 1]))->assertUnprocessable()->assertJsonValidationErrors('captcha');

        // File type and size.
        $this->post('/api/v1/adu/awam/aduan', $this->borang(['fail' => [UploadedFile::fake()->create('skrip.exe', 10, 'application/x-msdownload')]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('fail.0');
        $this->post('/api/v1/adu/awam/aduan', $this->borang(['fail' => [UploadedFile::fake()->create('besar.pdf', 20000, 'application/pdf')]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('fail.0');

        // Per phone number (3 / day).
        RateLimiter::clear('adu-awam-ip:'.sha1('127.0.0.1'));
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['keterangan' => "Aduan berbeza nombor {$i} mengenai sampah di tanah wakaf.", 'emel' => null]))->assertCreated();
        }
        $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['keterangan' => 'Aduan keempat daripada nombor telefon yang sama.', 'emel' => null]))->assertStatus(429);

        // Per IP (5 / hour) — counts every submission attempt.
        $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['tanpa_nama' => true, 'keterangan' => 'Satu lagi aduan tanpa nama untuk had IP.']))->assertCreated();
        $this->postJson('/api/v1/adu/awam/aduan', $this->borang(['tanpa_nama' => true, 'keterangan' => 'Aduan keenam dari IP yang sama perlu ditolak.']))->assertStatus(429);
    }

    public function test_SRS_ADU_24_same_content_from_same_contact_is_detected_as_duplicate(): void
    {
        $first = $this->postJson('/api/v1/adu/awam/aduan', $this->borang())->assertCreated()->json('data.no_rujukan_aduan');
        $again = $this->postJson('/api/v1/adu/awam/aduan', $this->borang())->assertOk();

        $this->assertTrue($again->json('data.pendua'));
        $this->assertSame($first, $again->json('data.no_rujukan_aduan'));
        $this->assertSame(1, Aduan::query()->count());
    }
}
