<?php

namespace Tests\Modules\Ntf;

use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\StatusPengguna;
use Illuminate\Support\Facades\Mail;

class AnnouncementApiTest extends NtfTestCase
{
    public function test_SRS_NTF_24_admin_announces_to_groups_by_role_bahagian_and_daerah(): void
    {
        $admin = $this->admin();
        $pegawai = $this->officer();
        $klang = $this->officer();
        $this->access->scopeTo($klang, 'daerah', ['klang']);
        $wakaf = $this->officer();
        $this->access->assignRoles($wakaf, ['kerani'])->scopeTo($wakaf, 'bahagian', ['wakaf']);
        $petaling = $this->officer();
        $this->access->assignRoles($petaling, ['kerani'])->scopeTo($petaling, 'daerah', ['petaling']);

        $groups = $this->actingAs($admin)->getJson('/api/v1/ntf/pengumuman/kumpulan')->assertOk()->json('data');
        $this->assertContains(['value' => 'klang', 'label' => 'klang'], $groups['daerah']);
        $this->assertContains(['value' => 'wakaf', 'label' => 'wakaf'], $groups['bahagian']);

        $this->actingAs($admin)->postJson('/api/v1/ntf/pengumuman', [
            'tajuk' => 'Penyelenggaraan sistem',
            'kandungan' => 'SISDATA tidak dapat dicapai pada 1 Nov 2026, 10 malam.',
            'sasaran' => [
                ['jenis' => 'peranan', 'nilai' => 'pegawai'],
                ['jenis' => 'daerah', 'nilai' => 'klang'],
                ['jenis' => 'bahagian', 'nilai' => 'wakaf'],
            ],
        ])->assertCreated()->assertJsonPath('data.penerima', 3);

        $recipients = StatusPengguna::query()->pluck('pengguna_id')->all();
        $this->assertEqualsCanonicalizing([$pegawai->id, $klang->id, $wakaf->id], $recipients);
        $this->assertNotContains($petaling->id, $recipients, 'other district is out of scope');
        $mesej = Mesej::query()->sole();
        $this->assertSame('Penyelenggaraan sistem', $mesej->subjek);
        $this->assertSame($admin->id, $mesej->dicipta_oleh);
        Mail::assertSent(NotifikasiMail::class, 3);
    }

    public function test_SRS_NTF_24_validation_empty_group_and_rate_limit(): void
    {
        $admin = $this->admin();
        $this->officer();

        $this->actingAs($admin)->postJson('/api/v1/ntf/pengumuman', ['tajuk' => 'x', 'kandungan' => 'y', 'sasaran' => [['jenis' => 'peranan', 'nilai' => 'tiada_siapa']]])
            ->assertUnprocessable()->assertJsonValidationErrors('sasaran');
        $this->actingAs($admin)->postJson('/api/v1/ntf/pengumuman', ['tajuk' => 'x', 'kandungan' => 'y', 'pautan' => 'https://luar.example', 'sasaran' => [['jenis' => 'peranan', 'nilai' => 'pegawai']]])
            ->assertUnprocessable()->assertJsonValidationErrors('pautan');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($admin)->postJson('/api/v1/ntf/pengumuman', ['tajuk' => "t{$i}", 'kandungan' => 'y', 'sasaran' => [['jenis' => 'peranan', 'nilai' => 'pegawai']]])->assertCreated();
        }
        $this->actingAs($admin)->postJson('/api/v1/ntf/pengumuman', ['tajuk' => 'lagi', 'kandungan' => 'y', 'sasaran' => [['jenis' => 'peranan', 'nilai' => 'pegawai']]])
            ->assertStatus(429);
    }
}
