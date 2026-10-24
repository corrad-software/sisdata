<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Models\AsetGambar;
use App\Modules\Ast\Models\AsetPemeriksaan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PemeriksaanTest extends AstTestCase
{
    public const LAPANGAN = ['ast.lapangan.lihat', 'ast.lapangan.kemas_kini'];

    public function test_SRS_AST_20_field_app_downloads_in_scope_assets_for_offline_use(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $this->daftar($u, $this->lot('100901'), ['nama_aset' => 'Aset Sepang']);
        $officer = $this->user(self::LAPANGAN, ['1008']);
        $b = $this->actingAs($officer)->getJson('/api/v1/ast/lapangan/bundle')->assertOk();
        $this->assertSame([$a->id], array_column($b->json('data.aset'), 'id'));
        $this->assertSame($a->qr_ref, $b->json('data.aset.0.qr_ref'));
        $this->assertNotEmpty($b->json('data.senarai_semak'));
        $this->actingAs($this->user(['ast.aset.lihat']))->getJson('/api/v1/ast/lapangan/bundle')->assertForbidden();
    }

    public function test_SRS_AST_20_offline_inspection_syncs_idempotently_without_losing_records(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $officer = $this->user(self::LAPANGAN, ['1008']);
        $offline = (string) Str::uuid();
        $payload = [
            'id_luar_talian' => $offline, 'id_aset' => $a->id, 'tarikh_pemeriksaan' => now()->subDay()->toDateString(), 'status_kondisi_dilapor' => 'perlu_perhatian',
            'isu_dikenalpasti' => 'Siling bocor', 'tindakan_disyorkan' => 'Baiki siling', 'latitud' => (float) $a->latitud, 'longitud' => (float) $a->longitud, 'ketepatan_gps_m' => 6,
            'senarai_semak' => [['kod' => 'bumbung', 'status' => 'isu', 'catatan' => 'Bocor'], ['kod' => 'elektrik', 'status' => 'baik']],
            'masa_mula' => now()->subDay()->subHour()->toIso8601String(), 'masa_tamat' => now()->subDay()->toIso8601String(),
        ];
        $first = $this->actingAs($officer)->postJson('/api/v1/ast/lapangan/pemeriksaan', $payload)->assertCreated()->assertJsonPath('status_segerak', 'diterima');
        // Network dropped before the response reached the device → the app retries.
        $this->actingAs($officer)->postJson('/api/v1/ast/lapangan/pemeriksaan', $payload)->assertOk()->assertJsonPath('status_segerak', 'pendua')->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, AsetPemeriksaan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->count());

        $p = AsetPemeriksaan::query()->withoutGlobalScope('access')->findOrFail($first->json('data.id'));
        $this->assertSame(['pwa', 'disegerak', 2], [$p->kaedah, $p->status_penyegerakan, count($p->senarai_semak)]);
        $this->assertLessThan(5, (float) $p->jarak_dari_aset_m);
        $a->refresh();
        $this->assertSame('perlu_perhatian', $a->status_kondisi);
        $this->assertSame(now()->subDay()->toDateString(), $a->tarikh_pemeriksaan_terakhir?->toDateString());

        // Geotagged photos, also idempotent.
        $photo = fn () => $this->actingAs($officer)->post('/api/v1/ast/lapangan/pemeriksaan/'.$p->id.'/gambar', [
            'fail' => UploadedFile::fake()->image('siling.jpg', 640, 480), 'id_luar_talian' => '6f1c1f3e-3b9e-4a59-9d0e-5f6b1b8c0d11',
            'latitud' => 3.1001, 'longitud' => 101.6001, 'ketepatan_m' => 4, 'tarikh_gambar' => now()->subDay()->toIso8601String(),
        ], ['Accept' => 'application/json']);
        $photo()->assertCreated();
        $photo()->assertOk()->assertJsonPath('status_segerak', 'pendua');
        $g = AsetGambar::query()->withoutGlobalScope('access')->where('id_pemeriksaan', $p->id)->sole();
        $this->assertSame(['pemeriksaan', '3.1001000', $officer->id], [$g->kategori_gambar, $g->latitud, $g->id_pegawai_rakam]);
        $doc = $this->documents->docs[$g->laluan_fail];
        $this->assertSame(['aset_fizikal', $a->id, 'gambar_tapak'], [$doc['owner_type'], $doc['owner_id'], $doc['category']]);
    }

    public function test_SRS_AST_20_older_offline_inspection_does_not_overwrite_newer_condition(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $officer = $this->user(self::LAPANGAN);
        $this->actingAs($officer)->postJson('/api/v1/ast/lapangan/pemeriksaan', ['id_luar_talian' => (string) Str::uuid(), 'id_aset' => $a->id, 'tarikh_pemeriksaan' => now()->toDateString(), 'status_kondisi_dilapor' => 'kritikal'])->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/ast/lapangan/pemeriksaan', ['id_luar_talian' => (string) Str::uuid(), 'id_aset' => $a->id, 'tarikh_pemeriksaan' => now()->subDays(10)->toDateString(), 'status_kondisi_dilapor' => 'baik'])->assertCreated();
        $a->refresh();
        $this->assertSame('kritikal', $a->status_kondisi);
        $this->assertSame(now()->toDateString(), $a->tarikh_pemeriksaan_terakhir?->toDateString());
        $this->assertSame(2, AsetPemeriksaan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->count(), 'both kept');
    }

    public function test_SRS_AST_20_out_of_scope_asset_cannot_be_synced(): void
    {
        $a = $this->daftar($this->unitAset(), $this->lot('100901'));
        $this->actingAs($this->user(self::LAPANGAN, ['1008']))->postJson('/api/v1/ast/lapangan/pemeriksaan', ['id_luar_talian' => (string) Str::uuid(), 'id_aset' => $a->id, 'tarikh_pemeriksaan' => now()->toDateString(), 'status_kondisi_dilapor' => 'baik'])
            ->assertStatus(422)->assertJsonValidationErrors('id_aset');
        $this->actingAs($this->user(['ast.lapangan.lihat']))->postJson('/api/v1/ast/lapangan/pemeriksaan', [])->assertForbidden();
    }

    public function test_SRS_AST_20_inspection_after_qr_scan_can_raise_corrective_work(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/pemeriksaan', [
            'kaedah' => 'qr', 'tarikh_pemeriksaan' => now()->toDateString(), 'status_kondisi_dilapor' => 'kritikal', 'isu_dikenalpasti' => 'Dinding retak', 'tindakan_disyorkan' => 'Perkukuh struktur', 'cipta_kerja' => true,
        ])->assertCreated()->assertJsonPath('data.kaedah', 'qr');
        $k = RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->where('sumber', 'pemeriksaan')->sole();
        $this->assertSame(['kecemasan', 'kritikal'], [$k->jenis_kerja, $k->keutamaan]);
    }

    public function test_SRS_AST_21_last_inspection_date_and_assets_not_inspected_beyond_period(): void
    {
        $u = $this->unitAset();
        $lama = $this->daftar($u, $this->lot(), ['nama_aset' => 'Lama']);
        $baru = $this->daftar($u, $this->lot(), ['nama_aset' => 'Baru']);
        $tiada = $this->daftar($u, $this->lot(), ['nama_aset' => 'Tiada']);
        $bulan = $this->kategori()->tempoh_pemeriksaan_bulan;
        DB::table('aset_fizikal')->where('id', $lama->id)->update(['tarikh_pemeriksaan_terakhir' => now()->subMonthsNoOverflow($bulan)->subDays(2)->toDateString()]);
        DB::table('aset_fizikal')->where('id', $baru->id)->update(['tarikh_pemeriksaan_terakhir' => now()->subDays(10)->toDateString()]);

        $ids = collect($this->actingAs($u)->getJson('/api/v1/ast/aset?tanpa_pemeriksaan=1')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$lama->id, $tiada->id], $ids);
        $r = $this->actingAs($u)->getJson('/api/v1/ast/laporan/tanpa_pemeriksaan')->assertOk();
        $this->assertSame(2, $r->json('data.ringkasan.0.nilai'));
        $this->assertSame('Belum pernah', collect($r->json('data.baris'))->firstWhere('nama_aset', 'Tiada')['tarikh_pemeriksaan_terakhir']);
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$lama->id)->assertJsonPath('data.pemeriksaan_lewat', true);
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$baru->id)->assertJsonPath('data.pemeriksaan_lewat', false);
    }
}
