<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data governance (SRS §4.3, §4.4; SRS-PRO-05, 11, 12, 13, 14, 20, 22, 23, 30):
 * stewardship register, configurable quality rules, classification per data
 * component, curated data-dictionary metadata, data-quality tasks routed to
 * stewards, master-record versions, duplicate suggestions, retention/archive
 * and the per-lot access log.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-PRO-11
        Schema::create('daftar_stewardship_data', function (Blueprint $table) {
            $table->id();
            $table->string('komponen_data', 100);
            $table->string('entiti_berkaitan', 100)->comment('Jadual, dipisah koma');
            $table->string('jawatan_data_owner', 150);
            $table->string('bahagian_data_owner', 150);
            $table->foreignId('id_data_owner')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jawatan_data_steward', 150);
            $table->string('bahagian_data_steward', 150);
            $table->foreignId('id_data_steward')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bahagian_pemilik', 30)->nullable()->comment('Hadkan kepada bahagian lot (hartanah | wakaf | baitulmal)');
            $table->string('kod_daerah', 4)->nullable()->comment('Hadkan kepada daerah (steward daerah)');
            $table->text('peranan_tanggungjawab');
            $table->date('tarikh_berkuat_kuasa');
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->index(['entiti_berkaitan', 'status']);
        });

        // SRS-PRO-13 / SRS-DAT-01
        Schema::create('peraturan_kualiti_data', function (Blueprint $table) {
            $table->id();
            $table->string('entiti', 64);
            $table->string('medan', 64);
            $table->string('jenis_peraturan', 20)->comment('wajib | format | julat | senarai | panjang | rujukan | unik | lengkap');
            $table->jsonb('ungkapan_peraturan')->default('{}');
            $table->string('mesej_ralat', 255);
            $table->string('tahap_keterukan', 10)->default('ralat')->comment('ralat (menghalang) | amaran (dilaporkan sahaja)');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index(['entiti', 'status_aktif']);
        });

        // SRS-PRO-14
        Schema::create('klasifikasi_data', function (Blueprint $table) {
            $table->id();
            $table->string('komponen_data', 100)->unique();
            $table->string('entiti', 64);
            $table->string('aras_kerahsiaan', 10);
            $table->string('keperluan_encryption', 150);
            $table->string('peranan_dibenarkan', 255);
            $table->string('kebenaran_diperlukan', 100)->nullable();
            $table->date('tarikh_semakan');
            $table->timestamps();
        });

        // SRS-PRO-05: curated half of the data dictionary (technical half comes from the catalog)
        Schema::create('kamus_data', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jadual', 64);
            $table->string('nama_medan', 64)->nullable()->comment('NULL = metadata jadual');
            $table->string('nama_perniagaan', 150);
            $table->text('definisi')->nullable();
            $table->string('format', 150)->nullable();
            $table->string('nilai_dibenarkan', 500)->nullable();
            $table->boolean('wajib')->nullable();
            $table->string('data_owner', 150)->nullable();
            $table->string('data_steward', 150)->nullable();
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->timestamps();

            $table->unique(['nama_jadual', 'nama_medan']);
        });

        // SRS-PRO-12
        Schema::create('tugasan_kualiti_data', function (Blueprint $table) {
            $table->id();
            $table->string('entiti', 64);
            $table->string('id_rekod', 64);
            $table->string('kod_isu', 50);
            $table->text('keterangan');
            $table->string('tahap_keterukan', 10)->default('ralat');
            $table->foreignId('id_stewardship')->nullable()->constrained('daftar_stewardship_data')->nullOnDelete();
            $table->foreignId('id_data_steward')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kod_daerah', 4)->nullable();
            $table->string('status', 20)->default('terbuka')->comment('terbuka | selesai | ditolak');
            $table->text('tindakan_pembetulan')->nullable();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->string('sumber', 30)->default('semakan')->comment('semakan | import | laporan_berjadual');
            $table->timestamps();

            $table->index(['entiti', 'id_rekod', 'kod_isu', 'status']);
            $table->index(['id_data_steward', 'status']);
        });

        // SRS-PRO-22 / SRS-DAT-04
        Schema::create('versi_rekod_induk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entiti', 64);
            $table->string('id_rekod', 64);
            $table->unsignedInteger('no_versi')->nullable()->comment('Diberi apabila perubahan berkuat kuasa');
            $table->jsonb('nilai_sebelum')->nullable();
            $table->jsonb('nilai_selepas');
            $table->boolean('kritikal')->default(false);
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justifikasi')->nullable();
            $table->foreignId('id_pengesah_kedua')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_pengesahan', 20)->comment('berkuat_kuasa | menunggu | lulus | tolak | ditarik_balik | dibatalkan');
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->text('catatan_pengesahan')->nullable();
            $table->timestamp('tarikh_pengesahan')->nullable();
            $table->timestamps();

            $table->index(['entiti', 'id_rekod']);
            $table->unique(['entiti', 'id_rekod', 'no_versi']);
        });

        // SRS-PRO-20 / SRS-DAT-02
        Schema::create('cadangan_duplikasi', function (Blueprint $table) {
            $table->id();
            $table->string('entiti', 64)->default('lot_tanah');
            $table->uuid('id_rekod_a');
            $table->uuid('id_rekod_b');
            $table->jsonb('kriteria_padanan');
            $table->decimal('skor_padanan', 5, 2);
            $table->string('keputusan', 20)->default('menunggu')->comment('menunggu | sah | gabung | tolak');
            $table->text('catatan_keputusan')->nullable();
            $table->foreignId('id_data_steward')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->timestamps();

            $table->unique(['entiti', 'id_rekod_a', 'id_rekod_b']);
            $table->index('keputusan');
        });

        // SRS-PRO-23 / SRS-DAT-06
        Schema::create('jadual_retensi', function (Blueprint $table) {
            $table->id();
            $table->string('kategori_rekod', 50)->unique();
            $table->string('keterangan', 255);
            $table->string('entiti', 64)->default('lot_tanah');
            $table->jsonb('syarat')->default('{}')->comment('Contoh {"status_lot": ["tidak_aktif","digabung"]}');
            $table->unsignedSmallInteger('tempoh_retensi_tahun');
            $table->string('tindakan_selepas_retensi', 20)->default('arkib');
            $table->string('rujukan_dasar_mais', 255)->nullable();
            $table->boolean('memerlukan_kelulusan')->default(true);
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('rekod_arkib', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entiti', 64);
            $table->string('id_rekod', 64);
            $table->foreignId('id_jadual_retensi')->nullable()->constrained('jadual_retensi')->nullOnDelete();
            $table->string('status_arkib', 20)->comment('dicadang | menunggu_kelulusan | diarkib | ditolak | dipulih');
            $table->foreignId('id_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus_arkib')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->timestamp('tarikh_arkib')->nullable();
            $table->string('lokasi_storan_arkib', 100)->default('pangkalan_data:lot_tanah(status_lot=arkib)');
            $table->date('tarikh_layak_lupus')->nullable();
            $table->foreignId('id_pemulih')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pulih')->nullable();
            $table->text('sebab')->nullable();
            $table->timestamps();

            $table->index(['entiti', 'id_rekod']);
            $table->index('status_arkib');
        });

        // SRS-PRO-30
        Schema::create('log_akses_profil', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lot_id')->constrained('lot_tanah')->cascadeOnDelete();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis_capaian', 20)->comment('lihat | eksport | cetak | api');
            $table->string('saluran', 20)->default('web');
            $table->string('alamat_ip', 45)->nullable();
            $table->jsonb('butiran')->nullable();
            $table->timestamp('tarikh_masa');

            $table->index(['lot_id', 'tarikh_masa']);
        });
    }

    public function down(): void
    {
        foreach (['log_akses_profil', 'rekod_arkib', 'jadual_retensi', 'cadangan_duplikasi', 'versi_rekod_induk', 'tugasan_kualiti_data', 'kamus_data', 'klasifikasi_data', 'peraturan_kualiti_data', 'daftar_stewardship_data'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
