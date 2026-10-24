<?php

namespace Tests\Modules\Ntf;

use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Models\KeutamaanPengguna;
use App\Modules\Ntf\Models\Penghantaran;
use Illuminate\Support\Facades\Mail;

class PreferenceApiTest extends NtfTestCase
{
    public function test_SRS_NTF_09_user_chooses_channels_or_opts_out_of_optional_types(): void
    {
        $jenis = $this->registerType('pjk.pajakan_tamat', NotificationTypes::TINGGI, ['no_lot']);
        $user = $this->officer();
        $api = $this->actingAs($user);

        $row = collect($api->getJson('/api/v1/ntf/keutamaan')->assertOk()->json('data'))->firstWhere('kod', 'pjk.pajakan_tamat');
        $this->assertFalse($row['wajib']);
        $this->assertEqualsCanonicalizing(['dalam_sistem', 'emel'], $row['saluran']);

        $api->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => ['dalam_sistem']]]])->assertOk();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'A']);
        $this->assertSame(['dalam_sistem'], Penghantaran::query()->pluck('saluran')->all());
        Mail::assertNothingSent();

        $api->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => false, 'saluran' => []]]])->assertOk();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'B']);
        $this->assertSame(1, Penghantaran::query()->count());

        $api->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => []]]])->assertUnprocessable();
        $api->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => ['faks']]]])->assertUnprocessable();
    }

    public function test_SRS_NTF_10_mandatory_types_cannot_be_turned_off_and_are_shown_locked(): void
    {
        // Kriteria penerimaan 4.
        $jenis = $this->registerType('kes.prosiding', NotificationTypes::KRITIKAL, ['no_kes'], mandatory: true);
        $user = $this->officer();
        $api = $this->actingAs($user);

        $row = collect($api->getJson('/api/v1/ntf/keutamaan')->json('data'))->firstWhere('kod', 'kes.prosiding');
        $this->assertTrue($row['wajib']);
        $this->assertTrue($row['aktif']);

        $api->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => false, 'saluran' => []]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.aktif');

        // Even a preference row written behind the API's back is ignored.
        KeutamaanPengguna::query()->create(['pengguna_id' => $user->id, 'jenis_id' => $jenis->id, 'aktif' => false, 'saluran' => []]);
        app(Notifier::class)->send('kes.prosiding', $user, ['no_kes' => 'BA-1']);
        Mail::assertSent(NotifikasiMail::class);
        $this->assertSame(2, Penghantaran::query()->count());
    }
}
