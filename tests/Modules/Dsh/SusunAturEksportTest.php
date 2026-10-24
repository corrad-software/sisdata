<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Models\PaparanKegemaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SusunAturEksportTest extends DshTestCase
{
    public function test_SRS_DSH_16_personal_layout_is_saved_per_user_and_can_be_reset(): void
    {
        $me = $this->user();
        $other = $this->user();
        $layout = $this->actingAs($me)->getJson('/api/v1/dsh/susun-atur?aras=eksekutif')->assertOk()->json('data');
        $kods = array_column($layout, 'kod');
        $this->assertContains('kpi:pro.keluasan', $kods);

        $this->actingAs($me)->putJson('/api/v1/dsh/susun-atur', ['aras' => 'eksekutif', 'widget' => [
            ['kod' => 'kpi:pro.keluasan', 'saiz' => 2, 'jenis_visual' => 'pie'],
            ['kod' => 'kpi:pro.bilangan_lot', 'tersembunyi' => true],
            ['kod' => 'kpi:tidak.wujud'],
        ]])->assertOk();

        $mine = $this->actingAs($me)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->json('data');
        $this->assertSame('kpi:pro.keluasan', $mine['widget'][0]['kod']);
        $this->assertSame(2, $mine['widget'][0]['saiz']);
        $this->assertSame('pie', $mine['widget'][0]['jenis_visual']);
        $this->assertTrue(collect($mine['widget'])->firstWhere('kod', 'kpi:pro.bilangan_lot')['tersembunyi']);
        $this->assertNull(collect($mine['kpi'])->firstWhere('key', 'pro.bilangan_lot'), 'hidden widgets are not computed');

        $theirs = $this->actingAs($other)->getJson('/api/v1/dsh/susun-atur?aras=eksekutif')->json('data');
        $this->assertSame($kods, array_column($theirs, 'kod'), "another user's layout is untouched");

        $this->actingAs($me)->deleteJson('/api/v1/dsh/susun-atur?aras=eksekutif')->assertOk();
        $this->assertSame($kods, array_column($this->actingAs($me)->getJson('/api/v1/dsh/susun-atur?aras=eksekutif')->json('data'), 'kod'));

        $this->actingAs($me)->putJson('/api/v1/dsh/susun-atur', ['aras' => 'eksekutif', 'widget' => [['kod' => 'kpi:pro.keluasan', 'jenis_visual' => 'tiga_dimensi']]])->assertStatus(422);
    }

    public function test_SRS_DSH_10_admin_sets_thresholds_and_widgets_without_code(): void
    {
        $this->lot();
        $this->lot();
        $admin = $this->user([...self::EKSEKUTIF, 'dsh.widget.kemas_kini']);
        $this->assertNull($this->kpis($admin)['pro.bilangan_lot']['lampu']);

        $this->actingAs($admin)->putJson('/api/v1/dsh/tetapan/ambang/pro.bilangan_lot', ['arah' => 'tinggi', 'nilai_hijau' => 10, 'nilai_kuning' => 2])->assertOk();
        $kpi = $this->kpis($admin)['pro.bilangan_lot'];
        $this->assertSame('kuning', $kpi['lampu']);
        $this->assertSame('pentadbir', $kpi['ambang']['sumber']);
        $this->assertAudited('cipta', ['nama_entiti' => 'dsh_ambang_indikator']);

        $this->actingAs($admin)->putJson('/api/v1/dsh/tetapan/ambang/pro.bilangan_lot', ['arah' => 'tinggi', 'nilai_hijau' => 1, 'nilai_kuning' => 5])->assertStatus(422);

        // Widget definition: retitle, change visual, move to Operasi only.
        $this->actingAs($admin)->putJson('/api/v1/dsh/tetapan/widget/kpi:pro.keluasan', ['tajuk' => 'Keluasan portfolio', 'jenis_visual' => 'bar', 'aras_dashboard' => ['operasi'], 'susunan' => 1])->assertOk();
        $exec = collect($this->actingAs($admin)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->json('data.widget'));
        $this->assertNull($exec->firstWhere('kod', 'kpi:pro.keluasan'));
        $ops = collect($this->actingAs($admin)->getJson('/api/v1/dsh/dashboard?aras=operasi')->json('data.widget'));
        $this->assertSame('Keluasan portfolio', $ops->firstWhere('kod', 'kpi:pro.keluasan')['tajuk']);
        $this->assertSame('bar', $ops->firstWhere('kod', 'kpi:pro.keluasan')['jenis_visual']);

        $this->actingAs($this->user())->putJson('/api/v1/dsh/tetapan/ambang/pro.bilangan_lot', ['arah' => 'tinggi', 'nilai_hijau' => 1, 'nilai_kuning' => 0])->assertForbidden();
        $this->actingAs($this->user())->getJson('/api/v1/dsh/tetapan')->assertForbidden();
    }

    public function test_SRS_DSH_14_export_pdf_and_excel_with_filters_timestamp_and_watermark(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $user = $this->user();
        $user->forceFill(['name' => 'Pegawai Eksport'])->save();

        $pdf = $this->actingAs($user)->get('/api/v1/dsh/eksport?format=pdf&aras=eksekutif&kod_daerah=1008')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertAudited('eksport', ['modul' => 'dsh', 'nama_entiti' => 'dashboard', 'id_pengguna' => $user->id]);
        $meta = json_decode((string) DB::table('audit_rekod')->where('jenis_tindakan', 'eksport')->orderByDesc('no_urutan')->value('meta'), true);
        $this->assertSame(['kod_daerah' => '1008'], $meta['penapis']);

        $xlsx = $this->actingAs($user)->get('/api/v1/dsh/eksport?format=xlsx&aras=eksekutif&kod_daerah=1008')->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'dsh').'.xlsx';
        file_put_contents($file, $xlsx->getContent());
        $book = IOFactory::load($file);
        $sheet = $book->getActiveSheet();
        $text = implode("\n", array_map(fn ($r) => implode(' ', array_map('strval', $r)), $sheet->toArray()));
        @unlink($file);
        $this->assertStringContainsString('Daerah: 1008', $text);
        $this->assertStringContainsString('Data dikira pada', $text);
        $this->assertStringContainsString('Pegawai Eksport', $text, 'watermark carries the user identity');
        $this->assertStringContainsString('Jumlah tanah / aset', $text);
        $this->assertStringContainsString('Pegawai Eksport', (string) $sheet->getHeaderFooter()->getOddHeader());

        $this->actingAs($this->user(['dsh.dashboard.lihat', 'dsh.eksekutif.lihat']))->get('/api/v1/dsh/eksport?format=pdf&aras=eksekutif')->assertForbidden();
    }

    public function test_SRS_DSH_14_filtered_views_saved_as_personal_favourites(): void
    {
        $me = $this->user();
        $this->actingAs($me)->postJson('/api/v1/dsh/kegemaran', ['nama_paparan' => 'Petaling 2025', 'aras' => 'eksekutif', 'parameter_penapis' => ['kod_daerah' => '1008', 'tahun' => 2025], 'lalai' => true])->assertCreated();
        $this->actingAs($me)->postJson('/api/v1/dsh/kegemaran', ['nama_paparan' => 'Sepang', 'parameter_penapis' => ['kod_daerah' => '1009'], 'lalai' => true])->assertCreated();
        $views = $this->actingAs($me)->getJson('/api/v1/dsh/kegemaran')->assertOk()->json('data');
        $this->assertCount(2, $views);
        $this->assertSame('Sepang', $views[0]['nama_paparan'], 'one default view');
        $this->assertEquals(['kod_daerah' => '1008', 'tahun' => 2025], collect($views)->firstWhere('nama_paparan', 'Petaling 2025')['parameter_penapis']);

        $other = $this->user();
        $this->assertSame([], $this->actingAs($other)->getJson('/api/v1/dsh/kegemaran')->json('data'));
        $id = PaparanKegemaran::query()->where('nama_paparan', 'Sepang')->value('id');
        $this->actingAs($other)->deleteJson("/api/v1/dsh/kegemaran/{$id}")->assertNotFound();
        $this->actingAs($me)->deleteJson("/api/v1/dsh/kegemaran/{$id}")->assertOk();
        $this->actingAs($me)->postJson('/api/v1/dsh/kegemaran', ['nama_paparan' => 'x', 'parameter_penapis' => ['kod_daerah' => 'abc']])->assertStatus(422);
    }

    public function test_SRS_DSH_12_every_widget_and_the_dashboard_carry_a_data_timestamp(): void
    {
        Carbon::setTestNow('2026-10-25 09:00:00');
        $this->lot();
        $res = $this->actingAs($this->user())->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->assertOk();
        $this->assertNotNull($res->json('data.dikira_pada'));
        $this->assertNotNull($res->json('data.data_terakhir'), 'latest source record change');
        foreach ($res->json('data.kpi') as $kpi) {
            $this->assertNotNull($kpi['dikira_pada'], $kpi['key']);
        }
        $this->assertLessThanOrEqual(900, $res->json('data.ttl_saat'));
        Carbon::setTestNow();
    }

    public function test_SRS_DSH_13_dashboard_reads_never_write_other_modules_records(): void
    {
        $lot = $this->lot();
        $this->title($lot);
        $user = $this->user([...self::EKSEKUTIF, 'dsh.rahsia.lihat', 'gis.peta.lihat']);
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\s+(into\s+)?"?([a-z_]+)"?/i', $q->sql, $m)) {
                $writes[] = strtolower($m[3]);
            }
        });
        $this->actingAs($user)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/dsh/kpi/pro.bilangan_lot/senarai')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/dsh/inbox')->assertOk();

        $foreign = array_filter(array_unique($writes), fn ($t) => ! str_starts_with($t, 'dsh_') && ! str_starts_with($t, 'audit_') && ! in_array($t, ['cache', 'cache_locks', 'sessions', 'log_akses_profil'], true));
        $this->assertSame([], array_values($foreign), 'MOD-DSH wrote to: '.implode(', ', $foreign));
    }
}
