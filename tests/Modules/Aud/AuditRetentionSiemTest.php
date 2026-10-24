<?php

namespace Tests\Modules\Aud;

use App\Models\Setting;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Services\AuditWriter;
use App\Modules\Aud\Services\Siem\CefSiemSink;
use App\Modules\Aud\Services\Siem\SiemSink;
use App\Modules\Aud\Services\TrailAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** AUD-d: retention + archive, timestamps, SIEM (SRS-AUD-20..22, 24). */
class AuditRetentionSiemTest extends AudTestCase
{
    private function recordAt(Carbon $when, string $id): void
    {
        Carbon::setTestNow($when);
        app(AuditLogger::class)->record('kemas_kini', 'pro', 'lot_tanah', $id, changes: ['keluasan' => ['1', '2']]);
        Carbon::setTestNow();
    }

    /** Kriteria penerimaan 8. */
    public function test_SRS_AUD_20_records_past_online_retention_are_archived_by_the_scheduled_job(): void
    {
        $this->recordAt(now()->subMonths(30), 'L-OLD-1');
        $this->recordAt(now()->subMonths(26), 'L-OLD-2');
        $this->recordAt(now()->subMonths(6), 'L-NEW');

        $this->artisan('aud:arkib')->assertSuccessful();

        $this->assertSame(['L-OLD-1', 'L-OLD-2'], DB::table('audit_arkib')->orderBy('no_urutan')->pluck('id_rekod')->all());
        $this->assertSame(0, DB::table('audit_rekod')->whereIn('id_rekod', ['L-OLD-1', 'L-OLD-2'])->count());
        $this->assertSame(0, DB::table('audit_perubahan_medan')->whereNotIn('id_audit', DB::table('audit_rekod')->select('id'))->count());
        $this->assertAudited('arkib', ['modul' => 'aud'])->assertMeta(['bilangan_rekod' => 2, 'tempoh_dalam_talian_bulan' => 24]);

        // The chain still verifies across archive + online.
        $this->artisan('aud:semak-integriti')->assertSuccessful();
    }

    public function test_SRS_AUD_20_policy_is_configurable_but_never_below_24_months(): void
    {
        $admin = $this->auditor(['aud.retensi.lihat', 'aud.retensi.kemas_kini'], ['pentadbir_sistem']);

        $this->actingAs($admin)->putJson('/api/v1/aud/retensi', ['tempoh_dalam_talian_bulan' => 12])
            ->assertStatus(422)->assertJsonValidationErrors('tempoh_dalam_talian_bulan');
        $this->actingAs($admin)->putJson('/api/v1/aud/retensi', ['tempoh_dalam_talian_bulan' => 36, 'catatan' => 'Arahan Juruaudit Dalam'])
            ->assertOk()->assertJsonPath('data.tempoh_dalam_talian_bulan', 36);
        $this->actingAs($admin)->getJson('/api/v1/aud/retensi')->assertOk()->assertJsonPath('data.minimum_bulan', 24);

        // Policy change is itself audited with before/after (configuration change, SRS-AUD-01).
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'audit_retensi_polisi', 'modul' => 'aud'])
            ->assertFieldChanged('tempoh_dalam_talian_bulan', '24', '36');

        // 36 months now: a 30-month-old record stays online.
        $this->recordAt(now()->subMonths(30), 'L-30');
        $this->artisan('aud:arkib')->assertSuccessful();
        $this->assertSame(0, DB::table('audit_arkib')->count());

        // DB-level floor: the archive function refuses anything younger than 24 months.
        $this->expectException(QueryException::class);
        DB::select("select aud_arkib_pindah(now() - interval '1 month', 'x')");
    }

    public function test_SRS_AUD_21_archived_records_stay_searchable_and_archive_access_is_audited(): void
    {
        $this->recordAt(now()->subMonths(30), 'L-ARK');
        $this->artisan('aud:arkib')->assertSuccessful();
        $archivedId = (int) DB::table('audit_arkib')->value('id');
        $auditor = $this->auditor();

        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?id_rekod=L-ARK')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?id_rekod=L-ARK&sumber=arkib')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sumber', 'arkib')
            ->assertJsonPath('data.0.perubahan.0.nama_medan', 'keluasan');
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?id_rekod=L-ARK&sumber=semua')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($auditor)->getJson("/api/v1/aud/rekod/{$archivedId}?sumber=arkib")->assertOk()->assertJsonPath('data.id_rekod', 'L-ARK');
        $this->actingAs($auditor)->getJson('/api/v1/aud/kronologi?nama_entiti=lot_tanah&id_rekod=L-ARK')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($auditor)->get('/api/v1/aud/eksport?format=json&sumber=arkib')->assertOk();

        $this->assertAuditCount(3, 'capaian_arkib', ['modul' => 'aud', 'id_pengguna' => $auditor->id]);
        $this->assertAudited('lihat_kronologi')->assertMeta(['termasuk_arkib' => true]);

        // Archive itself is append-only.
        $this->expectException(QueryException::class);
        DB::table('audit_arkib')->delete();
    }

    /** Kriteria penerimaan 7 — JSON lines sink a SIEM forwarder tails. */
    public function test_SRS_AUD_22_records_are_streamed_to_siem_as_json_lines(): void
    {
        Setting::set('siem', 'ya');

        $lines = file((string) config('aud.siem.laluan'), FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lines);
        $event = json_decode($lines[0], true);
        $this->assertSame('sisdata.audit', $event['event']['dataset']);
        $this->assertSame('cipta', $event['event']['action']);
        $this->assertSame($this->seq(1), $event['event']['sequence']);
        $this->assertSame($this->newAuditRows()->value('cincangan_rekod'), $event['event']['hash']);
        $this->assertSame('settings', $event['sisdata']['nama_entiti']);

        // Searchable on the SIEM side: replay command re-sends a range.
        $this->artisan('aud:siem-strim', ['--dari' => $this->seq(1)])->assertSuccessful();
        $this->assertCount(2, file((string) config('aud.siem.laluan'), FILE_IGNORE_NEW_LINES));
    }

    public function test_SRS_AUD_22_cef_format_for_syslog_siem(): void
    {
        config(['aud.siem.pemacu' => 'cef']);
        $this->app->forgetInstance(SiemSink::class);
        $this->app->forgetInstance(AuditWriter::class);
        $this->app->forgetInstance(TrailAuditLogger::class);
        $this->app->forgetInstance(AuditLogger::class);

        app(AuditLogger::class)->record('log_masuk', 'rbc', 'users', 1, 'gagal', meta: ['pengecam' => 'a|b=c']);

        $line = trim((string) file_get_contents((string) config('aud.siem.laluan_cef')));
        $this->assertStringStartsWith('CEF:0|MAIS|SISDATA|1.0|log_masuk|rbc log_masuk users|7|', $line);
        $this->assertStringContainsString('outcome=gagal', $line);
        $this->assertStringContainsString('cs1Label=modul cs1=rbc', $line);
        $this->assertStringContainsString('cn1Label=noUrutan cn1='.$this->seq(1), $line);
        $escaped = CefSiemSink::format(['jenis_tindakan' => 'a|b', 'modul' => 'x', 'sebab_perubahan' => 'k=v']);
        $this->assertStringStartsWith('CEF:0|MAIS|SISDATA|1.0|a\\|b|x a\\|b|3|', $escaped);
        $this->assertStringContainsString('reason=k\\=v', $escaped);
    }

    public function test_SRS_AUD_24_timestamps_are_stored_in_utc_and_malaysia_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 16:30:00', 'UTC'));
        app(AuditLogger::class)->record('lihat', 'pro', 'lot_tanah', 1);
        Carbon::setTestNow();

        $row = $this->newAuditRows()->first();
        $this->assertSame('2026-06-01 16:30:00', Carbon::parse($row->tarikh_masa_utc)->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-02 00:30:00', Carbon::parse($row->tarikh_masa_tempatan)->format('Y-m-d H:i:s'));

        $this->actingAs($this->auditor())->getJson('/api/v1/aud/rekod?modul=pro')
            ->assertJsonPath('data.0.tarikh_masa_tempatan', '2026-06-02 00:30:00')
            ->assertJsonPath('data.0.tarikh_masa_utc', '2026-06-01T16:30:00.000000Z');
    }
}
