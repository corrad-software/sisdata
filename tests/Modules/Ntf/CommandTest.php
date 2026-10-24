<?php

namespace Tests\Modules\Ntf;

use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Models\StatusPengguna;
use Illuminate\Support\Facades\Mail;

class CommandTest extends NtfTestCase
{
    public function test_SRS_NTF_01_ntf_test_command_sends_a_notification(): void
    {
        $user = $this->officer();

        $this->artisan('ntf:test', ['user' => $user->email, 'type' => 'ntf.ujian', '--data' => ['catatan=Semakan manual']])
            ->expectsOutputToContain('Notifikasi ujian SISDATA')
            ->assertSuccessful();

        $this->assertSame(1, StatusPengguna::query()->where('pengguna_id', $user->id)->count());
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => str_contains($m->mesej->kandungan, 'Semakan manual'));

        $this->artisan('ntf:test', ['user' => 'tiada@example.com'])->assertFailed();
        $this->artisan('ntf:test', ['user' => (string) $user->id, 'type' => 'xyz.tiada'])->assertFailed();
    }
}
