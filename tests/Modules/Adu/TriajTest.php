<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\ResolusiAduan;

class TriajTest extends AduTestCase
{
    public function test_SRS_ADU_09_coordinates_inside_a_mais_lot_are_matched_to_that_lot(): void
    {
        $lot = $this->lot('100803');
        $this->lot('100901');

        $aduan = $this->aduan(['lat' => self::PUSAT_PETALING[0] + 0.0004, 'lng' => self::PUSAT_PETALING[1] - 0.0003]);

        $this->assertSame($lot, $aduan->lot_id);
        $this->assertSame('koordinat', $aduan->kaedah_padanan_lot);
        $this->assertSame(['1008', '100803'], [$aduan->kod_daerah, $aduan->kod_mukim]);
        $this->assertFalse($aduan->status_bukan_tanah_mais);
    }

    public function test_SRS_ADU_09_category_and_priority_are_suggested_from_keywords(): void
    {
        $aduan = $this->aduan(['kategori' => null, 'keterangan' => 'Ada kebakaran kecil dan bahaya kepada kanak-kanak di kawasan ini.']);
        $this->assertSame('KESELAMATAN', $aduan->kategori_dicadang);
        $this->assertSame('KESELAMATAN', $aduan->kategori);
        $this->assertSame('kritikal', $aduan->tahap_keutamaan);

        $officer = $this->user(self::PEGAWAI);
        $this->actingAs($officer)->postJson('/api/v1/adu/cadang-kategori', ['keterangan' => 'Longgokan sampah sisa binaan'])
            ->assertOk()->assertJsonPath('data.kategori', 'PEMBUANGAN_SAMPAH');
    }

    public function test_SRS_ADU_09_repeat_complaint_on_same_lot_within_window_is_flagged(): void
    {
        $lot = $this->lot();
        $first = $this->aduan(['lot_id' => $lot, 'keterangan' => 'Penghuni haram membina pondok di atas lot ini.']);
        $second = $this->aduan(['lot_id' => $lot, 'keterangan' => 'Penghuni haram membina pondok di atas lot ini lagi.']);
        $otherCategory = $this->aduan(['lot_id' => $lot, 'kategori' => 'PEMBUANGAN_SAMPAH', 'keterangan' => 'Sampah']);

        $this->assertNull($first->id_aduan_pendua_dicadang);
        $this->assertSame($first->id, $second->id_aduan_pendua_dicadang);
        $this->assertGreaterThan(80, $second->skor_pendua);
        $this->assertNull($otherCategory->id_aduan_pendua_dicadang);

        // Outside the configured window it is not a repeat.
        Aduan::query()->whereKey($first->id)->update(['tarikh_terima' => now()->subDays(45)]);
        Aduan::query()->whereKey($second->id)->update(['tarikh_terima' => now()->subDays(45)]);
        $this->assertNull($this->aduan(['lot_id' => $lot])->id_aduan_pendua_dicadang);
    }

    public function test_SRS_ADU_10_location_outside_every_mais_lot_is_flagged_with_polite_reply_and_agencies(): void
    {
        $this->lot();
        $aduan = $this->aduan(['lat' => 3.2, 'lng' => 101.4, 'kod_daerah' => '1008']);

        $this->assertTrue($aduan->status_bukan_tanah_mais);
        $this->assertNull($aduan->lot_id);
        $this->assertSame('tiada', $aduan->kaedah_padanan_lot);
        $this->assertStringContainsString('bukan tanah milik', (string) $aduan->jawapan_automatik);
        $this->assertStringContainsString('Majlis Bandaraya Petaling Jaya', (string) $aduan->jawapan_automatik);
        $this->assertContains('Pejabat Daerah dan Tanah Petaling', array_column($aduan->agensi_dicadang ?? [], 'nama'));
        $this->assertNull($aduan->id_pegawai, 'not auto-assigned: awaits Unit Aduan review');
        $this->assertSame([], array_filter($this->messenger->sent, fn ($m) => str_contains($m['teks'], 'bukan tanah')), 'reply not sent before officer review');

        // Officer reviews → reply goes out, closure requested through WFE.
        $unit = $this->user(self::UNIT_ADUAN);
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$aduan->id}/bukan-tanah-mais", ['jawapan' => $aduan->jawapan_automatik])->assertOk()
            ->assertJsonPath('data.status', 'menunggu_kelulusan');
        $this->assertTrue(collect($this->messenger->sent)->contains(fn ($m) => str_contains($m['teks'], 'bukan tanah milik')));
        $this->assertSame('bukan_tanah_mais', ResolusiAduan::query()->where('aduan_id', $aduan->id)->value('kategori_resolusi'));
        $this->assertSame('adu.penutupan', array_values($this->workflow->started)[0]['flow']);
    }
}
