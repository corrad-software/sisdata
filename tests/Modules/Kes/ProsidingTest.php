<?php

namespace Tests\Modules\Kes;

use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Reminders\PeringatanProsiding;
use App\Modules\Kes\Reminders\PeringatanTarikhPenting;
use App\Modules\Kes\Services\Eskalasi;
use App\Modules\Kes\Services\ProsidingService;
use App\Modules\Ntf\Services\ReminderRegistry;
use App\Modules\Ntf\Services\TypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProsidingTest extends KesTestCase
{
    public function test_SRS_KES_08_proceedings_with_outcome_directions_next_date_and_chronology(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/prosiding', [
            'tarikh_prosiding' => now()->subDays(20)->setTime(9, 0)->toDateTimeString(), 'jenis_prosiding' => 'sebutan', 'status' => 'selesai',
            'hasil' => 'Pengurusan kes ditetapkan', 'arahan_mahkamah' => 'Failkan afidavit dalam 14 hari', 'tarikh_berikutnya' => now()->addDays(10)->toDateString(), 'jenis_berikutnya' => 'bicara',
            'ikut_jenis' => true,
        ])->assertCreated()->assertJsonPath('data.arahan_mahkamah', 'Failkan afidavit dalam 14 hari');
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/prosiding', [
            'tarikh_prosiding' => now()->subDays(40)->setTime(9, 0)->toDateTimeString(), 'jenis_prosiding' => 'pengurusan_kes', 'status' => 'selesai', 'hasil' => 'Awal',
        ])->assertCreated();

        $next = Prosiding::query()->where('kes_mahkamah_id', $k->id)->where('status', 'dijadualkan')->first();
        $this->assertNotNull($next, 'next date scheduled as a proceeding (calendar + reminders)');
        $this->assertSame('bicara', $next->jenis_prosiding);
        $this->assertSame(now()->addDays(10)->toDateString(), $k->refresh()->tarikh_prosiding_berikutnya?->toDateString());
        $this->assertSame('sebutan', $k->status, 'stage follows the proceeding type when asked');

        $kron = collect($this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id)->assertOk()->json('data.kronologi'));
        $dates = $kron->pluck('tarikh')->all();
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'chronology in time order');
        $this->assertTrue($kron->contains(fn ($e) => $e['jenis'] === 'prosiding' && str_contains((string) $e['butiran'], 'Failkan afidavit')));
    }

    public function test_SRS_KES_09_calendar_filtered_by_officer_type_court_and_district(): void
    {
        $officer = $this->user();
        $a = $this->kes([$this->lot()], null, ['id_pegawai_bertanggungjawab' => $officer->id]);
        $b = $this->kes([$this->lot('100901')], null, ['jenis' => 'faraid', 'mahkamah' => 'Mahkamah Tinggi Syariah Shah Alam', 'peringkat_mahkamah' => 'syariah_tinggi']);
        foreach ([$a, $b] as $k) {
            Prosiding::query()->create(['kes_mahkamah_id' => $k->id, 'tarikh_prosiding' => now()->addDays(5)->setTime(9, 0), 'jenis_prosiding' => 'bicara', 'status' => 'dijadualkan']);
        }
        $ketua = $this->user(KesTestCase::KETUA);
        $get = fn (array $q) => collect($this->actingAs($ketua)->getJson('/api/v1/kes/kalendar?'.http_build_query($q))->assertOk()->json('data'))->pluck('kes_id')->unique()->values()->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $get([]));
        $this->assertSame([$a->id], $get(['id_pegawai' => $officer->id]));
        $this->assertSame([$b->id], $get(['jenis' => 'faraid']));
        $this->assertSame([$b->id], $get(['mahkamah' => 'Syariah']));
        $this->assertSame([$a->id], $get(['kod_daerah' => '1008']));
        $this->assertSame([], $get(['dari' => now()->addDays(10)->toDateString(), 'hingga' => now()->addDays(20)->toDateString()]));

        // District officer sees only the calendar of their district.
        $district = $this->user(KesTestCase::PEGAWAI, ['1009']);
        $this->assertSame([$b->id], collect($this->actingAs($district)->getJson('/api/v1/kes/kalendar')->json('data'))->pluck('kes_id')->unique()->values()->all());
    }

    public function test_SRS_KES_10_reminders_14_7_1_days_to_officer_and_head(): void
    {
        $officer = $this->user();
        $ketua = $this->user(KesTestCase::KETUA);
        $k = $this->kes([$this->lot()], $ketua, ['id_pegawai_bertanggungjawab' => $officer->id]);
        $today = CarbonImmutable::today();
        $p = Prosiding::query()->create(['kes_mahkamah_id' => $k->id, 'tarikh_prosiding' => $today->addDays(14)->setTime(9, 0), 'jenis_prosiding' => 'bicara', 'status' => 'dijadualkan']);
        $source = app(PeringatanProsiding::class);
        $this->assertSame([14, 7, 1], $source->defaultDays());

        foreach ([14, 7, 1] as $offset) {
            $due = iterator_to_array($source->due($offset, $today->addDays(14 - $offset)));
            $this->assertCount(1, $due, "due {$offset} days before");
            $ids = collect($due[0]->recipients)->map(fn ($u) => $u->id)->all();
            $this->assertContains($officer->id, $ids);
            $this->assertContains($ketua->id, $ids);
            $this->assertSame($offset, $due[0]->data['baki_hari']);
            $this->assertSame('kes_prosiding:'.$p->id, $due[0]->key);
            $this->assertArrayNotHasKey('no_kes', $due[0]->data, 'no Rahsia court number in the notification');
        }
        $this->assertCount(0, iterator_to_array($source->due(14, $today->addDay())));

        // Document filing deadline.
        app(ProsidingService::class)->tarikhPenting($k, ['jenis' => 'pengemukaan_dokumen', 'tarikh' => $today->addDays(7)->toDateString()], $officer);
        $due = iterator_to_array(app(PeringatanTarikhPenting::class)->due(7, $today));
        $this->assertCount(1, $due);
        $this->assertSame('Pengemukaan dokumen', $due[0]->data['jenis']);
        $this->assertSame([14, 7, 1], app(PeringatanTarikhPenting::class)->defaultDays());
    }

    public function test_SRS_KES_10_reminder_types_registered_with_ntf(): void
    {
        $types = app(TypeRegistry::class);
        foreach ([PeringatanProsiding::TYPE, PeringatanTarikhPenting::TYPE, 'kes.eskalasi_tiada_tarikh'] as $code) {
            $this->assertTrue($types->isRegistered($code), $code);
        }
        $sources = app(ReminderRegistry::class)->sources();
        $this->assertContains(PeringatanProsiding::class, $sources);
        $this->assertContains(PeringatanTarikhPenting::class, $sources);
    }

    public function test_SRS_KES_11_escalate_when_no_next_date_after_the_set_period(): void
    {
        $ketua = $this->user(KesTestCase::KETUA);
        $stalled = $this->kes([$this->lot()], $ketua);
        $ok = $this->kes([$this->lot()], $ketua);
        Prosiding::query()->create(['kes_mahkamah_id' => $stalled->id, 'tarikh_prosiding' => now()->subDays(10), 'jenis_prosiding' => 'sebutan', 'status' => 'selesai']);
        Prosiding::query()->create(['kes_mahkamah_id' => $ok->id, 'tarikh_prosiding' => now()->subDays(10), 'jenis_prosiding' => 'sebutan', 'status' => 'selesai']);
        Prosiding::query()->create(['kes_mahkamah_id' => $ok->id, 'tarikh_prosiding' => now()->addDays(30), 'jenis_prosiding' => 'bicara', 'status' => 'dijadualkan']);

        Artisan::call('kes:semak-eskalasi');
        $sent = $this->notifier->of('kes.eskalasi_tiada_tarikh');
        $this->assertCount(1, $sent);
        $this->assertSame($stalled->no_rujukan, $sent[0]['data']['no_rujukan']);
        $this->assertContains($ketua->id, $sent[0]['to']);

        // Once only.
        $this->assertSame(0, app(Eskalasi::class)->jalankan());
        $this->assertNotNull(DB::table('kes_prosiding')->where('kes_mahkamah_id', $stalled->id)->value('eskalasi_dihantar_pada'));
    }
}
