<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Models\Langganan;
use App\Modules\Dsh\Models\Snapshot;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Dsh\Support\RecordingNotifier;

class RingkasanBerkalaTest extends DshTestCase
{
    private RecordingNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Notifier::class, $this->notifier = new RecordingNotifier);
    }

    public function test_SRS_DSH_14_scheduled_digest_sends_a_snapshot_summary_through_ntf(): void
    {
        $lot = $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100901']);
        DB::table('penilaian')->insert(['id' => (string) str()->uuid(), 'lot_id' => $lot, 'tarikh_penilaian' => '2025-01-01', 'nilai_pasaran' => 123456, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->user([...self::EKSEKUTIF, 'dsh.rahsia.lihat'], ['1008']);

        $this->actingAs($user)->putJson('/api/v1/dsh/langganan', ['aras' => 'pengurusan', 'kekerapan' => 'mingguan', 'penapis' => ['tahun' => 2026]])->assertOk();
        $this->artisan('dsh:ringkasan-berkala')->assertSuccessful();

        $this->assertCount(1, $this->notifier->sent);
        $sent = $this->notifier->sent[0];
        $this->assertSame('dsh.ringkasan_berkala', $sent['type']);
        $this->assertSame([$user->id], $sent['recipients']);
        $this->assertStringContainsString('Jumlah tanah / aset: 1 lot', (string) $sent['data']['ringkasan'], 'computed as the subscriber, in their scope');
        $this->assertStringNotContainsString('123', (string) $sent['data']['ringkasan'], 'Rahsia figures never go into a notification');
        $this->assertStringNotContainsString('Nilai portfolio', (string) $sent['data']['ringkasan']);

        $snap = Snapshot::query()->sole();
        $this->assertSame('berjadual', $snap->sumber);
        $this->assertSame('/admin/dsh/snapshot/'.$snap->id, $sent['link']);
        $this->assertNotNull(Langganan::query()->sole()->terakhir_dihantar);

        // Not due again until next week.
        $this->artisan('dsh:ringkasan-berkala')->assertSuccessful();
        $this->assertCount(1, $this->notifier->sent);
        Carbon::setTestNow(now()->addDays(7));
        $this->artisan('dsh:ringkasan-berkala')->assertSuccessful();
        $this->assertCount(2, $this->notifier->sent);
        Carbon::setTestNow();

        // The snapshot can be exported later — by its owner only.
        $this->actingAs($user)->get("/api/v1/dsh/snapshot/{$snap->id}/eksport?format=pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertAudited('eksport', ['nama_entiti' => 'dsh_snapshot', 'id_rekod' => $snap->id]);
        $this->actingAs($this->user())->getJson("/api/v1/dsh/snapshot/{$snap->id}")->assertNotFound();
    }

    public function test_SRS_DSH_14_manual_snapshot_and_subscription_permissions(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/dsh/snapshot', ['aras' => 'eksekutif'])->assertCreated()->json('data.id');
        $this->actingAs($user)->getJson('/api/v1/dsh/snapshot')->assertOk()->assertJsonPath('data.0.id', $id);

        $noSub = $this->user(['dsh.dashboard.lihat']);
        $this->actingAs($noSub)->putJson('/api/v1/dsh/langganan', ['aras' => 'operasi', 'kekerapan' => 'harian'])->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/dsh/langganan', ['aras' => 'eksekutif', 'kekerapan' => 'setiap_saat'])->assertStatus(422);

        // A subscriber who lost the level is skipped.
        $this->actingAs($user)->putJson('/api/v1/dsh/langganan', ['aras' => 'eksekutif', 'kekerapan' => 'harian'])->assertOk();
        $user->skop()->create(['jenis_skop' => 'daerah', 'nilai_skop' => '1008']);
        $this->artisan('dsh:ringkasan-berkala')->assertSuccessful();
        $this->assertSame([], $this->notifier->sent);
    }
}
