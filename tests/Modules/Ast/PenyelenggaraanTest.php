<?php

namespace Tests\Modules\Ast;

use App\Models\User;
use App\Modules\Ast\Integrasi\Reminders\PenyelenggaraanBerjadual;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Services\PenyelenggaraanService;
use App\Modules\Ast\Support\AliranAst;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PenyelenggaraanTest extends AstTestCase
{
    public function test_SRS_AST_14_maintenance_history_records_type_date_cost_contractor_status(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $c = AsetKontraktor::query()->create(['nama_syarikat' => 'Binaan Ujian Sdn Bhd', 'status_aktif' => true]);
        $id = $this->actingAs($u)->postJson('/api/v1/ast/kerja', [
            'id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'bumbung', 'keterangan' => 'Tukar atap bocor',
            'tarikh_dijadual' => now()->addDays(3)->toDateString(), 'kos_anggaran' => 4500, 'id_kontraktor' => $c->id,
        ])->assertCreated()->assertJsonPath('data.status', 'dirancang')->json('data.id');
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'dalam_pelaksanaan'])->assertOk();
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'selesai'])->assertStatus(422)->assertJsonValidationErrors('kos');
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'selesai', 'kos' => '4380.255', 'tarikh_siap' => now()->toDateString()])->assertOk()
            ->assertJsonPath('data.kos', '4380.26')->assertJsonPath('data.nama_syarikat_pelaksana', 'Binaan Ujian Sdn Bhd');
        $r = $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/penyelenggaraan')->assertOk();
        $k = collect($r->json('data.kerja'))->firstWhere('id', $id);
        $this->assertSame(['bumbung', 'selesai', '4380.26', $c->id], [$k['jenis_penyelenggaraan'], $k['status'], $k['kos'], $k['id_kontraktor']]);
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['keterangan' => 'x'])->assertStatus(422);
        $this->assertAudited('kemas_kini', ['modul' => 'ast', 'nama_entiti' => 'rekod_penyelenggaraan', 'id_rekod' => $id]);
    }

    public function test_SRS_AST_15_schedule_generated_by_category_cycle_and_shown_in_calendar(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $kitaran = $this->kategori()->kitaran_penyelenggaraan_bulan;
        $j = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->firstOrFail();
        $this->assertSame($kitaran, $j->kitaran_bulan);
        $this->assertSame(now()->addMonthsNoOverflow($kitaran)->toDateString(), $j->tarikh_jatuh_tempoh->toDateString());

        $bulan = $j->tarikh_jatuh_tempoh->format('Y-m');
        $acara = collect($this->actingAs($u)->getJson('/api/v1/ast/kalendar?bulan='.$bulan)->assertOk()->json('data.acara'));
        $this->assertTrue($acara->contains(fn ($e) => $e['jenis'] === 'jadual' && $e['tarikh'] === $j->tarikh_jatuh_tempoh->toDateString()));
        // Next cycle is projected.
        $next = $j->tarikh_jatuh_tempoh->copy()->addMonthsNoOverflow($kitaran);
        $acara2 = collect($this->actingAs($u)->getJson('/api/v1/ast/kalendar?bulan='.$next->format('Y-m'))->assertOk()->json('data.acara'));
        $this->assertTrue($acara2->contains(fn ($e) => $e['status'] === 'unjuran' && $e['tarikh'] === $next->toDateString()));
        $this->actingAs($this->user(['ast.aset.lihat']))->getJson('/api/v1/ast/kalendar')->assertForbidden();
    }

    public function test_SRS_AST_15_completion_rolls_the_schedule_forward(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $j = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->firstOrFail();
        $j->forceFill(['tarikh_jatuh_tempoh' => now()->addDays(5)->toDateString()])->save();
        $n = app(PenyelenggaraanService::class)->janaKerjaJadual();
        $this->assertGreaterThanOrEqual(1, $n);
        $k = RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_jadual', $j->id)->firstOrFail();
        $this->assertSame('jadual', $k->sumber);
        $this->assertSame(0, app(PenyelenggaraanService::class)->janaKerjaJadual(), 'not generated twice');
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$k->id, ['status' => 'selesai', 'kos' => 800])->assertOk();
        $j->refresh();
        $this->assertSame(now()->addMonthsNoOverflow($j->kitaran_bulan)->toDateString(), $j->tarikh_jatuh_tempoh->toDateString());
        $this->assertSame(now()->toDateString(), $j->tarikh_terakhir_dilaksana?->toDateString());
    }

    public function test_SRS_AST_16_reminder_within_configured_warning_period_and_escalation_when_overdue(): void
    {
        $officer = $this->user(['ast.aset.lihat']);
        $supervisor = $this->user([]);
        $head = $this->user([]);
        $this->directory->supervisors[$officer->id] = [$supervisor->id];
        $this->directory->roles['ketua_unit_aset'] = [$head->id];
        $a = $this->daftar($this->unitAset(), $this->lot(), ['id_pegawai_bertanggungjawab' => $officer->id]);
        $jadual = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->orderBy('id')->get();
        $jadual[0]->forceFill(['tarikh_jatuh_tempoh' => now()->addDays(7)->toDateString(), 'tempoh_amaran_hari' => 14])->save();
        $jadual[1]->forceFill(['tarikh_jatuh_tempoh' => now()->addDays(7)->toDateString(), 'tempoh_amaran_hari' => 3])->save();

        $src = new PenyelenggaraanBerjadual;
        $due = iterator_to_array($src->due(7, CarbonImmutable::today()), false);
        $this->assertCount(1, $due, 'only the schedule whose warning period covers 7 days');
        $this->assertSame($officer->id, $due[0]->recipients->id);
        $this->assertSame(7, $due[0]->data['baki_hari']);

        // Overdue → escalation to the officer, the supervisor and the Ketua Unit.
        $jadual[1]->forceFill(['tarikh_jatuh_tempoh' => now()->subDays(4)->toDateString()])->save();
        $r = app(PenyelenggaraanService::class)->eskalasiTertunggak();
        $this->assertSame(1, $r['tertunggak']);
        $sent = $this->notifier->of('ast.penyelenggaraan_tertunggak');
        $this->assertCount(1, $sent);
        $this->assertEqualsCanonicalizing([$officer->id, $supervisor->id, $head->id], $sent[0]['to']);
        $this->assertSame(4, $sent[0]['data']['hari_lewat']);
        $this->assertNotNull($jadual[1]->refresh()->tarikh_eskalasi);
        $this->artisan('ast:semak-harian')->assertSuccessful();
    }

    public function test_SRS_AST_17_cost_flows_into_the_lot_cumulative_cost_chart(): void
    {
        $u = $this->user([...self::UNIT_ASET, 'pro.lot.lihat']);
        $lot = $this->lot();
        $a = $this->daftar($u, $lot);
        $b = $this->daftar($u, $lot, ['nama_aset' => 'Pagar']);
        foreach ([[$a, 1200.50, '2025-03-10'], [$b, 799.50, '2025-03-20'], [$a, 3000, '2026-01-05']] as [$asset, $kos, $tarikh]) {
            $id = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $asset->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'cat', 'keterangan' => 'Cat semula'])->json('data.id');
            $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'selesai', 'kos' => $kos, 'tarikh_siap' => $tarikh])->assertOk();
        }
        $open = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'cat', 'keterangan' => 'Belum siap', 'kos_anggaran' => 999])->json('data.id');
        $this->assertNotNull($open);

        $p = $this->actingAs($u)->getJson('/api/v1/pro/lot/'.$lot.'/profil')->assertOk();
        $k = $p->json('data.kewangan');
        $this->assertTrue($k['tersedia']);
        $tahunan = collect($k['tahunan'])->keyBy('tempoh');
        $this->assertEqualsWithDelta(2000.0, $tahunan['2025']['kos_penyelenggaraan'], 0.001);
        $this->assertEqualsWithDelta(3000.0, $tahunan['2026']['kos_penyelenggaraan'], 0.001);
        $this->assertEqualsWithDelta(-2000.0, $tahunan['2025']['pulangan_bersih'], 0.001);
        $ruang = collect($p->json('data.tab'))->firstWhere('kod', 'ruang');
        $seksyen = collect($ruang['seksyen'])->firstWhere('key', 'aset_fizikal');
        $this->assertSame('ada', $seksyen['status'], 'MOD-AST section replaces PRO\'s placeholder');
    }

    public function test_SRS_AST_17_estimate_above_threshold_needs_wfe_approval(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $id = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'bumbung', 'keterangan' => 'Ganti bumbung', 'kos_anggaran' => 85000])
            ->assertCreated()->assertJsonPath('data.status', 'menunggu_kelulusan')->json('data.id');
        $this->assertSame(85000.0, $this->workflow->of(AliranAst::PENYELENGGARAAN)[0]['context']['kos_anggaran']);
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'dalam_pelaksanaan'])->assertStatus(422);
        $this->complete(AliranAst::PENYELENGGARAAN, AliranAst::SUBJEK_KERJA, $id, 'lulus', $u->id, 'Diluluskan');
        $this->assertSame('diluluskan', RekodPenyelenggaraan::query()->withoutGlobalScope('access')->findOrFail($id)->status);
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'dalam_pelaksanaan'])->assertOk();
    }

    public function test_SRS_AST_14_contractor_sees_and_updates_only_its_own_work(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $akaun = $this->user(['ast.kerja.kemas_kini']);
        $mine = AsetKontraktor::query()->create(['nama_syarikat' => 'Kontraktor A', 'status_aktif' => true, 'id_pengguna' => $akaun->id]);
        $other = AsetKontraktor::query()->create(['nama_syarikat' => 'Kontraktor B', 'status_aktif' => true]);
        $k1 = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'elektrik', 'keterangan' => 'A', 'id_kontraktor' => $mine->id, 'kos_anggaran' => 1000])->json('data.id');
        $k2 = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'elektrik', 'keterangan' => 'B', 'id_kontraktor' => $other->id])->json('data.id');
        $this->assertCount(1, array_filter($this->notifier->of('ast.kerja_ditugaskan'), fn ($s) => in_array($akaun->id, $s['to'], true)), 'the contractor account is told about its work');

        $list = $this->actingAs($akaun)->getJson('/api/v1/ast/kerja')->assertOk();
        $this->assertSame([$k1], collect($list->json('data'))->pluck('id')->all());
        $this->assertNull($list->json('data.0.kos_anggaran'), 'contractor does not see internal estimates');
        $this->actingAs($akaun)->getJson('/api/v1/ast/kerja/'.$k2)->assertNotFound();
        $this->actingAs($akaun)->putJson('/api/v1/ast/kerja/'.$k2, ['status' => 'dalam_pelaksanaan'])->assertNotFound();
        $this->actingAs($akaun)->putJson('/api/v1/ast/kerja/'.$k1, ['kos_anggaran' => 1])->assertForbidden();
        $this->actingAs($akaun)->putJson('/api/v1/ast/kerja/'.$k1, ['status' => 'dibatalkan'])->assertStatus(422);
        $this->actingAs($akaun)->putJson('/api/v1/ast/kerja/'.$k1, ['status' => 'dalam_pelaksanaan'])->assertOk();
        $this->actingAs($akaun)->putJson('/api/v1/ast/kerja/'.$k1, ['status' => 'selesai', 'kos' => 950, 'catatan_penyelesaian' => 'Siap'])->assertOk();
        $this->actingAs($akaun)->getJson('/api/v1/ast/aset')->assertForbidden();
    }

    public function test_SRS_AST_14_ptn_maintenance_needs_become_work_orders_and_are_linked_back(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $a = $this->daftar($u, $lot);
        $need = $this->keperluanPtn($lot);
        $r = $this->actingAs($u)->postJson('/api/v1/ast/ptn/tarik')->assertOk();
        $this->assertSame(1, $r->json('data.dicipta'));
        $k = RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_keperluan_ptn', $need)->firstOrFail();
        $this->assertSame(['ptn', 'pagar', 'tinggi', $a->id], [$k->sumber, $k->jenis_penyelenggaraan, $k->keutamaan, $k->id_aset]);
        $row = DB::table('keperluan_penyelenggaraan')->where('id', $need)->first();
        $this->assertSame([$a->id, 'dijadual'], [$row->id_aset_fizikal, $row->status]);
        // Idempotent.
        $this->assertSame(0, $this->actingAs($u)->postJson('/api/v1/ast/ptn/tarik')->json('data.dicipta'));
        // Completion marks the need done in MOD-PTN.
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$k->id, ['status' => 'selesai', 'kos' => 500])->assertOk();
        $this->assertSame('selesai', DB::table('keperluan_penyelenggaraan')->where('id', $need)->value('status'));
    }

    public function test_SRS_AST_14_ptn_need_on_lot_with_several_assets_is_matched_manually(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $this->daftar($u, $lot);
        $b = $this->daftar($u, $lot, ['nama_aset' => 'Pagar sempadan']);
        $need = $this->keperluanPtn($lot);
        $this->assertSame(1, $this->actingAs($u)->postJson('/api/v1/ast/ptn/tarik')->json('data.perlu_dipadankan'));
        $list = $this->actingAs($u)->getJson('/api/v1/ast/ptn/keperluan')->assertOk()->json('data');
        $this->assertSame([$need], array_column($list, 'id'));
        $this->actingAs($u)->postJson('/api/v1/ast/ptn/keperluan/'.$need.'/paut', ['id_aset' => $b->id])->assertCreated();
        $this->assertSame($b->id, DB::table('keperluan_penyelenggaraan')->where('id', $need)->value('id_aset_fizikal'));
        $this->actingAs($u)->postJson('/api/v1/ast/ptn/keperluan/'.$need.'/paut', ['id_aset' => $b->id])->assertStatus(422);
        $this->actingAs($this->user(['ast.penyelenggaraan.lihat']))->postJson('/api/v1/ast/ptn/tarik')->assertForbidden();
    }

    public function test_contractor_update_keeps_its_own_linked_account_but_cannot_take_another_contractors(): void
    {
        $u = $this->unitAset();
        $akaunA = User::factory()->create();
        $akaunB = User::factory()->create();
        $a = AsetKontraktor::query()->create(['nama_syarikat' => 'Kontraktor A', 'status_aktif' => true, 'id_pengguna' => $akaunA->id]);
        AsetKontraktor::query()->create(['nama_syarikat' => 'Kontraktor B', 'status_aktif' => true, 'id_pengguna' => $akaunB->id]);

        // Saving a contractor again with the account it already owns must pass the unique rule (it ignores itself).
        $this->actingAs($u)->putJson('/api/v1/ast/kontraktor/'.$a->id, ['nama_syarikat' => 'Kontraktor A (kemas kini)', 'id_pengguna' => $akaunA->id])->assertOk();

        // ...but it must not be able to take an account that belongs to another contractor.
        $this->actingAs($u)->putJson('/api/v1/ast/kontraktor/'.$a->id, ['id_pengguna' => $akaunB->id])->assertUnprocessable()->assertJsonValidationErrors('id_pengguna');
    }
}
