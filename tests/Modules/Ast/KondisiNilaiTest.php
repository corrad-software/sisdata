<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Integrasi\Reminders\PenilaianSemula;
use App\Modules\Ast\Models\AsetAudit;
use App\Modules\Ast\Models\AsetKondisiSejarah;
use App\Modules\Ast\Models\AsetNilaiSejarah;
use App\Modules\Ast\Support\SusutNilai;
use App\Modules\Rbc\Support\BaseRoles;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class KondisiNilaiTest extends AstTestCase
{
    public function test_SRS_AST_05_condition_scale_with_full_history_date_and_officer(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/kondisi', ['status_kondisi' => 'perlu_perhatian', 'catatan' => 'Bumbung bocor'])->assertOk();
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/kondisi', ['status_kondisi' => 'kritikal', 'catatan' => 'Retakan struktur'])->assertOk();
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/kondisi', ['status_kondisi' => 'rosak'])->assertStatus(422);

        $h = AsetKondisiSejarah::query()->where('id_aset', $a->id)->orderBy('id')->get();
        $this->assertSame([null, 'baik', 'perlu_perhatian'], $h->pluck('status_lama')->all());
        $this->assertSame(['baik', 'perlu_perhatian', 'kritikal'], $h->pluck('status_baharu')->all());
        $this->assertSame($u->id, $h->last()->id_pengguna);
        $this->assertTrue($h->last()->tarikh_perubahan->isToday());
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/sejarah')->assertOk()->assertJsonCount(3, 'data.kondisi');
        $this->assertCount(1, $this->notifier->of('ast.kondisi_kritikal'));
    }

    public function test_SRS_AST_18_value_update_keeps_yearly_history_with_report_reference(): void
    {
        $kewangan = $this->user(['ast.aset.lihat', 'ast.sulit.kemas_kini']);
        $a = $this->daftar($this->unitAset(), $this->lot());
        $this->actingAs($kewangan)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 300000.555, 'tarikh_penilaian' => '2023-06-01', 'kaedah_penilaian' => 'laporan_jpph', 'id_laporan_penilaian' => 'JPPH/SEL/2023/11'])->assertCreated();
        $this->actingAs($kewangan)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 320000, 'tarikh_penilaian' => now()->toDateString(), 'kaedah_penilaian' => 'penilai_berdaftar', 'id_laporan_penilaian' => 'PB-77'])->assertCreated();
        // Back-dated report: history only, current value unchanged.
        $this->actingAs($kewangan)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 100, 'tarikh_penilaian' => '2015-01-01', 'kaedah_penilaian' => 'laporan_jpph'])->assertCreated();

        $a->refresh();
        $this->assertSame('320000.00', $a->nilai_semasa);
        $rows = AsetNilaiSejarah::query()->where('id_aset', $a->id)->orderBy('tarikh_penilaian')->get();
        $this->assertEqualsCanonicalizing([2015, 2023, (int) now()->year, (int) now()->year], $rows->pluck('tahun')->all(), 'registration (kos perolehan) + three reports');
        $this->assertSame('300000.56', $rows->firstWhere('id_laporan_penilaian', 'JPPH/SEL/2023/11')->nilai, 'DECIMAL half-up rounding');
        $this->actingAs($kewangan)->getJson('/api/v1/ast/aset/'.$a->id.'/nilai')->assertOk()->assertJsonCount(4, 'data.sejarah');
    }

    public function test_SRS_AST_23_value_change_by_unauthorised_role_rejected_and_authorised_change_audited(): void
    {
        $a = $this->daftar($this->unitAset(), $this->lot());
        // Acceptance #7: a Pegawai Operasi (base role, wildcard *.kemas_kini) may NOT change values.
        $this->assertFalse(BaseRoles::grantsByDefault(BaseRoles::PEGAWAI_OPERASI, 'ast.sulit.kemas_kini'));
        $this->assertFalse(BaseRoles::grantsByDefault(BaseRoles::PELULUS, 'ast.sulit.kemas_kini'));
        $operasi = $this->user(['ast.aset.lihat', 'ast.aset.kemas_kini', 'ast.aset.cipta']);
        $this->actingAs($operasi)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 1, 'tarikh_penilaian' => now()->toDateString(), 'kaedah_penilaian' => 'penilaian_dalaman'])->assertForbidden();
        $this->actingAs($operasi)->putJson('/api/v1/ast/aset/'.$a->id, ['nilai_semasa' => 1])->assertStatus(422);
        $this->actingAs($operasi)->postJson('/api/v1/ast/aset', ['lot_id' => $a->lot_id, 'id_kategori' => $a->id_kategori, 'nama_aset' => 'X', 'nilai_semasa' => 5])->assertStatus(422)->assertJsonValidationErrors('nilai_semasa');
        $this->assertSame('250000.00', $a->refresh()->nilai_semasa);

        $kewangan = $this->user(['ast.aset.lihat', 'ast.sulit.kemas_kini']);
        $this->actingAs($kewangan)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 275000, 'tarikh_penilaian' => now()->toDateString(), 'kaedah_penilaian' => 'laporan_jpph', 'id_laporan_penilaian' => 'JPPH-1'])->assertCreated();
        $this->assertAudited('kemas_kini', ['modul' => 'ast', 'nama_entiti' => 'aset_fizikal', 'id_rekod' => $a->id, 'id_pengguna' => $kewangan->id])
            ->assertFieldChanged('nilai_semasa', '250000.00', '275000.00');
        $t = AsetAudit::query()->where('id_aset', $a->id)->where('medan_diubah', 'nilai_semasa')->firstOrFail();
        $this->assertSame(['250000.00', '275000.00', $kewangan->id], [$t->nilai_sebelum, $t->nilai_selepas, $t->id_pengguna]);
        $this->assertTrue($t->cap_masa->isToday());
    }

    public function test_SRS_AST_23_condition_and_responsible_officer_changes_audited_append_only(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $baru = $this->user([]);
        $this->actingAs($u)->putJson('/api/v1/ast/aset/'.$a->id, ['id_pegawai_bertanggungjawab' => $baru->id, 'sebab' => 'Pertukaran tugas'])->assertOk();
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/kondisi', ['status_kondisi' => 'perlu_perhatian'])->assertOk();
        $medan = AsetAudit::query()->where('id_aset', $a->id)->pluck('medan_diubah')->all();
        $this->assertContains('id_pegawai_bertanggungjawab', $medan);
        $this->assertContains('status_kondisi', $medan);
        $this->assertAudited('kemas_kini', ['modul' => 'ast', 'nama_entiti' => 'aset_fizikal', 'id_rekod' => $a->id])->assertFieldChanged('status_kondisi', 'baik', 'perlu_perhatian');
        $this->expectException(QueryException::class);
        DB::table('aset_audit')->where('id_aset', $a->id)->update(['nilai_selepas' => 'x']);
    }

    public function test_SRS_AST_19_revaluation_reminder_when_threshold_period_reached(): void
    {
        $a = $this->daftar($this->unitAset(), $this->lot());
        $months = $this->kategori()->tempoh_ambang_penilaian_bulan;
        DB::table('aset_fizikal')->where('id', $a->id)->update(['tarikh_nilai_semasa' => now()->subMonthsNoOverflow($months)->addDays(30)->toDateString()]);
        $src = new PenilaianSemula;
        $this->assertContains(30, $src->defaultDays());
        $due = iterator_to_array($src->due(30, CarbonImmutable::today()), false);
        $this->assertCount(1, $due);
        $this->assertSame($a->nama_aset, $due[0]->data['nama_aset']);
        $this->assertArrayNotHasKey('nilai', $due[0]->data, 'no value in notifications');
        $this->assertCount(0, iterator_to_array($src->due(7, CarbonImmutable::today()), false));
    }

    public function test_SRS_AST_18_book_value_by_straight_line_depreciation_in_decimal(): void
    {
        $r = SusutNilai::kira('100000.00', 2015, 10, '10', 2020);
        $this->assertSame(['100000.00', '10000.00', '9000.00', 5, '45000.00', '55000.00'], [$r['kos'], $r['nilai_sisa'], $r['susut_tahunan'], $r['umur_tahun'], $r['susut_terkumpul'], $r['nilai_buku']]);
        $this->assertSame('10000.00', SusutNilai::kira('100000', 2000, 10, '10', 2030)['nilai_buku'], 'never below residual value');
        $this->assertSame('0.30', SusutNilai::jumlah(['0.10', '0.20']), 'no float drift');
        $this->assertCount(6, SusutNilai::jadual('100000', 2015, 10, '10', 2020));
    }
}
