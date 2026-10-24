<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-HKM · Hak Milik Tanah (SRS-HKM-01..22). Entities follow the SRS entity
 * list; every satellite holds lot_id → lot_tanah (ADR-01). Daerah/mukim,
 * jenis hak milik and kategori tanah are MOD-REF codes (versioned, not FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-HKM-01/02/21 — the title register.
        Schema::create('hak_milik', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('no_hak_milik', 50);
            $table->string('jenis_hak_milik', 20)->comment('ref_jenis_hak_milik.kod');
            $table->string('kategori_tanah', 30)->nullable()->comment('ref_kategori_guna_tanah.kod');
            $table->text('syarat_nyata')->nullable();
            $table->decimal('keluasan', 16, 4)->nullable();
            $table->string('unit_keluasan', 10)->default('m2');
            $table->string('kod_mukim', 6);
            $table->string('kod_daerah', 4);
            $table->string('pemilik_berdaftar', 255);
            $table->date('tarikh_pendaftaran')->nullable();
            $table->date('tarikh_tamat_tempoh')->nullable();
            $table->unsignedSmallInteger('tempoh_pajakan_tahun')->nullable();
            $table->string('pejabat_pendaftaran', 150)->nullable();
            $table->string('no_perserahan', 50)->nullable();
            $table->date('tarikh_perserahan')->nullable();
            $table->string('status_semasa', 40)->default('aktif');
            $table->uuid('id_hak_milik_digantikan')->nullable();
            $table->boolean('disahkan')->default(false);
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();
            $table->string('klasifikasi', 10)->default('sulit');
            $table->unsignedInteger('versi_rekod')->default(1);
            $table->foreignId('id_pendaftar')->nullable()->constrained('users')->nullOnDelete();
            // SRS-DAT-08 lineage for imported rows.
            $table->string('sumber_sistem', 100)->nullable();
            $table->string('kunci_asal', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lot_id');
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index('jenis_hak_milik');
            $table->index('status_semasa');
            $table->index('tarikh_tamat_tempoh');
        });
        Schema::table('hak_milik', function (Blueprint $table) {
            $table->foreign('id_hak_milik_digantikan')->references('id')->on('hak_milik')->nullOnDelete();
        });
        // Unique title number per mukim.
        DB::statement('CREATE UNIQUE INDEX hak_milik_no_mukim_unique ON hak_milik (kod_mukim, upper(no_hak_milik)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX hak_milik_kunci_asal_unique ON hak_milik (sumber_sistem, kunci_asal) WHERE kunci_asal IS NOT NULL');
        DB::statement("ALTER TABLE hak_milik ADD CONSTRAINT hak_milik_klasifikasi_check CHECK (klasifikasi IN ('sulit', 'rahsia'))");
        DB::statement('ALTER TABLE hak_milik ADD CONSTRAINT hak_milik_tarikh_check CHECK (tarikh_tamat_tempoh IS NULL OR tarikh_pendaftaran IS NULL OR tarikh_tamat_tempoh >= tarikh_pendaftaran)');
        DB::statement('CREATE INDEX hak_milik_carian_trgm ON hak_milik USING gin ((no_hak_milik || \' \' || pemilik_berdaftar) gin_trgm_ops)');

        // SRS-HKM-19 / SRS-DAT-04 — versioned before/after of every applied change (append-only).
        Schema::create('hak_milik_versi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->unsignedInteger('versi');
            $table->string('jenis_perubahan', 30);
            $table->json('data_sebelum')->nullable();
            $table->json('data_selepas');
            $table->json('medan_berubah');
            $table->text('justifikasi')->nullable();
            $table->string('id_dokumen_rujukan', 64)->nullable();
            $table->string('no_rujukan_dokumen', 100)->nullable();
            $table->uuid('id_perubahan')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hak_milik_id', 'versi']);
        });

        // SRS-HKM-03 — vault master.
        Schema::create('peti_besi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_peti_besi', 30)->unique();
            $table->string('nama', 150);
            $table->string('lokasi_bangunan', 150);
            $table->string('tingkat', 30)->nullable();
            $table->string('bilik', 60)->nullable();
            $table->foreignId('id_pegawai_penjaga')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('kapasiti')->nullable();
            $table->string('status_peti_besi', 20)->default('aktif');
            $table->date('tarikh_pemeriksaan_akhir')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // SRS-HKM-03/04/05 — where each original document is.
        Schema::create('daftar_peti_besi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peti_besi_id')->constrained('peti_besi');
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('jenis_dokumen_asal', 40)->default('dokumen_hak_milik_daftar');
            $table->string('no_fail', 50)->nullable();
            $table->string('lokasi_rak', 50)->nullable();
            $table->string('no_kotak_pengikat', 50)->nullable();
            $table->text('catatan_lokasi')->nullable();
            $table->string('status_dokumen_asal', 30)->default('dalam_simpanan');
            $table->date('tarikh_masuk_simpanan');
            $table->foreignId('id_pegawai_terima')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['peti_besi_id', 'lokasi_rak', 'no_fail']);
            $table->index('status_dokumen_asal');
            $table->unique(['hak_milik_id', 'jenis_dokumen_asal']);
        });

        // SRS-HKM-06 — issue / return history.
        Schema::create('pengeluaran_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daftar_peti_besi_id')->constrained('daftar_peti_besi');
            $table->timestamp('tarikh_pengeluaran');
            $table->foreignId('id_pegawai_keluar')->nullable()->constrained('users')->nullOnDelete();
            $table->string('penerima_nama', 150);
            $table->string('penerima_jawatan', 150)->nullable();
            $table->text('tujuan_pengeluaran');
            $table->string('status_semasa_dikeluarkan', 30)->default('dikeluarkan');
            $table->date('tarikh_jangka_pulang');
            $table->timestamp('tarikh_pulang_sebenar')->nullable();
            $table->foreignId('id_pegawai_terima_pulang')->nullable()->constrained('users')->nullOnDelete();
            $table->string('keadaan_dokumen_pulang', 30)->nullable();
            $table->text('catatan_pulang')->nullable();
            $table->string('status_pengeluaran', 20)->default('dikeluarkan');
            $table->timestamps();

            $table->index(['status_pengeluaran', 'tarikh_jangka_pulang']);
        });
        // One open issue per document at a time.
        DB::statement("CREATE UNIQUE INDEX pengeluaran_dokumen_satu_terbuka ON pengeluaran_dokumen (daftar_peti_besi_id) WHERE status_pengeluaran = 'dikeluarkan'");

        // SRS-HKM-08/09 — land status history (append-only, see next migration).
        Schema::create('sejarah_status_tanah', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->foreignUuid('hak_milik_id')->nullable()->constrained('hak_milik');
            $table->string('status_sebelum', 40)->nullable();
            $table->string('status_selepas', 40);
            $table->date('tarikh_berkuat_kuasa');
            $table->text('sebab_perubahan');
            $table->string('id_dokumen_rujukan', 64)->nullable();
            $table->string('no_rujukan_dokumen', 100)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('id_perubahan')->nullable();
            $table->timestamp('tarikh_masa_rekod')->useCurrent();

            $table->index(['hak_milik_id', 'tarikh_berkuat_kuasa']);
            $table->index('lot_id');
        });

        // SRS-HKM-10 — ownership chronology (append-only).
        Schema::create('kronologi_pemilikan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('pemilik_sebelum', 255)->nullable();
            $table->string('pemilik_selepas', 255);
            $table->date('tarikh_pertukaran')->nullable();
            $table->text('sebab_pertukaran');
            $table->string('id_pemerolehan', 100)->nullable()->comment('MOD-PER rujukan, e.g. pemerolehan:<id>');
            $table->string('id_dokumen_rujukan', 64)->nullable();
            $table->unsignedInteger('susunan_kronologi');
            $table->string('sumber', 20)->default('manual');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('id_perubahan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hak_milik_id', 'susunan_kronologi']);
            $table->index('lot_id');
        });

        // SRS-HKM-12 — heirs and co-owners.
        Schema::create('waris_pemilik_bersama', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->string('nama', 200);
            $table->string('no_pengenalan', 20)->nullable();
            $table->string('hubungan', 30);
            $table->unsignedInteger('pecahan_bahagian_pembilang')->nullable();
            $table->unsignedInteger('pecahan_bahagian_penyebut')->nullable();
            $table->string('sumber_pengesahan', 40)->nullable();
            $table->string('no_kes_faraid', 60)->nullable();
            $table->string('status_pengesahan', 30)->default('belum_disahkan');
            $table->text('catatan')->nullable();
            $table->boolean('sintetik')->default(false);
            $table->string('kunci_asal', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('hak_milik_id');
        });
        DB::statement('ALTER TABLE waris_pemilik_bersama ADD CONSTRAINT waris_pecahan_check CHECK ((pecahan_bahagian_pembilang IS NULL AND pecahan_bahagian_penyebut IS NULL) OR (pecahan_bahagian_penyebut > 0 AND pecahan_bahagian_pembilang <= pecahan_bahagian_penyebut))');

        // SRS-HKM-13 — encumbrances.
        Schema::create('sekatan_kepentingan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->string('jenis_sekatan', 40);
            $table->string('no_rujukan_sekatan', 100)->nullable();
            $table->string('pihak_terlibat', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->date('tarikh_masuk')->nullable();
            $table->date('tarikh_lupus')->nullable();
            $table->string('status_sekatan', 20)->default('aktif');
            $table->string('id_dokumen_rujukan', 64)->nullable();
            $table->string('id_kes_mahkamah', 64)->nullable()->comment('MOD-KES — no FK until that module exists');
            $table->timestamps();

            $table->index(['hak_milik_id', 'status_sekatan']);
        });

        // SRS-HKM-16 — permanent-title applications.
        Schema::create('permohonan_hak_milik_kekal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hak_milik_sementara_id')->constrained('hak_milik');
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('no_permohonan', 50)->unique();
            $table->date('tarikh_permohonan');
            $table->string('pejabat_tanah', 150);
            $table->json('senarai_semak_dokumen');
            $table->string('peringkat_semasa', 40)->default('pendaftaran');
            $table->string('status_kelulusan', 20)->default('dalam_proses');
            $table->decimal('jumlah_bayaran', 14, 2)->nullable();
            $table->date('tarikh_bayaran')->nullable();
            $table->string('no_resit', 60)->nullable();
            $table->date('tarikh_terima_hak_milik_baharu')->nullable();
            $table->foreignUuid('hak_milik_baharu_id')->nullable()->constrained('hak_milik');
            $table->unsignedInteger('tempoh_proses_hari')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['peringkat_semasa', 'status_kelulusan']);
        });

        Schema::create('permohonan_hak_milik_kekal_peringkat', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('permohonan_id')->constrained('permohonan_hak_milik_kekal')->cascadeOnDelete();
            $table->string('peringkat', 40);
            $table->string('status', 20);
            $table->date('tarikh');
            $table->text('catatan')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // SRS-HKM-21 — Land Office transactions (presentation tracking).
        Schema::create('urusan_pendaftaran_pt', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->string('jenis_urusan', 40);
            $table->string('pejabat_pendaftaran', 150);
            $table->string('no_perserahan', 50);
            $table->date('tarikh_perserahan');
            $table->string('status_urusan', 20)->default('diserah');
            $table->date('tarikh_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->uuid('permohonan_hak_milik_kekal_id')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hak_milik_id', 'status_urusan']);
            $table->unique(['pejabat_pendaftaran', 'no_perserahan']);
        });

        // SRS-HKM-18 — external change proposals, kept apart from the master.
        Schema::create('cadangan_perubahan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hak_milik_id')->nullable()->constrained('hak_milik');
            $table->string('no_hak_milik', 50);
            $table->string('kod_mukim', 6)->nullable();
            $table->string('sumber', 30);
            $table->string('rujukan_luaran', 100);
            $table->string('jenis_perubahan', 30);
            $table->json('data_cadangan');
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('draf');
            $table->foreignId('id_penghantar')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_penyemak')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_semakan')->nullable();
            $table->text('catatan_semakan')->nullable();
            $table->uuid('id_perubahan')->nullable();
            $table->timestamps();

            $table->unique(['sumber', 'rujukan_luaran']);
            $table->index('status');
        });

        // SRS-HKM-19 — change requests, applied only after two-stage WFE approval.
        Schema::create('perubahan_hak_milik', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hak_milik_id')->constrained('hak_milik');
            $table->string('jenis_perubahan', 30);
            $table->json('data_cadangan');
            $table->json('data_semasa')->nullable();
            $table->text('justifikasi');
            $table->string('id_dokumen_rujukan', 64)->nullable();
            $table->string('no_rujukan_dokumen', 100)->nullable();
            $table->string('status', 30)->default('menunggu_kelulusan');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->foreignId('id_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cadangan_perubahan_id')->nullable()->constrained('cadangan_perubahan');
            $table->unsignedBigInteger('diputuskan_oleh')->nullable();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->timestamps();

            $table->index(['hak_milik_id', 'status']);
        });
        // At most one pending change per title — avoids lost updates between approvals.
        DB::statement("CREATE UNIQUE INDEX perubahan_hak_milik_satu_menunggu ON perubahan_hak_milik (hak_milik_id) WHERE status = 'menunggu_kelulusan'");
    }

    public function down(): void
    {
        foreach (['perubahan_hak_milik', 'cadangan_perubahan', 'urusan_pendaftaran_pt', 'permohonan_hak_milik_kekal_peringkat', 'permohonan_hak_milik_kekal', 'sekatan_kepentingan', 'waris_pemilik_bersama', 'kronologi_pemilikan', 'sejarah_status_tanah', 'pengeluaran_dokumen', 'daftar_peti_besi', 'peti_besi', 'hak_milik_versi', 'hak_milik'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
