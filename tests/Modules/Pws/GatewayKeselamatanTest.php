<?php

namespace Tests\Modules\Pws;

use App\Modules\Pws\Models\KlienApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** SRS-PWS-02/22/23/24: the public API gateway's access control. */
class GatewayKeselamatanTest extends PwsTestCase
{
    public function test_SRS_PWS_23_client_credentials_give_a_short_lived_bearer_token_and_secrets_are_hashed(): void
    {
        [$klien, $secret] = $this->klien(['baca']);
        $this->assertNotSame($secret, $klien->cincangan_rahsia);
        $this->assertStringNotContainsString($secret, (string) json_encode(DB::table('pws_klien_api')->get()));

        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => 'salah'])->assertUnauthorized()->assertJsonPath('error', 'invalid_client');
        $this->awam('GET', 'kandungan')->assertUnauthorized();
        $this->awam('GET', 'kandungan', [], 'bukan-token')->assertUnauthorized();

        $res = $this->awam('POST', 'token', ['grant_type' => 'client_credentials', 'client_id' => $klien->id_klien, 'client_secret' => $secret])->assertOk();
        $this->assertSame('Bearer', $res->json('token_type'));
        $this->assertSame(15 * 60, $res->json('expires_in'));
        $token = $res->json('access_token');
        $this->awam('GET', 'kandungan', [], $token)->assertOk();
        $this->assertSame(0, DB::table('pws_token_akses')->where('cincangan_token', $token)->count(), 'Token disimpan sebagai cincangan sahaja.');

        Carbon::setTestNow(now()->addMinutes(16));
        $this->awam('GET', 'kandungan', [], $token)->assertUnauthorized();
    }

    public function test_SRS_PWS_23_http_basic_credentials_are_accepted(): void
    {
        [$klien, $secret] = $this->klien(['baca']);
        $this->awam('POST', 'token', [], null, ['Authorization' => 'Basic '.base64_encode($klien->id_klien.':'.$secret)])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_SRS_PWS_23_scopes_limit_what_a_client_may_call(): void
    {
        $token = $this->token(['baca']);
        $this->awam('GET', 'kandungan', [], $token)->assertOk();
        $this->awam('POST', 'permohonan/wakaf', [], $token, $this->kunci())->assertForbidden();
        $this->awam('POST', 'bayaran', [], $token, $this->kunci())->assertForbidden();
        $this->awam('POST', 'aduan', [], $token, $this->kunci())->assertForbidden();
    }

    public function test_SRS_PWS_23_ip_allowlist_applies_to_token_and_calls(): void
    {
        [$klien, $secret] = $this->klien(['baca'], ['ip_dibenarkan' => ['10.20.30.0/24']]);
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $secret])->assertUnauthorized(); // test client is 127.0.0.1

        $klien->forceFill(['ip_dibenarkan' => ['127.0.0.1']])->save();
        $token = $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $secret])->assertOk()->json('access_token');
        $klien->forceFill(['ip_dibenarkan' => ['10.20.30.40']])->save();
        $this->awam('GET', 'kandungan', [], $token)->assertUnauthorized();
    }

    public function test_SRS_PWS_23_rotation_keeps_the_old_secret_for_a_grace_period_and_revocation_is_immediate(): void
    {
        [$klien, $old] = $this->klien(['baca']);
        $admin = $this->pegawai();
        $new = $this->actingAs($admin)->postJson("/api/v1/pws/klien/{$klien->id}/putar")->assertOk()->json('data.client_secret');
        $this->assertNotSame($old, $new);
        $this->assertAudited('putar_kunci', ['modul' => 'pws', 'id_rekod' => (string) $klien->id]);

        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $old])->assertOk();
        $token = $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $new])->assertOk()->json('access_token');
        Carbon::setTestNow(now()->addHours(25));
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $old])->assertUnauthorized();
        $token = $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $new])->assertOk()->json('access_token');

        $this->actingAs($admin)->postJson("/api/v1/pws/klien/{$klien->id}/batal", ['sebab' => 'Pelayan portal diganti'])->assertOk()->assertJsonPath('data.status', 'dibatalkan');
        $this->awam('GET', 'kandungan', [], $token)->assertUnauthorized();
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $new])->assertUnauthorized();
    }

    public function test_SRS_PWS_23_expired_client_cannot_get_tokens(): void
    {
        [$klien, $secret] = $this->klien(['baca'], ['tarikh_luput' => '2026-10-20']);
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $secret])->assertOk();
        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $secret])->assertUnauthorized();
    }

    public function test_SRS_PWS_24_per_client_rate_limit(): void
    {
        [$klien, $secret] = $this->klien(['baca'], ['had_kadar_minit' => 3]);
        $token = $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => $secret])->json('access_token');
        foreach (range(1, 3) as $_) {
            $this->awam('GET', 'kandungan', [], $token)->assertOk();
        }
        $this->awam('GET', 'kandungan', [], $token)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_SRS_PWS_24_failed_token_attempts_are_limited(): void
    {
        [$klien] = $this->klien(['baca']);
        foreach (range(1, 10) as $_) {
            $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => 'salah'])->assertUnauthorized();
        }
        $this->awam('POST', 'token', ['client_id' => $klien->id_klien, 'client_secret' => 'salah'])->assertStatus(429);
    }

    public function test_SRS_PWS_23_cors_only_for_the_portal_origin_never_with_credentials(): void
    {
        $token = $this->token(['baca']);
        $this->awam('GET', 'kandungan', [], $token, ['Origin' => 'http://localhost:3000'])->assertForbidden();
        $this->awam('GET', 'kandungan', [], $token, ['Origin' => 'https://laman-lain.example'])->assertForbidden();
        $ok = $this->awam('GET', 'kandungan', [], $token, ['Origin' => 'http://127.0.0.1:8090'])->assertOk();
        $this->assertSame('http://127.0.0.1:8090', $ok->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($ok->headers->get('Access-Control-Allow-Credentials'));

        // Preflight from the portal origin is answered for the public API only.
        $pre = $this->awam('OPTIONS', 'kandungan', [], null, ['Origin' => 'http://127.0.0.1:8090', 'Access-Control-Request-Method' => 'GET']);
        $this->assertSame(204, $pre->getStatusCode());
        $this->assertSame('http://127.0.0.1:8090', $pre->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame(403, $this->awam('OPTIONS', 'kandungan', [], null, ['Origin' => 'https://laman-lain.example', 'Access-Control-Request-Method' => 'GET'])->getStatusCode());
        $this->assertStringContainsString('no-store', (string) $ok->headers->get('Cache-Control'));
    }

    public function test_SRS_PWS_22_every_call_is_logged_with_a_masked_payload_summary(): void
    {
        $token = $this->token(['baca', 'permohonan']);
        $this->awam('POST', 'permohonan/status', ['no_rujukan' => 'PHWS-2026-TIADA01', 'emel' => 'rahsia@contoh.invalid'], $token)->assertNotFound();

        $row = DB::table('pws_log_integrasi')->where('arah', 'masuk')->where('endpoint', 'like', '%permohonan/status')->sole();
        $this->assertSame(404, (int) $row->kod_status);
        $this->assertNotNull($row->klien_id);
        $this->assertNotNull($row->tempoh_ms);
        $summary = json_decode($row->ringkasan_payload, true);
        $this->assertSame('***', $summary['emel']);
        $this->assertStringNotContainsString('rahsia@contoh.invalid', (string) json_encode(DB::table('pws_log_integrasi')->get()));
        $this->assertSame(1, DB::table('adm_log_integrasi')->where('kod_integrasi', 'portal_hws')->where('arah', 'masuk')->where('status_http', 404)->count());
    }

    public function test_SRS_PWS_23_admin_client_management_needs_permission_and_mfa(): void
    {
        $payload = ['nama' => 'Pelayan Portal', 'skop' => ['baca'], 'ip_dibenarkan' => ['10.0.0.5']];
        $this->actingAs($this->user(['pws.klien.lihat']))->postJson('/api/v1/pws/klien', $payload)->assertForbidden();
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->getJson('/api/v1/pws/klien')->assertForbidden();

        $admin = $this->pegawai();
        $this->mfa->ok = false;
        $this->actingAs($admin)->postJson('/api/v1/pws/klien', $payload)->assertForbidden();
        $this->mfa->ok = true;
        $this->actingAs($admin)->postJson('/api/v1/pws/klien', ['ip_dibenarkan' => ['bukan-ip']] + $payload)->assertUnprocessable()->assertJsonValidationErrors('ip_dibenarkan.0');
        $res = $this->actingAs($admin)->postJson('/api/v1/pws/klien', $payload)->assertCreated();
        $this->assertStringStartsWith('phws_s_', $res->json('data.client_secret'));

        $list = $this->actingAs($admin)->getJson('/api/v1/pws/klien')->assertOk();
        $this->assertStringNotContainsString($res->json('data.client_secret'), $list->getContent());
        $this->assertArrayNotHasKey('client_secret', $list->json('data.0'));
        $this->assertSame(['10.0.0.5'], KlienApi::query()->sole()->ip_dibenarkan);
    }
}
