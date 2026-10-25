<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-LAP's own entities (SRS-SISDATA §Modul 16 "Entiti data"). LAP never
 * writes another module's tables — these hold only report definitions,
 * schedules, generation logs, analytic results and executive insights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lap_katalog_laporan', function (Blueprint $t) {
            $t->id();
            $t->string('kod_laporan', 80)->unique();
            $t->string('nama', 200);
            $t->string('kategori', 30)->index()->comment('portfolio | kewangan | operasi | risiko | pematuhan | aset | lembaga');
            $t->string('jenis', 20)->default('lap')->comment('lap (enjin MOD-LAP) | modul (laporan modul sumber) | komposit (laporan lembaga / berkanun)');
            $t->text('keterangan')->nullable();
            $t->text('definisi_metrik')->nullable();
            $t->text('sumber_data')->nullable();
            $t->string('pemilik', 150)->nullable()->comment('pemilik laporan (bahagian / jawatan)');
            $t->string('kebenaran', 100)->nullable()->comment('kunci kebenaran RBC yang diperlukan, selain kebenaran set data');
            $t->json('peranan_dibenarkan')->nullable()->comment('kod peranan yang dibenarkan; kosong = semua yang ada kebenaran');
            $t->string('set_data', 50)->nullable();
            $t->json('definisi')->nullable()->comment('definisi pertanyaan (medan, penapis, kumpulan, agregat, susunan, tempoh)');
            $t->json('bahagian')->nullable()->comment('kod laporan bagi laporan komposit');
            $t->string('pautan', 300)->nullable()->comment('laman laporan modul sumber (jenis modul)');
            $t->string('status_pengesahan', 20)->default('draf')->comment('draf | disahkan (CRP-2)');
            $t->unsignedBigInteger('disahkan_oleh')->nullable();
            $t->timestamp('disahkan_pada')->nullable();
            $t->boolean('aktif')->default(true);
            $t->unsignedSmallInteger('susunan')->default(0);
            $t->timestamps();
        });

        Schema::create('lap_definisi_laporan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kod_laporan', 80)->nullable()->index()->comment('laporan standard asal, jika diubah suai daripadanya');
            $t->string('nama', 200);
            $t->text('keterangan')->nullable();
            $t->string('set_data', 50);
            $t->json('pertanyaan');
            $t->json('parameter_lalai')->nullable();
            $t->unsignedBigInteger('id_pencipta')->index();
            $t->string('status_kongsi', 20)->default('peribadi')->comment('peribadi | peranan | semua — penerima tetap tertakluk kepada kebenaran sendiri');
            $t->json('kongsi_peranan')->nullable();
            $t->string('sumber', 20)->default('pembina')->comment('pembina | ai_prompt');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('lap_jadual_penjanaan', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 200);
            $t->uuid('id_definisi')->nullable()->index();
            $t->string('kod_laporan', 80)->nullable()->index();
            $t->string('kekerapan', 20)->comment('harian | mingguan | bulanan | suku_tahun');
            $t->string('masa_jalan', 5)->default('07:00');
            $t->unsignedTinyInteger('hari_jalan')->nullable()->comment('mingguan: 1 (Isnin)–7; bulanan / suku tahun: 1–28');
            $t->string('format', 10)->default('pdf');
            $t->json('parameter')->nullable();
            $t->json('senarai_penerima')->comment('ID pengguna SISDATA; laporan dijana mengikut skop setiap penerima');
            $t->unsignedBigInteger('id_pencipta')->index();
            $t->boolean('status_aktif')->default(true);
            $t->timestamp('jalan_berikutnya')->nullable()->index();
            $t->timestamp('jalan_terakhir')->nullable();
            $t->string('status_terakhir', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('lap_log_penjanaan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('id_definisi')->nullable()->index();
            $t->string('kod_laporan', 80)->nullable()->index();
            $t->unsignedBigInteger('id_jadual')->nullable()->index();
            $t->string('tajuk', 250);
            $t->string('set_data', 50)->nullable();
            $t->json('parameter_digunakan')->nullable();
            $t->string('format_eksport', 10)->comment('paparan | pdf | xlsx | csv | png');
            $t->string('mod', 15)->default('segerak')->comment('segerak | latar | berjadual');
            $t->unsignedInteger('bilangan_rekod')->default(0);
            $t->unsignedBigInteger('id_pengguna')->nullable()->index();
            $t->timestamp('cap_masa')->index();
            $t->string('status', 20)->comment('berjaya | gagal | beratur | dalam_proses | ditolak');
            $t->unsignedInteger('tempoh_ms')->nullable();
            $t->string('laluan_fail', 300)->nullable();
            $t->unsignedBigInteger('saiz_bait')->nullable();
            $t->string('checksum', 64)->nullable();
            $t->string('klasifikasi', 20)->default('dalaman');
            $t->text('ralat')->nullable();
            $t->timestamp('tamat_pada')->nullable();
            $t->timestamps();
        });

        Schema::create('lap_hasil_analitik', function (Blueprint $t) {
            $t->id();
            $t->string('jenis_analitik', 40)->comment('potensi_hasil | risiko_tunggakan | tanah_tidak_produktif | potensi_pembangunan');
            $t->uuid('id_lot')->index();
            $t->string('no_lot', 100)->nullable();
            $t->string('kod_daerah', 10)->nullable()->index();
            $t->string('kod_mukim', 10)->nullable();
            $t->string('klasifikasi', 20)->default('dalaman');
            $t->decimal('skor', 5, 2);
            $t->string('aras_risiko', 20)->comment('rendah | sederhana | tinggi | kritikal');
            $t->json('faktor')->nullable();
            $t->timestamp('tarikh_kira');
            $t->string('versi_model', 40);
            $t->boolean('semasa')->default(true);
            $t->timestamps();
            $t->index(['jenis_analitik', 'semasa']);
        });

        Schema::create('lap_cadangan_ai', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_hasil')->nullable()->index();
            $t->uuid('id_lot')->index();
            $t->string('no_lot', 100)->nullable();
            $t->string('kod_daerah', 10)->nullable()->index();
            $t->string('kod_mukim', 10)->nullable();
            $t->string('klasifikasi', 20)->default('dalaman');
            $t->string('jenis_analitik', 40);
            $t->string('jenis_tindakan', 60);
            $t->text('asas_cadangan');
            $t->string('status_keputusan', 20)->default('baharu')->comment('baharu | diterima | ditolak');
            $t->text('justifikasi_pegawai')->nullable();
            $t->unsignedBigInteger('id_pegawai')->nullable();
            $t->timestamp('tarikh_keputusan')->nullable();
            $t->string('versi_model', 40);
            $t->timestamps();
        });

        Schema::create('lap_insight_eksekutif', function (Blueprint $t) {
            $t->id();
            $t->string('tempoh', 20);
            $t->json('skop')->nullable()->comment('null = seluruh negeri');
            $t->json('isu_utama');
            $t->json('risiko');
            $t->json('tindakan_segera');
            $t->json('statistik')->nullable();
            $t->string('sumber', 20)->default('berjadual');
            $t->unsignedBigInteger('id_pengguna')->nullable();
            $t->timestamp('tarikh_jana')->index();
            $t->timestamps();
        });

        Schema::create('lap_papan_bi', function (Blueprint $t) {
            $t->id();
            $t->string('kod', 60)->unique();
            $t->string('nama', 200);
            $t->text('keterangan')->nullable();
            $t->string('jenis', 20)->default('dashboard')->comment('dashboard | question');
            $t->unsignedInteger('id_metabase');
            $t->string('kebenaran', 100)->nullable()->comment('kebenaran tambahan selain lap.bi.lihat');
            $t->json('parameter_kunci')->nullable()->comment('nama parameter Metabase => jenis skop RBC (cth. {"kod_daerah":"daerah"})');
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['lap_papan_bi', 'lap_insight_eksekutif', 'lap_cadangan_ai', 'lap_hasil_analitik', 'lap_log_penjanaan', 'lap_jadual_penjanaan', 'lap_definisi_laporan', 'lap_katalog_laporan'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
