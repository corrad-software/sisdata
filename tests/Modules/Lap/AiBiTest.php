<?php

namespace Tests\Modules\Lap;

use App\Models\User;
use App\Modules\Lap\Models\PapanBi;
use App\Modules\Lap\Services\Metabase;
use App\Modules\Rbc\Contracts\AccessControl;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** SRS-LAP-13 AI prompt, SRS-LAP-14 smart search, SRS-LAP-01 BI embedding. */
class AiBiTest extends LapTestCase
{
    public function test_SRS_LAP_13_plain_language_request_becomes_an_editable_saveable_report(): void
    {
        $this->complaint('100803');
        $this->complaint('100901');
        $user = $this->user();
        $r = $this->actingAs($user)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'Bilangan aduan terbuka mengikut daerah tahun ini'])->assertOk()->json('data');
        $this->assertTrue($r['difahami']);
        $this->assertSame('aduan', $r['definisi']['set']);
        $this->assertSame(['kod_daerah'], $r['definisi']['kumpulan']);
        $this->assertSame('tahun_ini', $r['definisi']['tempoh']['pratetap']);
        $this->assertContains(['medan' => 'terbuka', 'op' => 'benar'], $r['definisi']['penapis']);
        $this->assertNotEmpty($r['penjelasan']);
        $this->assertAudited('ai_prompt', ['modul' => 'lap']);

        $res = $this->jana($user, ['adhoc' => $r['definisi']])['bahagian'][0]['hasil'];
        $this->assertSame(2, $res['jumlah_keseluruhan']['bilangan']);

        $this->actingAs($user)->postJson('/api/v1/lap/definisi', ['nama' => 'Dari AI', 'pertanyaan' => $r['definisi'], 'status_kongsi' => 'peribadi', 'sumber' => 'ai_prompt'])->assertCreated();

        $yoy = $this->actingAs($user)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'trend jumlah baki cukai tertunggak di Sepang berbanding tahun lepas'])->json('data.definisi');
        $this->assertSame('cukai', $yoy['set']);
        $this->assertSame('tahun_lepas', $yoy['bandingan']);
        $this->assertContains(['fungsi' => 'jumlah', 'medan' => 'baki'], $yoy['agregat']);
        $this->assertContains(['medan' => 'kod_daerah', 'op' => 'sama', 'nilai' => '1009'], $yoy['penapis']);
        $this->assertSame('garis', $yoy['carta']);

        $this->assertFalse($this->actingAs($user)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'apa khabar?'])->json('data.difahami'));
    }

    public function test_SRS_LAP_13_a_prompt_cannot_widen_the_users_scope_or_datasets(): void
    {
        $this->complaint('100803');
        $this->complaint('100901');
        $user = $this->user(districts: ['1009']);
        $def = $this->actingAs($user)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'aduan mengikut daerah di Petaling'])->json('data.definisi');
        $res = $this->jana($user, ['adhoc' => $def])['bahagian'][0]['hasil'];
        $this->assertSame([], $res['baris'], 'asking for another district returns nothing');

        $noTax = $this->user(['lap.laporan.lihat', 'lap.adhoc.cipta', 'adu.laporan.lihat']);
        $r = $this->actingAs($noTax)->postJson('/api/v1/lap/ai/prompt', ['prompt' => 'jumlah cukai tertunggak mengikut daerah'])->json('data');
        $this->assertFalse($r['difahami']);
        $this->assertNull($r['definisi']);
    }

    public function test_SRS_LAP_14_smart_search_finds_lots_from_natural_keywords(): void
    {
        $a = $this->lot(['kod_mukim' => '100901', 'no_lot' => 'PT 1234', 'kategori_guna_tanah' => 'PERTANIAN']);
        $leased = $this->lot(['kod_mukim' => '100901', 'no_lot' => 'PT 5678', 'kategori_guna_tanah' => 'PERTANIAN']);
        $this->contract($leased);
        $this->lot(['kod_mukim' => '100803', 'no_lot' => 'PT 9999', 'kategori_guna_tanah' => 'PERTANIAN']);
        $user = $this->user();

        $r = $this->actingAs($user)->getJson('/api/v1/lap/carian?q='.urlencode('tanah pertanian tiada pajakan di Sepang'))->assertOk()->json('data');
        $this->assertSame(['PT 1234'], array_column($r['hasil']['baris'], 'no_lot'));
        $this->assertSame('/admin/pro/lot/'.$a, $r['hasil']['baris'][0]['_pautan']);
        $this->assertContains('Daerah Sepang', $r['tafsiran']);

        $r = $this->actingAs($user)->getJson('/api/v1/lap/carian?q='.urlencode('lot 5678'))->json('data');
        $this->assertSame(['PT 5678'], array_column($r['hasil']['baris'], 'no_lot'));

        // Scope still applies.
        $r = $this->actingAs($this->user(districts: ['1008']))->getJson('/api/v1/lap/carian?q='.urlencode('pertanian Sepang'))->json('data');
        $this->assertSame([], $r['hasil']['baris']);
        $this->actingAs($this->user(['lap.laporan.lihat']))->getJson('/api/v1/lap/carian?q=pertanian')->assertForbidden();
    }

    private function board(array $a = []): PapanBi
    {
        return PapanBi::query()->create($a + ['kod' => 'portfolio', 'nama' => 'Portfolio daerah', 'jenis' => 'dashboard', 'id_metabase' => 7, 'parameter_kunci' => ['kod_daerah' => 'daerah'], 'aktif' => true]);
    }

    /** @return array<string, mixed> */
    private function decode(string $url, string $secret): array
    {
        $this->assertSame(1, preg_match('#/embed/dashboard/([^\#]+)#', $url, $m));
        [$h, $p, $s] = explode('.', $m[1]);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$h}.{$p}", $secret, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'HS256 signature with the embedding secret');

        return json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    }

    public function test_SRS_LAP_01_metabase_embed_is_signed_with_the_users_district_locked(): void
    {
        config(['lap.metabase.secret' => $secret = str_repeat('a', 64), 'lap.metabase.url' => 'http://127.0.0.1:3030']);
        $this->board();
        $user = $this->user([...self::PEMBACA, 'lap.bi.lihat'], ['1008', '1009']);

        $e = $this->actingAs($user)->getJson('/api/v1/lap/bi/portfolio/embed')->assertOk()->json('data');
        $this->assertStringStartsWith('http://127.0.0.1:3030/embed/dashboard/', $e['url']);
        $claims = $this->decode($e['url'], $secret);
        $this->assertSame(['dashboard' => 7], $claims['resource']);
        $this->assertSame(['1008', '1009'], $claims['params']['kod_daerah']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertAudited('lihat', ['modul' => 'lap', 'nama_entiti' => 'lap_papan_bi']);

        $state = $this->decode($this->actingAs($this->user([...self::PEMBACA, 'lap.bi.lihat']))->getJson('/api/v1/lap/bi/portfolio/embed')->json('data.url'), $secret);
        $this->assertSame([], $state['params']['kod_daerah'], 'unrestricted = all districts');
    }

    public function test_SRS_LAP_01_empty_scope_extra_permission_and_unconfigured_bi_are_refused(): void
    {
        config(['lap.metabase.secret' => str_repeat('b', 64)]);
        $this->board();
        $this->board(['kod' => 'kewangan', 'nama' => 'Kewangan', 'id_metabase' => 8, 'kebenaran' => 'pjk.rahsia.lihat']);

        // A user whose district scope is EMPTY ("nothing") must never get a locked [] (= all in Metabase).
        $empty = new class implements AccessControl
        {
            public function allows(User $user, string $permission): bool
            {
                return true;
            }

            public function scope(User $user, string $type): ?array
            {
                return $type === 'daerah' ? [] : null;
            }

            public function roles(User $user): array
            {
                return [];
            }

            public function permissions(User $user): array
            {
                return [];
            }
        };
        try {
            (new Metabase($empty))->embed($this->user(), PapanBi::query()->where('kod', 'portfolio')->sole());
            $this->fail('empty scope must be refused');
        } catch (AccessDeniedHttpException) {
            $this->addToAssertionCount(1);
        }
        $plain = $this->user([...self::PEMBACA, 'lap.bi.lihat']);
        $this->actingAs($plain)->getJson('/api/v1/lap/bi/kewangan/embed')->assertForbidden();
        $this->assertSame(['portfolio'], array_column($this->actingAs($plain)->getJson('/api/v1/lap/bi')->assertOk()->json('data'), 'kod'));
        $this->actingAs($this->user(self::PEMBACA))->getJson('/api/v1/lap/bi')->assertForbidden();

        config(['lap.metabase.secret' => null]);
        $this->actingAs($plain)->getJson('/api/v1/lap/bi/portfolio/embed')->assertStatus(503);
    }

    public function test_SRS_LAP_01_bi_dashboards_are_registered_by_bi_administrators_only(): void
    {
        $payload = ['kod' => 'aduan', 'nama' => 'Aduan bulanan', 'jenis' => 'dashboard', 'id_metabase' => 3, 'parameter_kunci' => ['kod_daerah' => 'daerah']];
        $this->actingAs($this->user([...self::PEMBACA, 'lap.bi.lihat']))->postJson('/api/v1/lap/bi', $payload)->assertForbidden();
        $this->actingAs($this->user([...self::PEMBACA, 'lap.bi.lihat', 'lap.bi.kemas_kini']))->postJson('/api/v1/lap/bi', $payload)->assertCreated();
        $this->actingAs($this->user([...self::PEMBACA, 'lap.bi.lihat', 'lap.bi.kemas_kini']))->postJson('/api/v1/lap/bi', ['parameter_kunci' => ['x' => 'klasifikasi']] + $payload)->assertStatus(422);
    }
}
