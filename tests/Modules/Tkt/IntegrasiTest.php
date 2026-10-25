<?php

namespace Tests\Modules\Tkt;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\LogIntegrasi;
use App\Modules\Tkt\Models\PautanAduan;
use App\Modules\Tkt\Models\PemetaanAduan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\TiketService;

class IntegrasiTest extends TktTestCase
{
    private const SISTEM = ['tkt.integrasi.sistem'];

    private const ADMIN = ['tkt.konfigurasi.lihat', 'tkt.konfigurasi.kemas_kini'];

    /** @return array<string, string> */
    private function aduan(string $no = 'SAP-2026-0001', array $extra = []): array
    {
        return $extra + ['nombor_aduan' => $no, 'tajuk' => 'Sistem lambat semasa cetakan bil', 'keterangan' => 'Pengadu melaporkan cetakan bil mengambil masa lama.', 'kategori' => 'TEKNIKAL', 'keterukan' => 'KRITIKAL', 'modul' => 'cki'];
    }

    /** @param array<string, mixed> $overrides */
    private function parameter(array $overrides): void
    {
        $inner = app(Parameters::class);
        $this->app->instance(Parameters::class, new class($inner, $overrides) implements Parameters
        {
            public function __construct(private Parameters $inner, private array $overrides) {}

            public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void
            {
                $this->inner->declare($key, $type, $default, $label, $critical);
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->overrides) ? $this->overrides[$key] : $this->inner->get($key, $default);
            }
        });
    }

    public function test_SRS_TKT_14_central_system_opens_a_ticket_and_status_is_synced_back(): void
    {
        $tech = $this->ahli();
        $machine = $this->user(self::SISTEM);
        $res = $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertCreated()
            ->assertJsonPath('data.sedia_ada', false)->assertJsonPath('data.status', 'DITERIMA');
        $t = Tiket::query()->findOrFail($res->json('data.id_tiket'));
        $this->assertSame(['sistem_aduan_pusat', 'SAP-2026-0001', 'ralat', 1, 'cki'], [$t->saluran, $t->rujukan_sumber, $t->kategori, $t->keterukan, $t->modul]);
        $this->assertSame('SAP-2026-0001', PautanAduan::query()->where('id_tiket', $t->id)->value('nombor_aduan_berpusat'));
        $this->assertSame($t->nombor_rujukan, $this->central->pushed[0]['payload']['no_tiket_sisdata'], 'our ticket number goes back (cross-reference)');

        app(TiketService::class)->tukarStatus($t, 'disiasat', $tech);
        $this->selesai($t, $tech);
        $statuses = array_map(fn ($p) => $p['payload']['status'], $this->central->pushed);
        $this->assertSame(['DITERIMA', 'DALAM_SIASATAN', 'SELESAI'], $statuses);
        $this->assertSame('Cache dikosongkan dan konfigurasi dimuat semula.', end($this->central->pushed)['payload']['resolusi']);
        $this->assertCount(3, array_unique(array_column($this->central->pushed, 'key')), 'one idempotency key per change');
        $this->assertSame('berjaya', PautanAduan::query()->where('id_tiket', $t->id)->value('status_penyegerakan'));
    }

    public function test_SRS_TKT_16_repeated_inbound_sync_never_duplicates(): void
    {
        $machine = $this->user(self::SISTEM);
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertCreated();
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertOk()->assertJsonPath('data.sedia_ada', true);
        $this->assertSame(1, Tiket::query()->count());
        $this->assertSame(1, LogIntegrasi::query()->where('arah', 'masuk')->where('status', 'diabaikan')->count());

        $update = ['kunci_idempotensi' => 'upd-001', 'tindakan' => 'catatan', 'catatan' => 'Pengadu menambah maklumat.'];
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket/SAP-2026-0001/kemas-kini', $update)->assertOk();
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket/SAP-2026-0001/kemas-kini', $update)->assertOk();
        $this->assertSame(1, Kronologi::query()->where('jenis_catatan', 'pelapor')->count());
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket/SAP-TIADA/kemas-kini', $update + ['kunci_idempotensi' => 'x2'])->assertStatus(422);
    }

    public function test_SRS_TKT_15_mapping_is_configurable(): void
    {
        $admin = $this->user(self::ADMIN);
        $this->actingAs($admin)->postJson('/api/v1/tkt/konfigurasi/pemetaan', ['jenis' => 'kategori', 'nilai_tempatan' => 'soalan', 'nilai_berpusat' => 'PERTANYAAN_AM', 'arah' => 'masuk'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/tkt/konfigurasi/pemetaan', ['jenis' => 'status', 'nilai_tempatan' => 'baharu', 'nilai_berpusat' => 'BARU_DITERIMA', 'arah' => 'keluar'])->assertCreated();
        PemetaanAduan::query()->where('jenis', 'status')->where('nilai_tempatan', 'baharu')->where('nilai_berpusat', 'DITERIMA')->update(['aktif' => false]);

        $machine = $this->user(self::SISTEM);
        $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan('SAP-2026-0002', ['kategori' => 'pertanyaan_am', 'keterukan' => 'RENDAH']))->assertCreated()
            ->assertJsonPath('data.status', 'BARU_DITERIMA');
        $t = Tiket::query()->where('rujukan_sumber', 'SAP-2026-0002')->firstOrFail();
        $this->assertSame(['soalan', 4], [$t->kategori, $t->keterukan]);
    }

    public function test_SRS_TKT_16_failed_push_is_retried_and_listed_for_reconciliation(): void
    {
        $tech = $this->ahli();
        $admin = $this->user(self::ADMIN);
        $machine = $this->user(self::SISTEM);
        $this->central->down = true;
        $id = $this->actingAs($machine)->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertCreated()->json('data.id_tiket');
        app(TiketService::class)->tukarStatus(Tiket::query()->findOrFail($id), 'disiasat', $tech);

        $this->assertSame(2, LogIntegrasi::query()->where('arah', 'keluar')->where('status', 'gagal')->count());
        $rows = $this->actingAs($admin)->getJson('/api/v1/tkt/konfigurasi/integrasi/rekonsiliasi')->assertOk()->json('data');
        $this->assertSame(['SAP-2026-0001', 'gagal', 'disiasat'], [$rows[0]['nombor_aduan'], $rows[0]['status_penyegerakan'], $rows[0]['status_semasa']]);
        $this->assertNotNull(LogIntegrasi::query()->where('status', 'gagal')->value('cuba_seterusnya_pada'), 'back-off scheduled');

        $this->central->down = false;
        $this->actingAs($admin)->postJson('/api/v1/tkt/konfigurasi/integrasi/ulang-cuba')->assertOk()->assertJsonPath('data.berjaya', 2);
        $this->assertSame([], $this->actingAs($admin)->getJson('/api/v1/tkt/konfigurasi/integrasi/rekonsiliasi')->json('data'));
        $this->assertSame(['DITERIMA', 'DALAM_SIASATAN'], array_map(fn ($p) => $p['payload']['status'], $this->central->pushed), 'delivered in order');
        $this->assertAudited('ulang_cuba', ['modul' => 'tkt', 'id_pengguna' => $admin->id]);
        $this->actingAs($admin)->getJson('/api/v1/tkt/konfigurasi/integrasi/log?arah=keluar')->assertOk()->assertJsonPath('meta.total', 2);
        $this->artisan('tkt:segerak-pusat')->assertSuccessful();
    }

    public function test_SRS_TKT_14_api_requires_integration_account_and_respects_ip_allowlist(): void
    {
        $this->actingAs($this->user(self::JURUTEKNIK))->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertForbidden();
        $this->actingAs($this->user(self::ADMIN))->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertForbidden();
        $this->parameter(['tkt.integrasi_ip_dibenarkan' => '10.10.0.0/16']);
        $this->actingAs($this->user(self::SISTEM))->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertForbidden();
        $this->parameter(['tkt.integrasi_ip_dibenarkan' => '127.0.0.1, 10.10.0.0/16']);
        $this->actingAs($this->user(self::SISTEM))->postJson('/api/v1/tkt/integrasi/berpusat/tiket', $this->aduan())->assertCreated();
    }
}
