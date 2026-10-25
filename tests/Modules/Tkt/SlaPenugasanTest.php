<?php

namespace Tests\Modules\Tkt;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Tkt\Models\Eskalasi;
use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\PeraturanPenugasan;
use App\Modules\Tkt\Models\SlaKonfigurasi;
use App\Modules\Tkt\Services\KalendarSokongan;
use App\Modules\Tkt\Services\SlaTiket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SlaPenugasanTest extends TktTestCase
{
    private const ADMIN = ['tkt.konfigurasi.lihat', 'tkt.konfigurasi.kemas_kini'];

    private function kl(string $at): string
    {
        return CarbonImmutable::parse((string) $at)->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i');
    }

    public function test_SRS_TKT_08_targets_follow_severity_service_hours_and_selangor_holidays(): void
    {
        DB::table('wf_cuti_umum')->insert(['tarikh' => '2026-11-03', 'nama' => 'Cuti ujian', 'negeri' => 'selangor', 'jenis' => 'negeri', 'created_at' => now(), 'updated_at' => now()]);
        $this->isnin('09:00');
        $s3 = $this->tiket(['keterukan' => 3]);
        $s1 = $this->tiket(['keterukan' => 1, 'tajuk' => 'Sistem terhenti untuk semua pengguna']);

        $this->assertSame('2026-11-02 13:00', $this->kl($s3->tarikh_sasaran_respons), 'S3 response: 4 working hours');
        $this->assertSame('2026-11-10 09:00', $this->kl($s3->tarikh_sasaran_selesai), 'S3 resolution: 5 working days, skipping the weekend and the Selangor holiday');
        $this->assertSame('2026-11-02 09:30', $this->kl($s1->tarikh_sasaran_respons), 'S1 is 24x7');
        $this->assertSame('2026-11-02 13:00', $this->kl($s1->tarikh_sasaran_selesai));
    }

    public function test_SRS_TKT_08_admin_changes_thresholds_without_code_and_history_is_kept(): void
    {
        $this->isnin('09:00');
        $admin = $this->user(self::ADMIN);
        $this->actingAs($admin)->postJson('/api/v1/tkt/konfigurasi/sla', [
            'keterukan' => 3, 'ambang_respons' => 60, 'ambang_penyelesaian' => 540, 'waktu_perkhidmatan' => 'waktu_pejabat', 'ambang_amaran_peratus' => 50,
            'hierarki_eskalasi' => [['peringkat' => 1, 'penerima' => 'penyelia', 'selepas_minit' => 0]], 'tarikh_berkuat_kuasa' => '2026-11-01', 'catatan' => 'Kontrak dikemas kini',
        ])->assertCreated();
        $this->actingAs($this->user(['tkt.konfigurasi.lihat']))->postJson('/api/v1/tkt/konfigurasi/sla', [])->assertForbidden();

        $t = $this->tiket(['keterukan' => 3]);
        $this->assertSame('2026-11-02 10:00', $this->kl($t->tarikh_sasaran_respons));
        $this->assertSame(2, SlaKonfigurasi::query()->where('keterukan', 3)->count(), 'old version kept');
        $this->assertAudited('cipta', ['nama_entiti' => 'tkt_sla_konfigurasi', 'id_pengguna' => $admin->id]);
        $rows = $this->actingAs($admin)->getJson('/api/v1/tkt/konfigurasi')->assertOk()->json('data.sla');
        $this->assertSame([true, false], array_column(array_values(array_filter($rows, fn ($r) => $r['keterukan'] === 3)), 'semasa'));
    }

    public function test_SRS_TKT_09_each_severity_warns_then_escalates_along_the_hierarchy(): void
    {
        $tech = $this->ahli('juruteknik');
        $sup = $this->ahli('penyelia');
        $mgr = $this->ahli('pengurus');
        $committee = User::factory()->create(['is_active' => true]);
        $this->app->instance(RecipientDirectory::class, new class($committee) implements RecipientDirectory
        {
            public function __construct(private User $u) {}

            public function resolve(string $target, string $value): array
            {
                return $target === self::PERANAN && $value === 'jawatankuasa_projek' ? [$this->u] : [];
            }

            public function options(string $target): array
            {
                return [];
            }
        });
        $cal = app(KalendarSokongan::class);
        $sla = app(SlaTiket::class);

        foreach ([1, 2, 3, 4] as $s) {
            $this->isnin('09:00');
            $t = $this->tiket(['keterukan' => $s, 'tajuk' => "Ujian SLA severity {$s} unik ".str_repeat('x', $s)]);
            $cfg = SlaKonfigurasi::semasa($s);
            $this->assertSame($tech->id, $t->id_juruteknik);

            // Warning at the configured % of the response period → assignee only.
            Carbon::setTestNow($cal->tambah($t->tarikh_buka, (int) ceil($cfg->ambang_respons * $cfg->ambang_amaran_peratus / 100), $cfg->waktu_perkhidmatan));
            $sla->semak();
            $warn = Eskalasi::query()->where('id_tiket', $t->id)->where('sebab', 'hampir_sla')->where('sasaran', 'respons')->get();
            $this->assertSame([$tech->id], $warn->pluck('id_penerima')->all(), "S{$s} warning");

            // Breach → level 1 supervisor; then each configured level.
            foreach ($cfg->hierarki_eskalasi as $level) {
                Carbon::setTestNow($cal->tambah($t->tarikh_sasaran_respons, $level['selepas_minit'] + 1, $cfg->waktu_perkhidmatan));
                $sla->semak();
                $expected = match ($level['penerima']) {
                    'penyelia' => $sup->id,
                    'pengurus' => $mgr->id,
                    default => $committee->id,
                };
                $got = Eskalasi::query()->where('id_tiket', $t->id)->where('sebab', 'lepas_sla')->where('sasaran', 'respons')->where('peringkat', $level['peringkat'])->pluck('id_penerima')->all();
                $this->assertSame([$expected], $got, "S{$s} level {$level['peringkat']}");
            }
            $t->refresh();
            $this->assertTrue($t->respons_dilepasi);
            $this->assertSame(count($cfg->hierarki_eskalasi), $t->peringkat_eskalasi);

            // Running again does not repeat.
            $before = Eskalasi::query()->where('id_tiket', $t->id)->count();
            $sla->semak();
            $this->assertSame($before, Eskalasi::query()->where('id_tiket', $t->id)->where('sasaran', 'respons')->count() + Eskalasi::query()->where('id_tiket', $t->id)->where('sasaran', 'selesai')->count());
        }
        $this->assertNotEmpty($this->notifier->of('tkt.sla_eskalasi'));
        $this->assertNotEmpty($this->notifier->of('tkt.sla_amaran'));
        $committeeTickets = array_unique(array_map(fn ($n) => $n['data']['keterukan'], array_filter($this->notifier->of('tkt.sla_eskalasi'), fn ($n) => $n['to'] === [$committee->id])));
        sort($committeeTickets);
        $this->assertSame(['S1', 'S2'], $committeeTickets, 'committee only for S1 / S2');
    }

    public function test_SRS_TKT_09_paused_ticket_is_not_escalated_and_manual_escalation_is_recorded(): void
    {
        $this->isnin('09:00');
        $tech = $this->ahli('juruteknik');
        $sup = $this->ahli('penyelia');
        $t = $this->tiket(['keterukan' => 2]);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'disiasat'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'menunggu_pelapor'])->assertOk();
        Carbon::setTestNow(now()->addDays(10));
        app(SlaTiket::class)->semak();
        $this->assertSame(0, Eskalasi::query()->where('id_tiket', $t->id)->count());

        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/eskalasi", ['penerima' => 'penyelia', 'sebab' => 'Memerlukan keputusan penyelia'])->assertOk();
        $row = Eskalasi::query()->where('id_tiket', $t->id)->firstOrFail();
        $this->assertSame(['manual', $sup->id, 1, $tech->id], [$row->sebab, $row->id_penerima, $row->peringkat, $row->id_pengguna]);
    }

    public function test_SRS_TKT_10_auto_assignment_by_module_category_severity_and_least_loaded(): void
    {
        $gis = Pasukan::query()->create(['kod' => 'GIS_L1', 'nama' => 'Sokongan GIS', 'aras' => 'L1', 'jenis' => 'dalaman', 'modul' => ['gis'], 'aktif' => true]);
        $specialist = $this->user(self::JURUTEKNIK);
        $gis->ahli()->create(['id_pengguna' => $specialist->id, 'peranan' => 'juruteknik', 'aktif' => true]);
        PeraturanPenugasan::query()->create(['modul' => 'gis', 'id_pasukan' => $gis->id, 'keutamaan' => 10, 'aktif' => true]);
        $busy = $this->ahli();
        $free = $this->ahli();

        $g = $this->tiket(['modul' => 'gis', 'tajuk' => 'Lapisan peta hilang']);
        $this->assertSame([$gis->id, $specialist->id], [$g->id_pasukan, $g->id_juruteknik]);

        $a = $this->tiket(['tajuk' => 'Isu pertama yang unik']);
        $b = $this->tiket(['tajuk' => 'Isu kedua lain sekali']);
        $this->assertNotSame($a->id_juruteknik, $b->id_juruteknik, 'least-loaded technician');
        $this->assertEqualsCanonicalizing([$busy->id, $free->id], [$a->id_juruteknik, $b->id_juruteknik]);
    }

    public function test_SRS_TKT_10_manual_reassignment_needs_reason_and_l2_vendor_handover(): void
    {
        $tech = $this->ahli();
        $other = $this->ahli();
        $vendor = $this->ahli('juruteknik', 'L2_VENDOR');
        $outsider = $this->user(self::JURUTEKNIK);
        $t = $this->tiket();
        $l1 = $this->pasukan()->id;
        $actor = $t->id_juruteknik === $tech->id ? $tech : $other;
        $target = $actor->is($tech) ? $other : $tech;

        $this->actingAs($actor)->postJson("/api/v1/tkt/tiket/{$t->id}/tugaskan", ['id_pasukan' => $l1, 'id_juruteknik' => $target->id])->assertStatus(422)->assertJsonValidationErrors('sebab');
        $this->actingAs($actor)->postJson("/api/v1/tkt/tiket/{$t->id}/tugaskan", ['id_pasukan' => $l1, 'id_juruteknik' => $outsider->id, 'sebab' => 'Cubaan tidak sah'])->assertStatus(422);
        $this->actingAs($actor)->postJson("/api/v1/tkt/tiket/{$t->id}/tugaskan", ['id_pasukan' => $l1, 'id_juruteknik' => $target->id, 'sebab' => 'Beban kerja diimbangkan'])->assertOk()->assertJsonPath('data.juruteknik.id', $target->id);
        $this->assertSame('Beban kerja diimbangkan', Kronologi::query()->where('id_tiket', $t->id)->where('jenis_catatan', 'tugasan')->value('kandungan'));

        $this->actingAs($target)->postJson("/api/v1/tkt/tiket/{$t->id}/serah-aras", ['id_pasukan' => $this->pasukan('L2_VENDOR')->id, 'rujukan_vendor' => 'VND-555', 'catatan' => 'Perlu pembetulan kod'])->assertOk()
            ->assertJsonPath('data.aras_sokongan', 'L2')->assertJsonPath('data.juruteknik.id', $vendor->id)
            ->assertJsonPath('data.rujukan_vendor', 'VND-555')->assertJsonPath('data.nama_vendor', 'Syarikat Pelaksana SISDATA');
        $this->assertNotNull($t->refresh()->tarikh_serah_vendor);
        $ids = array_column($this->actingAs($vendor)->getJson("/api/v1/tkt/tiket/{$t->id}/pegawai-layak")->assertOk()->json('data'), 'id');
        $this->assertContains($vendor->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
    }
}
