<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Support\ChannelCatalog;

class SaluranTest extends PerTestCase
{
    public function test_SRS_PER_01_eleven_channels_with_code_lampiran_h_icon_colour_bahagian_and_target(): void
    {
        $response = $this->actingAs($this->officer())->getJson('/api/v1/per/saluran')->assertOk();

        $channels = collect($response->json('data'));
        $this->assertCount(11, $channels);
        $this->assertSame([
            'pemberimilikan_5a', 'serah_milik', 'faraid', 'daftar_kematian', 'perintah_arb', 'serahan_hakmilik',
            'perintah_mahkamah', 'pusaka_jkptgs', 'wakaf', 'pembelian_terus', 'tanah_warta',
        ], $channels->pluck('kod')->all());
        $this->assertCount(11, $channels->pluck('warna')->unique(), 'Setiap saluran mempunyai warna tersendiri (SRS-PER-28).');
        $this->assertCount(11, $channels->pluck('rujukan_lampiran_h')->unique());
        foreach ($channels as $c) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $c['warna']);
            $this->assertMatchesRegularExpression('/^A-6\.\d{2}$/', $c['rujukan_lampiran_h']);
            $this->assertNotEmpty($c['ikon']);
            $this->assertContains($c['bahagian'], array_keys(ChannelCatalog::BAHAGIAN));
            $this->assertGreaterThan(0, $c['tempoh_sasaran_hari']);
        }
        $this->assertSame('wakaf', $channels->firstWhere('kod', 'wakaf')['bahagian']);
        $this->assertSame('baitulmal', $channels->firstWhere('kod', 'faraid')['bahagian']);
    }

    /** Kriteria penerimaan 1: each channel shows its own, correct form and checklist. */
    public function test_SRS_PER_01_each_channel_has_its_own_form_fields_and_mandatory_checklist(): void
    {
        $channels = collect($this->actingAs($this->officer())->getJson('/api/v1/per/saluran')->json('data'))->keyBy('kod');

        $fieldSets = $channels->map(fn ($c) => collect($c['medan'])->pluck('kod')->sort()->implode(','));
        $this->assertCount(11, $fieldSets->unique(), 'Borang setiap saluran berbeza.');

        $this->assertContains('no_mesyuarat_mmkn', collect($channels['pemberimilikan_5a']['medan'])->pluck('kod'));
        $this->assertContains('tarikh_akhir_bayaran', collect($channels['pemberimilikan_5a']['medan'])->pluck('kod'));
        $this->assertContains('status_pengesahan_syariah', collect($channels['wakaf']['medan'])->pluck('kod'));
        $this->assertContains('rujukan_e_faraid', collect($channels['faraid']['medan'])->pluck('kod'));
        $this->assertContains('no_warta_kerajaan', collect($channels['tanah_warta']['medan'])->pluck('kod'));
        $this->assertContains('no_perjanjian_jual_beli', collect($channels['pembelian_terus']['medan'])->pluck('kod'));

        $this->assertSame(
            ['Salinan permohonan e-Tanah', 'Keputusan / kertas MMKN', 'Notis 5A asal', 'Resit bayaran premium dan notis 5A', 'Pelan pra-perhitungan atau pelan akui', 'Hak milik yang diterima', 'Memo penerimaan', 'Rekod daftar peti besi'],
            collect($channels['pemberimilikan_5a']['senarai_semak'])->pluck('nama_dokumen')->all(),
        );
        $this->assertContains('Borang ikrar wakaf (akad)', collect($channels['wakaf']['senarai_semak'])->pluck('nama_dokumen'));
        $this->assertContains('Petikan Warta Kerajaan Negeri Selangor', collect($channels['tanah_warta']['senarai_semak'])->pluck('nama_dokumen'));
        foreach ($channels as $c) {
            $this->assertNotEmpty($c['senarai_semak']);
            $this->assertContains('kelulusan', collect($c['peringkat'])->pluck('jenis'));
            $this->assertSame('simpanan', collect($c['peringkat'])->last()['jenis']);
        }
    }

    public function test_SRS_PER_01_unknown_channel_field_rejected_and_types_validated(): void
    {
        $user = $this->officer();
        $this->createCase($user, 'tanah_warta', ['butiran' => ['no_mesyuarat_mmkn' => 'X']])->assertStatus(422);
        $this->createCase($user, 'tanah_warta', ['butiran' => ['tarikh_warta' => '31/12/2025']])->assertStatus(422)->assertJsonValidationErrors('tarikh_warta');
        $this->createCase($user, 'tanah_warta', ['butiran' => ['jenis_pewartaan' => 'bukan_pilihan']])->assertStatus(422);
        $this->createCase($user, 'tanah_warta', ['butiran' => ['jenis_pewartaan' => 'tapak_masjid_surau', 'tarikh_warta' => '2025-12-31']])->assertCreated();
    }

    public function test_SRS_PER_17_stages_and_targets_configurable_per_channel_for_new_cases(): void
    {
        $admin = $this->officer([...self::PEGAWAI, 'per.saluran.kemas_kini']);
        $channel = collect($this->actingAs($admin)->getJson('/api/v1/per/saluran/tanah_warta')->json('data.peringkat'));
        $semakan = $channel->firstWhere('kod', 'semakan');

        $this->actingAs($this->officer())->putJson('/api/v1/per/saluran/tanah_warta', ['tempoh_sasaran_hari' => 60])->assertForbidden();

        $this->actingAs($admin)->putJson('/api/v1/per/saluran/tanah_warta', [
            'tempoh_sasaran_hari' => 75,
            'peringkat' => [['id' => $semakan['id'], 'tempoh_sasaran_hari' => 5, 'nama_peringkat' => 'Semakan Unit Warta']],
        ])->assertOk()->assertJsonPath('data.tempoh_sasaran_hari', 75);

        $case = $this->newCase($admin, 'tanah_warta');
        $this->assertSame(75, $case->tempoh_sasaran_hari);
        $stage = $case->peringkat()->where('kod_peringkat', 'semakan')->firstOrFail();
        $this->assertSame(5, $stage->tempoh_sasaran_hari);
        $this->assertSame('Semakan Unit Warta', $stage->nama_peringkat);
        $this->assertSame('rekod_warta', $case->peringkat_semasa);
        $this->assertSame(Pemerolehan::DALAM_PROSES, $case->status_pemerolehan);
    }

    public function test_SRS_PER_01_permission_required(): void
    {
        $this->getJson('/api/v1/per/kes')->assertUnauthorized();

        $nobody = $this->officer(['per.laporan.lihat']);
        $this->actingAs($nobody)->getJson('/api/v1/per/saluran')->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/per/kes')->assertForbidden();
        $this->createCase($nobody, 'wakaf')->assertForbidden();
    }
}
