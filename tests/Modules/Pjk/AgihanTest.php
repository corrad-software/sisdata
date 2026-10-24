<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DasarAgihanManfaat;
use App\Modules\Pjk\Models\KiraanAgihanManfaat;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Services\AgihanService;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Services\SaluranPemerolehan;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgihanTest extends PjkTestCase
{
    private function policy(string $kategori, string $status, string $kadar, string $from = '2026-01-01', int $approver = 0): DasarAgihanManfaat
    {
        $user = $this->user(['pjk.dasar.kemas_kini', 'pjk.agihan.lihat']);
        $res = $this->actingAs($user)->postJson('/api/v1/pjk/dasar', [
            'kategori_hartanah' => $kategori, 'status_perolehan' => $status, 'kadar_agihan' => $kadar,
            'penerima_manfaat' => [['kod' => 'asnaf', 'nama' => 'Asnaf', 'peratus' => '60'], ['kod' => 'tabung', 'nama' => 'Tabung wakaf', 'peratus' => '40']],
            'tarikh_mula_kuasa' => $from, 'rujukan_kelulusan' => 'MMKN 1/2026',
        ])->assertCreated();
        $d = DasarAgihanManfaat::query()->findOrFail($res->json('data.id'));
        $this->actingAs($user)->postJson("/api/v1/pjk/dasar/{$d->id}/hantar")->assertOk();
        $this->complete(Aliran::DASAR, 'dasar_agihan_manfaat', $d->id, 'lulus', $approver ?: $this->user(['pjk.agihan.lihat'])->id);

        return $d->refresh();
    }

    /** A premise whose contract collected $amount (sen) of rent in October 2026. */
    private function collected(string $kategori, string $bahagian, string $amount): PremisAset
    {
        $premis = $this->premis(['kategori_hartanah' => $kategori], $this->lot(['bahagian_pemilik' => $bahagian]));
        $k = $this->active($this->user(), null, ['premis_id' => $premis->id, 'tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30', 'kadar' => $amount, 'deposit' => '0']);
        $b = Bayaran::query()->create(['no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $k->id, 'saluran' => 'kaunter', 'amaun' => $amount, 'tarikh_bayaran' => '2026-10-10', 'status' => 'menunggu_pengesahan']);
        app(BayaranService::class)->settle($b, null, false);

        return $premis;
    }

    public function test_SRS_PJK_22_rates_recorded_for_all_five_categories_with_version_dates_and_approval_reference(): void
    {
        foreach (array_keys(Kod::KATEGORI_HARTANAH) as $kategori) {
            $this->policy($kategori, 'wakaf', '90');
            $this->policy($kategori, 'belian', '60');
        }
        $this->assertSame(10, DasarAgihanManfaat::query()->where('status', 'aktif')->count());

        // New version supersedes from its start date; the old one keeps its history.
        $v2 = $this->policy('bazar', 'wakaf', '80', '2026-07-01');
        $this->assertSame(2, $v2->versi_dasar);
        $v1 = DasarAgihanManfaat::query()->where('kategori_hartanah', 'bazar')->where('status_perolehan', 'wakaf')->where('versi_dasar', 1)->sole();
        $this->assertSame(['digantikan', '2026-06-30'], [$v1->status, $v1->tarikh_tamat?->format('Y-m-d')]);
        $svc = app(AgihanService::class);
        $this->assertSame(1, $svc->applicable('bazar', 'wakaf', Carbon::parse('2026-03-01'))?->versi_dasar);
        $this->assertSame(2, $svc->applicable('bazar', 'wakaf', Carbon::parse('2026-08-01'))?->versi_dasar);

        $this->actingAs($this->user(['pjk.dasar.kemas_kini']))->postJson('/api/v1/pjk/dasar', [
            'kategori_hartanah' => 'tanah', 'status_perolehan' => 'wakaf', 'kadar_agihan' => '50', 'tarikh_mula_kuasa' => '2026-01-01',
            'penerima_manfaat' => [['kod' => 'a', 'nama' => 'A', 'peratus' => '50']],
        ])->assertStatus(422)->assertJsonValidationErrors(['penerima_manfaat']);
        $this->actingAs($this->user(['pjk.agihan.lihat']))->postJson('/api/v1/pjk/dasar', [])->assertForbidden();
    }

    public function test_SRS_PJK_23_wakaf_or_purchased_decided_from_the_acquisition_channel(): void
    {
        $svc = app(SaluranPemerolehan::class);
        $wakafLot = $this->lot(['bahagian_pemilik' => 'hartanah']);
        $boughtLot = $this->lot(['bahagian_pemilik' => 'wakaf']);
        foreach ([[$wakafLot, 'wakaf'], [$boughtLot, 'pembelian_terus']] as [$lot, $channel]) {
            DB::table('pemerolehan')->insert([
                'id' => (string) Str::uuid(), 'no_kes' => 'PER-'.Str::random(6), 'id_saluran' => DB::table('saluran_pemerolehan')->where('kod_saluran', $channel)->value('id'),
                'tajuk' => 'Ujian', 'lot_id' => $lot, 'tarikh_mula_kes' => '2020-01-01', 'status_pemerolehan' => 'selesai', 'kod_bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 90, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertSame('wakaf', $svc->forLot($wakafLot, 'hartanah'));
        $this->assertSame('belian', $svc->forLot($boughtLot, 'wakaf'));
        $this->assertSame('wakaf', $svc->forLot($this->lot(), 'wakaf')); // no acquisition record → owning bahagian
        $this->assertSame('belian', $svc->statusFor($this->premis(['saluran_pemerolehan' => 'belian'], $wakafLot))); // explicit wins
    }

    public function test_SRS_PJK_24_acceptance_6_calculation_for_five_places_matches_manual_figures(): void
    {
        $this->policy('premis', 'wakaf', '90');
        $this->policy('premis', 'belian', '60');
        $this->policy('bazar', 'wakaf', '85.5');
        $this->policy('tanah', 'belian', '50');
        $this->policy('premis_kediaman', 'wakaf', '95');
        $places = [
            [$this->collected('premis', 'wakaf', '1000.00'), '900.00', ['540.00', '360.00']],
            [$this->collected('premis', 'hartanah', '1000.00'), '600.00', ['360.00', '240.00']],
            [$this->collected('bazar', 'wakaf', '333.33'), '285.00', ['171.00', '114.00']], // 333.33 × 85.5% = 284.997 → 285.00
            [$this->collected('tanah', 'baitulmal', '2500.00'), '1250.00', ['750.00', '500.00']],
            [$this->collected('premis_kediaman', 'wakaf', '777.77'), '738.88', ['443.33', '295.55']], // 738.8815 → 738.88; 60% = 443.328 → 443.33
        ];
        $calc = $this->user(['pjk.agihan.lihat', 'pjk.agihan.cipta']);
        $out = collect($this->actingAs($calc)->postJson('/api/v1/pjk/agihan/kira', ['tempoh' => '2026-10'])->assertOk()->json('data'))->keyBy('premis_id');
        foreach ($places as [$premis, $amount, $split]) {
            $row = $out[$premis->id];
            $this->assertSame($amount, $row['amaun_agihan'], $premis->kategori_hartanah);
            $this->assertSame($split, array_column($row['pecahan_penerima'], 'amaun'));
        }
        $k = KiraanAgihanManfaat::query()->where('premis_id', $places[0][0]->id)->sole();
        $this->assertSame(['draf', 1, '1000.00'], [$k->status_pengesahan, $k->versi_dasar, $k->hasil_kutipan]);
        $this->assertAudited('cipta', ['nama_entiti' => 'kiraan_agihan_manfaat', 'id_rekod' => $k->id]);

        // Confirmation by a second officer only.
        $this->actingAs($this->user(['pjk.agihan.lihat', 'pjk.agihan.lulus']))->postJson('/api/v1/pjk/agihan/sahkan', ['ids' => [$k->id]])->assertOk();
        $this->assertSame('disahkan', $k->refresh()->status_pengesahan);
        $k2 = KiraanAgihanManfaat::query()->where('premis_id', $places[1][0]->id)->sole();
        $calc2 = $this->user(['pjk.agihan.lihat', 'pjk.agihan.lulus']);
        $k2->forceFill(['id_pengira' => $calc2->id])->save();
        $this->actingAs($calc2)->postJson('/api/v1/pjk/agihan/sahkan', ['ids' => [$k2->id]])->assertStatus(422);
    }

    public function test_SRS_PJK_25_simulation_with_proposed_rates_is_not_stored_and_portal_sees_only_confirmed(): void
    {
        $this->policy('premis', 'wakaf', '90');
        $premis = $this->collected('premis', 'wakaf', '1000.00');
        $user = $this->user(['pjk.agihan.lihat', 'pjk.agihan.cipta']);

        $sim = $this->actingAs($user)->postJson('/api/v1/pjk/agihan/simulasi', ['tempoh' => '2026-10', 'kadar' => ['premis:wakaf' => '75']])->assertOk()->json('data.0');
        $this->assertSame(['750.00', true], [$sim['amaun_agihan'], $sim['simulasi']]);
        $this->assertSame(0, KiraanAgihanManfaat::query()->count());

        $this->actingAs($user)->postJson('/api/v1/pjk/agihan/kira', ['tempoh' => '2026-10'])->assertOk();
        $this->getJson('/api/v1/pjk/awam/agihan')->assertOk()->assertJsonCount(0, 'data');
        KiraanAgihanManfaat::query()->update(['status_pengesahan' => 'disahkan']);
        $pub = $this->getJson('/api/v1/pjk/awam/agihan?tempoh=2026-10')->assertOk()->json('data.0');
        $this->assertSame(['900.00', 1], [$pub['agihan'], $pub['bil_tempat']]);
        $this->assertArrayNotHasKey('kontrak_id', $pub);
        $this->assertSame($premis->kod_daerah, $pub['kod_daerah']);
    }

    public function test_SRS_PJK_24_deposits_are_not_revenue(): void
    {
        $this->policy('premis_perniagaan', 'wakaf', '90');
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
        $b = Bayaran::query()->create(['no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $k->id, 'saluran' => 'kaunter', 'amaun' => '4500.00', 'tarikh_bayaran' => '2026-10-10', 'status' => 'menunggu_pengesahan']);
        app(BayaranService::class)->settle($b, null, false);
        $rows = app(AgihanService::class)->calculate('2026-10', null, $this->user(['pjk.agihan.cipta']));
        $this->assertSame('1500.00', $rows[0]['hasil_kutipan']);
        $this->assertInstanceOf(Kontrak::class, $k);
    }
}
