<?php

namespace Tests\Modules\Pws;

use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\Services\PermohonanService;
use App\Modules\Rbc\Services\RbcAccessControl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** SRS-PWS-07/08/09/20/21/22: applications from the portal land in SISDATA. */
class PermohonanPortalTest extends PwsTestCase
{
    /** @return array<string, mixed> */
    private function wakaf(array $extra = []): array
    {
        return $extra + [
            'nama_pewakaf' => 'Pewakaf Awam Sintetik', 'no_pengenalan' => '700101-10-1234', 'emel' => 'pewakaf@contoh.invalid',
            'jenis_wakaf' => 'am', 'tujuan_wakaf' => 'Tapak surau', 'no_lot' => 'PT 9898', 'kod_mukim' => '100803', 'nilai_anggaran' => '300000',
            'persetujuan_pdpa' => '1',
        ];
    }

    /** @return array<string, mixed> */
    private function sewaan(string $kodPremis, array $extra = []): array
    {
        return $extra + [
            'kod_premis' => $kodPremis, 'jenis_entiti' => 'individu', 'nama' => 'Pemohon Sewa Sintetik', 'no_pengenalan' => '900101-10-5678',
            'telefon' => '0123456789', 'emel' => 'penyewa@contoh.invalid', 'tempoh_bulan' => '24', 'tujuan' => 'Kedai runcit', 'persetujuan_pdpa' => '1',
        ];
    }

    /** Kriteria penerimaan 2 (wakaf). */
    public function test_SRS_PWS_21_waqf_registration_lands_in_PER_as_a_pending_case_with_the_same_reference(): void
    {
        $token = $this->token();
        $res = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci())->assertCreated();
        $ref = $res->json('data.no_rujukan');
        $this->assertMatchesRegularExpression('/^PHWS-2026-[A-Z0-9]{7}$/', $ref);
        $this->assertStringContainsString($ref, $res->json('message'));
        $this->assertSame('menunggu_semakan', $res->json('data.status'));
        $this->assertTrue($res->json('data.diterima_sisdata'));

        $case = Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', $ref)->sole();
        $this->assertSame(Pemerolehan::MENUNGGU_SEMAKAN, $case->status_pemerolehan);
        $this->assertSame('portal_wakaf', $case->sumber_rekod);
        $this->assertSame($case->no_kes, $res->json('data.rujukan_sisdata'));

        // SRS-PWS-20: personal data does not stay in the gateway once delivered.
        $row = Permohonan::query()->where('no_rujukan', $ref)->sole();
        $this->assertNull($row->kandungan);
        $this->assertSame('diterima', $row->status_penghantaran);
        $this->assertStringNotContainsString('700101-10-1234', (string) json_encode(DB::table('pws_permohonan')->get()));
        $this->assertStringNotContainsString('pewakaf@contoh.invalid', (string) json_encode(DB::table('pws_permohonan')->get()));
    }

    /** Kriteria penerimaan 2 (status dua hala). */
    public function test_SRS_PWS_21_officer_decision_in_PER_is_shown_to_the_applicant(): void
    {
        $token = $this->token();
        $ref = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci())->json('data.no_rujukan');
        $case = Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', $ref)->sole();

        $officer = $this->user(['per.kes.lihat', 'per.kes.kemas_kini']);
        $this->actingAs($officer)->postJson("/api/v1/per/kes/{$case->id}/semakan-awam", ['terima' => true])->assertOk();

        $status = $this->awam('POST', 'permohonan/status', ['no_rujukan' => $ref, 'emel' => 'PEWAKAF@contoh.invalid'], $token)->assertOk();
        $this->assertSame('diterima', $status->json('data.status'));
        $this->assertSame(['dihantar', 'menunggu_semakan', 'diterima'], array_column($status->json('data.sejarah'), 'status'));
        $this->assertArrayNotHasKey('nama_pewakaf', $status->json('data'));

        $changes = $this->awam('GET', 'permohonan/perubahan', ['sejak' => '2026-10-01T00:00:00+08:00'], $token)->assertOk();
        $this->assertSame([$ref], array_column($changes->json('data'), 'no_rujukan'));
    }

    public function test_SRS_PWS_09_status_check_needs_the_matching_email_and_never_reveals_existence(): void
    {
        $token = $this->token();
        $ref = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci())->json('data.no_rujukan');
        $wrong = $this->awam('POST', 'permohonan/status', ['no_rujukan' => $ref, 'emel' => 'orang.lain@contoh.invalid'], $token)->assertNotFound();
        $none = $this->awam('POST', 'permohonan/status', ['no_rujukan' => 'PHWS-2026-TIADA99', 'emel' => 'pewakaf@contoh.invalid'], $token)->assertNotFound();
        $this->assertSame($wrong->json('message'), $none->json('message'));
    }

    /** Kriteria penerimaan 2 (sewaan). */
    public function test_SRS_PWS_21_rental_application_lands_in_PJK_and_status_follows_the_officers_decision(): void
    {
        $premis = $this->premis();
        $token = $this->token();
        $res = $this->awam('POST', 'permohonan/sewaan', $this->sewaan($premis->kod_premis), $token, $this->kunci(), ['lampiran' => [UploadedFile::fake()->create('ic.pdf', 50, 'application/pdf')]])
            ->assertCreated();
        $ref = $res->json('data.no_rujukan');
        $this->assertSame('diterima', $res->json('data.status'));

        $app = PermohonanSewaan::query()->withoutGlobalScope('access')->where('rujukan_portal', $ref)->sole();
        $this->assertSame('portal', $app->sumber);
        $this->assertSame($premis->id, $app->premis_id);
        $this->assertSame($app->no_permohonan, $res->json('data.rujukan_sisdata'));
        // Supporting documents through MOD-DMS, Sulit, linked to the gateway record.
        $doc = $this->dms->ofCategory('dokumen_sokongan_permohonan')[0] ?? null;
        $this->assertNotNull($doc);
        $this->assertSame('sulit', $doc['classification']);
        // Done with the integration account, never the officer's identity.
        $this->assertAudited('cipta', ['nama_entiti' => 'permohonan_sewaan', 'id_rekod' => (string) $app->id]);

        $officer = $this->user(['pjk.permohonan.lihat', 'pjk.permohonan.kemas_kini']);
        $this->actingAs($officer)->postJson("/api/v1/pjk/permohonan/{$app->id}/tolak", ['catatan' => 'Premis telah ditawarkan kepada pemohon lain'])->assertOk();
        $this->awam('POST', 'permohonan/status', ['no_rujukan' => $ref, 'emel' => 'penyewa@contoh.invalid'], $token)->assertOk()
            ->assertJsonPath('data.status', 'tolak');
    }

    public function test_SRS_PWS_07_validation_errors_come_back_per_field_and_nothing_is_created(): void
    {
        $token = $this->token();
        $this->awam('POST', 'permohonan/wakaf', ['persetujuan_pdpa' => '0'], $token, $this->kunci())->assertUnprocessable()
            ->assertJsonValidationErrors(['nama_pewakaf', 'emel', 'persetujuan_pdpa']);
        $this->awam('POST', 'permohonan/sewaan', $this->sewaan('PRM-TIADA'), $token, $this->kunci('2'))->assertUnprocessable()->assertJsonValidationErrors('kod_premis');
        $hidden = $this->premis(['papar_portal' => false]);
        $this->awam('POST', 'permohonan/sewaan', $this->sewaan($hidden->kod_premis), $token, $this->kunci('3'))->assertUnprocessable()->assertJsonValidationErrors('kod_premis');
        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('4'), ['lampiran' => [UploadedFile::fake()->create('skrip.exe', 10, 'application/x-msdownload')]])
            ->assertUnprocessable()->assertJsonValidationErrors('lampiran.0');

        $this->assertSame(0, Permohonan::query()->count());
        $this->assertSame(0, PermohonanSewaan::query()->withoutGlobalScope('access')->count());
    }

    public function test_SRS_PWS_22_idempotency_key_replays_the_same_result_and_refuses_a_different_body(): void
    {
        $token = $this->token();
        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token)->assertStatus(400);

        $first = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('ulang'))->assertCreated();
        $again = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('ulang'))->assertCreated();
        $this->assertSame('true', $again->headers->get('Idempotent-Replayed'));
        $this->assertSame($first->json('data.no_rujukan'), $again->json('data.no_rujukan'));
        $this->assertSame(1, Permohonan::query()->count());
        $this->assertSame(1, Pemerolehan::query()->withoutGlobalScope('access')->where('sumber_rekod', 'portal_wakaf')->count());

        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(['nama_pewakaf' => 'Lain']), $token, $this->kunci('ulang'))->assertUnprocessable();
    }

    public function test_SRS_PWS_24_submissions_are_limited_per_end_user_ip(): void
    {
        $token = $this->token();
        foreach (range(1, 10) as $i) {
            $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('ip'.$i) + ['X-Pengguna-IP' => '203.0.113.9'])->assertCreated();
        }
        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('ip11') + ['X-Pengguna-IP' => '203.0.113.9'])->assertStatus(429);
        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('ip12') + ['X-Pengguna-IP' => '203.0.113.10'])->assertCreated();
    }

    public function test_SRS_PWS_22_failed_delivery_is_kept_and_retried(): void
    {
        $token = $this->token();
        // Integration account temporarily without PER permission → PER refuses (403).
        DB::table('peranan_kebenaran')->where('kebenaran', 'per.portal_wakaf.cipta')->delete();
        $res = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci())->assertCreated();
        $this->assertFalse($res->json('data.diterima_sisdata'));
        $row = Permohonan::query()->sole();
        $this->assertSame('gagal', $row->status_penghantaran);
        $this->assertNotNull($row->kandungan, 'Disimpan tersulit untuk cubaan semula.');
        $this->assertSame(1, DB::table('pws_log_integrasi')->where('arah', 'keluar')->where('kod_status', 403)->count());

        $role = DB::table('peranan')->where('kod', 'integrasi_portal_hws')->value('id');
        DB::table('peranan_kebenaran')->insert(['peranan_id' => $role, 'kebenaran' => 'per.portal_wakaf.cipta', 'modul' => 'per', 'fungsi' => 'portal_wakaf', 'tindakan' => 'cipta', 'created_at' => now(), 'updated_at' => now()]);
        RbcAccessControl::flushAll();

        $this->actingAs($this->pegawai())->postJson('/api/v1/pws/permohonan/cuba-semula')->assertOk()->assertJsonPath('data.wakaf', 1);
        $this->assertSame('diterima', $row->refresh()->status_penghantaran);
        $this->assertNull($row->kandungan);
        $this->assertSame(1, Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', $row->no_rujukan)->count());
        $this->assertSame(1, (int) DB::table('pws_log_integrasi')->where('arah', 'keluar')->where('kod_status', 201)->value('bilangan_cuba_semula'));
    }

    public function test_SRS_PWS_08_reference_is_the_single_key_across_portal_and_sisdata(): void
    {
        $token = $this->token();
        $premis = $this->premis();
        $w = $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci('a'))->json('data.no_rujukan');
        $s = $this->awam('POST', 'permohonan/sewaan', $this->sewaan($premis->kod_premis), $token, $this->kunci('b'))->json('data.no_rujukan');
        $this->assertNotSame($w, $s);
        $this->assertSame(1, Pemerolehan::query()->withoutGlobalScope('access')->where('rujukan_luaran', $w)->count());
        $this->assertSame(1, PermohonanSewaan::query()->withoutGlobalScope('access')->where('rujukan_portal', $s)->count());
        $this->assertSame(PermohonanService::emailHash('penyewa@contoh.invalid'), Permohonan::query()->where('no_rujukan', $s)->value('cincangan_emel'));
    }

    public function test_SRS_PWS_22_admin_monitoring_lists_submissions_without_personal_data_and_needs_permission(): void
    {
        $token = $this->token();
        $this->awam('POST', 'permohonan/wakaf', $this->wakaf(), $token, $this->kunci())->assertCreated();

        $this->actingAs($this->user(['pjk.kontrak.lihat']))->getJson('/api/v1/pws/permohonan')->assertForbidden();
        $list = $this->actingAs($this->user(['pws.pemantauan.lihat']))->getJson('/api/v1/pws/permohonan')->assertOk();
        $this->assertSame(1, $list->json('meta.total'));
        $this->assertStringNotContainsString('Pewakaf Awam Sintetik', $list->getContent());
        $this->actingAs($this->user(['pws.pemantauan.lihat']))->getJson('/api/v1/pws/log-integrasi?arah=keluar')->assertOk()->assertJsonPath('data.0.arah', 'keluar');
        $this->actingAs($this->user(['pws.pemantauan.lihat']))->getJson('/api/v1/pws/ringkasan')->assertOk()->assertJsonPath('data.permohonan.0.jenis', 'wakaf');
        $this->actingAs($this->user(['pws.pemantauan.lihat']))->postJson('/api/v1/pws/permohonan/cuba-semula')->assertForbidden();
    }
}
