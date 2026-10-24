<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;

/** Kriteria penerimaan 3: read, delete and archive from the personal list; delivery log stays. */
class InboxApiTest extends NtfTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerType('pjk.pajakan_tamat', NotificationTypes::TINGGI, ['no_lot']);
        $this->registerType('cki.cukai_matang', NotificationTypes::BIASA, ['no_lot']);
        $this->user = $this->officer();
    }

    /** @return list<int> inbox ids, newest first */
    private function seedInbox(int $count = 3, string $type = 'pjk.pajakan_tamat'): array
    {
        for ($i = 1; $i <= $count; $i++) {
            app(Notifier::class)->send($type, $this->user, ['no_lot' => "PT {$i}"], null, "{$type}:{$i}");
            $this->travel(1)->seconds();
        }

        return StatusPengguna::query()->where('pengguna_id', $this->user->id)->orderByDesc('id')->pluck('id')->all();
    }

    public function test_SRS_NTF_11_mark_read_and_unread_individually_and_in_bulk_and_filter(): void
    {
        $ids = $this->seedInbox(3);
        $api = $this->actingAs($this->user);

        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'ids' => [$ids[0]]])
            ->assertOk()->assertJsonPath('data.belum_baca', 2);
        $api->getJson('/api/v1/ntf/notifikasi?status=dibaca')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[0]);
        $api->getJson('/api/v1/ntf/notifikasi?status=belum_baca')->assertJsonCount(2, 'data');

        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'belum_baca', 'ids' => [$ids[0]]])->assertJsonPath('data.belum_baca', 3);
        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'ids' => [$ids[1], $ids[2]]])->assertJsonPath('data.dikemas_kini', 2);
        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'semua' => true])->assertJsonPath('data.belum_baca', 0);
    }

    public function test_SRS_NTF_12_logical_delete_hides_item_but_keeps_delivery_log(): void
    {
        $ids = $this->seedInbox(2);
        $logBefore = Penghantaran::query()->count();

        $this->actingAs($this->user)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'padam', 'ids' => [$ids[0]]])->assertOk();

        $this->actingAs($this->user)->getJson('/api/v1/ntf/notifikasi')->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('ntf_status_pengguna', ['id' => $ids[0]]);
        $this->assertNotNull(StatusPengguna::query()->find($ids[0])->dipadam_pada);
        $this->assertSame($logBefore, Penghantaran::query()->count());
        $this->assertCount(1, $this->audit->where('padam'));
    }

    public function test_SRS_NTF_13_archive_folder_is_kept_and_searchable(): void
    {
        $ids = $this->seedInbox(2);
        $api = $this->actingAs($this->user);

        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'arkib', 'ids' => [$ids[1]]])->assertOk();

        $api->getJson('/api/v1/ntf/notifikasi')->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?folder=arkib')->assertJsonCount(1, 'data')->assertJsonPath('data.0.diarkib', true);
        $api->getJson('/api/v1/ntf/notifikasi?folder=arkib&q=PT%201')->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?folder=arkib&q=PT%202')->assertJsonCount(0, 'data');

        $api->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'nyaharkib', 'ids' => [$ids[1]]]);
        $api->getJson('/api/v1/ntf/notifikasi')->assertJsonCount(2, 'data');
    }

    public function test_SRS_NTF_14_filter_by_type_priority_module_read_status_and_date(): void
    {
        $this->seedInbox(2, 'pjk.pajakan_tamat');
        $this->travel(2)->days();
        $this->seedInbox(1, 'cki.cukai_matang');
        $api = $this->actingAs($this->user);

        $api->getJson('/api/v1/ntf/notifikasi?jenis=cki.cukai_matang')->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?keutamaan=tinggi')->assertJsonCount(2, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?modul=cki')->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?dari='.now()->toDateString())->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?hingga='.now()->subDay()->toDateString())->assertJsonCount(2, 'data');
        $api->getJson('/api/v1/ntf/notifikasi?keutamaan=salah')->assertUnprocessable();

        $options = $api->getJson('/api/v1/ntf/notifikasi/pilihan')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['pjk', 'cki'], $options['modul']);
    }

    public function test_SRS_NTF_11_users_only_see_and_change_their_own_notifications(): void
    {
        $ids = $this->seedInbox(1);
        $other = $this->officer();

        $this->actingAs($other)->getJson('/api/v1/ntf/notifikasi')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'padam', 'ids' => $ids])
            ->assertOk()->assertJsonPath('data.dikemas_kini', 0);
        $this->assertNull(StatusPengguna::query()->find($ids[0])->dipadam_pada);

        auth()->forgetGuards();
        $this->getJson('/api/v1/ntf/notifikasi')->assertUnauthorized();
    }

    public function test_SRS_NTF_11_bell_summary_returns_unread_count_and_latest(): void
    {
        $this->seedInbox(3);

        $this->actingAs($this->user)->getJson('/api/v1/ntf/notifikasi/ringkas')
            ->assertOk()
            ->assertJsonPath('data.belum_baca', 3)
            ->assertJsonCount(3, 'data.terkini')
            ->assertJsonPath('data.terkini.0.subjek', 'Pajakan lot PT 3 tamat dalam — hari');
    }
}
