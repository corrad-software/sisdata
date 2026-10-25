<?php

namespace Tests\Modules\Tkt;

use App\Modules\Tkt\Models\CadanganKb;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\Cadangan;

class CadanganTest extends TktTestCase
{
    public function test_SRS_TKT_06_kb_suggestions_shown_before_submit_with_at_least_three_articles(): void
    {
        $user = $this->user();
        $res = $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Saya tidak dapat menjana invois sewaan bulan ini', 'sesi' => 'sesi-1111-aaaa', 'modul' => 'pjk'])->assertOk();

        $articles = $res->json('data.artikel');
        $this->assertGreaterThanOrEqual(3, count($articles));
        $this->assertStringStartsWith('/admin/kba/artikel/', $articles[0]['pautan'], 'direct link to the full content');
        $this->assertSame($user->id, $this->kb->queries[0]['user'], 'KBA search runs with the caller (access-filtered)');
        $this->assertSame(0, Tiket::query()->count(), 'no ticket exists yet');
        $this->assertSame(count($articles), CadanganKb::query()->where('id_tiket_draf', 'sesi-1111-aaaa')->count());

        // Typing more in the same session does not duplicate the shown rows.
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Saya tidak dapat menjana invois sewaan bulan ini juga', 'sesi' => 'sesi-1111-aaaa'])->assertOk();
        $this->assertSame(count($articles), CadanganKb::query()->where('id_tiket_draf', 'sesi-1111-aaaa')->count());

        // Personal data is masked before it reaches the knowledge base.
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Carian No. KP 850101-10-5555 gagal', 'sesi' => 'sesi-1111-bbbb'])->assertOk();
        $this->assertStringNotContainsString('850101', end($this->kb->queries)['query']);
    }

    public function test_SRS_TKT_07_deflection_records_continue_or_cancel_and_flags_ineffective_articles(): void
    {
        $user = $this->user();
        // Session A: reads an article, still submits.
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Ralat semasa memuat naik dokumen PDF', 'sesi' => 'sesi-aaaa-0001'])->assertOk();
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan/klik', ['sesi' => 'sesi-aaaa-0001', 'pautan' => '/admin/kba/artikel/1'])->assertOk();
        $id = $this->actingAs($user)->postJson('/api/v1/tkt/tiket', ['tajuk' => 'Gagal muat naik PDF', 'penerangan' => 'Ralat semasa memuat naik dokumen PDF.', 'kategori' => 'ralat', 'sesi' => 'sesi-aaaa-0001'])->assertCreated()->json('data.id');
        // Session B: the article solved it.
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan', ['teks' => 'Bagaimana menukar kata laluan saya', 'sesi' => 'sesi-bbbb-0001'])->assertOk();
        $this->actingAs($user)->postJson('/api/v1/tkt/cadangan/selesai', ['sesi' => 'sesi-bbbb-0001'])->assertOk()->assertJsonPath('data.direkod', 4);

        $this->assertTrue((bool) CadanganKb::query()->where('id_tiket_draf', 'sesi-aaaa-0001')->where('id_artikel_kb', '/admin/kba/artikel/1')->value('diklik'));
        $this->assertSame(4, CadanganKb::query()->where('id_tiket_draf', 'sesi-aaaa-0001')->where('tiket_diteruskan', true)->where('id_tiket', $id)->count());
        $this->assertSame(4, CadanganKb::query()->where('id_tiket_draf', 'sesi-bbbb-0001')->where('tiket_diteruskan', false)->count());

        $m = app(Cadangan::class)->metrik();
        $this->assertSame([2, 1, 1, 50.0], [$m['sesi'], $m['diteruskan'], $m['diselesaikan_sendiri'], $m['kadar_penyelesaian_kendiri']]);
        $this->assertSame('/admin/kba/artikel/1', $m['artikel_tidak_berkesan'][0]['pautan'], 'opened but the ticket still went ahead');
    }
}
