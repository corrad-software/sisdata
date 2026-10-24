<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Integrasi\Reminders\InsuransTamat;
use App\Modules\Ast\Integrasi\Reminders\JaminanTamat;
use App\Modules\Ast\Models\AsetDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaTagTest extends AstTestCase
{
    public function test_SRS_AST_11_free_and_structured_tags_filter_and_report(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot(), ['tag' => ['warisan', 'Dekat Sungai']]);
        $this->daftar($u, $this->lot(), ['nama_aset' => 'Lain', 'tag' => ['sewaan']]);
        $tags = collect($this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id)->json('data.tag'))->keyBy('nama');
        $this->assertSame('berstruktur', $tags['warisan']['jenis']);
        $this->assertSame('bebas', $tags['dekat_sungai']['jenis']);
        $this->assertSame([$a->id], collect($this->actingAs($u)->getJson('/api/v1/ast/aset?tag=warisan')->json('data'))->pluck('id')->all());
        $this->assertSame(1, $this->actingAs($u)->getJson('/api/v1/ast/laporan/inventori?tag=dekat_sungai')->json('data.ringkasan.0.nilai'));
        $this->actingAs($u)->putJson('/api/v1/ast/aset/'.$a->id, ['tag' => ['warisan']])->assertOk();
        $this->assertSame(['warisan'], DB::table('aset_tag')->where('id_aset', $a->id)->pluck('nama_tag')->all());
        $this->assertNotEmpty($this->actingAs($u)->getJson('/api/v1/ast/tag')->json('data'));
    }

    public function test_SRS_AST_12_dated_photo_gallery_in_chronological_order_with_gps_and_officer(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $a = $this->daftar($u, $lot);
        foreach (['2024-01-05T10:00:00+08:00', '2025-06-01T09:00:00+08:00', '2023-03-03T08:00:00+08:00'] as $t) {
            $this->actingAs($u)->post('/api/v1/ast/aset/'.$a->id.'/gambar', ['fail' => UploadedFile::fake()->image('g.jpg'), 'tarikh_gambar' => $t, 'latitud' => 3.1, 'longitud' => 101.6, 'keterangan' => 'Pandangan hadapan'], ['Accept' => 'application/json'])->assertCreated();
        }
        // A MOD-PTN visit photo of the same lot joins the gallery for users who may see visits.
        $visit = (string) Str::uuid();
        DB::table('rekod_lawatan')->insert(['id' => $visit, 'no_rujukan' => 'LWT-T-1', 'lot_id' => $lot, 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'klasifikasi' => 'dalaman', 'tarikh_lawatan' => '2024-08-01', 'status_lawatan' => 'disahkan', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gambar_lawatan')->insert(['id_lawatan' => $visit, 'tarikh_masa' => '2024-08-01 10:00:00', 'kapsyen' => 'Lawatan', 'imej_kecil' => 'data:image/jpeg;base64,AAAA', 'created_at' => now(), 'updated_at' => now()]);

        $g = $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/gambar')->assertOk()->json('data');
        $this->assertCount(3, $g, 'no PTN photo without ptn.lawatan.lihat');
        $this->assertSame(['2025-06-01', '2024-01-05', '2023-03-03'], array_map(fn ($x) => substr($x['tarikh_gambar'], 0, 10), $g));
        $this->assertSame([3.1, 101.6], [$g[0]['latitud'], $g[0]['longitud']]);
        $this->assertSame($u->name, $g[0]['perakam']);
        $both = $this->actingAs($this->user([...self::UNIT_ASET, 'ptn.lawatan.lihat']))->getJson('/api/v1/ast/aset/'.$a->id.'/gambar')->json('data');
        $this->assertCount(4, $both);
        $this->assertSame('ptn', $both[1]['sumber']);
        $this->assertSame('/admin/ptn/lawatan/'.$visit, $both[1]['pautan']);
        $this->assertNotNull($this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/gambar/'.$g[0]['id'].'/url')->json('data.url'));
    }

    public function test_SRS_AST_22_supporting_documents_through_dms_with_expiry(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        foreach (['pelan_bangunan' => 'pelan', 'perakuan_siap' => 'surat_rasmi', 'permit' => 'surat_rasmi', 'polisi_insurans' => 'perjanjian', 'dokumen_pemilikan' => 'geran'] as $kat => $dms) {
            $this->actingAs($u)->post('/api/v1/ast/aset/'.$a->id.'/dokumen', ['fail' => UploadedFile::fake()->create($kat.'.pdf', 20, 'application/pdf'), 'kategori_dokumen' => $kat, 'tarikh_luput' => '2030-01-01'], ['Accept' => 'application/json'])->assertCreated();
            $doc = collect($this->documents->docs)->last();
            $this->assertSame(['aset_fizikal', $a->id, $dms], [$doc['owner_type'], $doc['owner_id'], $doc['category']]);
        }
        $this->assertSame(5, AsetDokumen::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->count());
        $list = $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/dokumen')->assertOk()->json('data');
        $this->assertSame('2030-01-01', $list[0]['tarikh_luput']);
        $this->assertNotNull($this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/dokumen/'.$list[0]['id'].'/url')->json('data.url'));
        $this->actingAs($this->user(['ast.aset.lihat']))->post('/api/v1/ast/aset/'.$a->id.'/dokumen', ['kategori_dokumen' => 'permit'], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_SRS_AST_22_insurance_and_warranty_with_expiry_reminders(): void
    {
        $officer = $this->user(['ast.aset.lihat']);
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot(), ['id_pegawai_bertanggungjawab' => $officer->id]);
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/insurans', ['no_polisi' => 'P-1', 'penanggung' => 'Takaful Ujian', 'tarikh_mula' => now()->subYear()->addDays(30)->toDateString(), 'tarikh_tamat' => now()->addDays(30)->toDateString(), 'nilai_dilindungi' => 250000])->assertCreated();
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/jaminan', ['perkara' => 'Bumbung', 'tarikh_mula' => now()->subYear()->toDateString(), 'tarikh_tamat' => now()->addDays(7)->toDateString()])->assertCreated();
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id.'/insurans')->assertOk()->assertJsonCount(1, 'data.insurans')->assertJsonCount(1, 'data.jaminan');

        $i = iterator_to_array((new InsuransTamat)->due(30, CarbonImmutable::today()), false);
        $this->assertSame('P-1', $i[0]->data['no_polisi']);
        $this->assertSame($officer->id, $i[0]->recipients->id);
        $j = iterator_to_array((new JaminanTamat)->due(7, CarbonImmutable::today()), false);
        $this->assertSame('Bumbung', $j[0]->data['perkara']);
    }
}
