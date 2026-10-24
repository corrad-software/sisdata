<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Facades\DB;

class SampleCommandTest extends PjkTestCase
{
    public function test_SRS_PJK_01_synthetic_sample_data_on_existing_lots_respects_bahagian_and_is_flagged(): void
    {
        $this->user();
        foreach (['wakaf', 'baitulmal', 'hartanah'] as $i => $b) {
            for ($n = 0; $n < 5; $n++) {
                $this->lot(['bahagian_pemilik' => $b, 'sintetik' => true]);
            }
        }
        $this->artisan('pjk:sample', ['--count' => 6])->assertSuccessful();

        $this->assertSame(6, Kontrak::query()->withoutGlobalScope('access')->count());
        $this->assertSame(0, Kontrak::query()->withoutGlobalScope('access')->where('sintetik', false)->count());
        $this->assertSame(0, DB::table('penyewa')->where('sintetik', false)->count());
        $this->assertSame(0, DB::table('penyewa')->whereNotNull('no_pengenalan')->where('no_pengenalan', 'not like', '990000-%')->count());
        $premis = PremisAset::query()->withoutGlobalScope('access')->get();
        $this->assertTrue($premis->every(fn ($p) => $p->sintetik));
        $this->assertTrue($premis->where('bahagian', 'baitulmal')->every(fn ($p) => in_array($p->kategori_hartanah, ['premis_kediaman', 'premis', 'tanah'], true)));
        $this->assertGreaterThan(2, Kontrak::query()->withoutGlobalScope('access')->distinct()->count('status'));
        $this->assertSame(10, DB::table('dasar_agihan_manfaat')->count());

        // Running balances stay consistent for every synthetic ledger.
        foreach (Kontrak::query()->withoutGlobalScope('access')->pluck('id') as $id) {
            $run = 0;
            foreach (LejarTransaksi::query()->where('kontrak_id', $id)->orderBy('id')->get() as $r) {
                $run += Wang::sen($r->debit) - Wang::sen($r->kredit);
                $this->assertSame(Wang::dec($run), $r->baki_berjalan);
            }
        }
        $this->artisan('pjk:sample', ['--count' => 12])->assertFailed(); // not enough free lots left
    }
}
