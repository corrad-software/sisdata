<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-AST · Inventori Aset Fizikal (SRS-AST-01..26). Entity names follow the
 * SRS ("Entiti data"). `aset_fizikal` is 1 : N `lot_tanah` and is NOT merged
 * with `data_spatial` (SRS-PRO-07) — the asset point lives on the asset row.
 * kod_daerah / kod_mukim / klasifikasi are copied from the parent lot so the
 * RBC record scope (ScopedByAccess) applies to every asset table. Asset rows
 * are never deleted (archived — SRS security control); value / condition /
 * officer changes are kept append-only in aset_audit (SRS-AST-23).
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-AST-03: configurable categories with maintenance cycle and revaluation threshold.
        Schema::create('aset_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('nama_kategori', 120);
            $table->string('jenis_lalai', 30)->default('bangunan');
            $table->unsignedSmallInteger('kitaran_penyelenggaraan_bulan')->default(12);
            $table->unsignedSmallInteger('tempoh_ambang_penilaian_bulan')->default(60);
            $table->unsignedSmallInteger('tempoh_pemeriksaan_bulan')->default(12)->comment('SRS-AST-21: aset tidak diperiksa melebihi tempoh ini');
            $table->unsignedSmallInteger('hayat_guna_tahun')->default(50)->comment('susut nilai garis lurus');
            $table->decimal('kadar_nilai_sisa', 5, 2)->default(10)->comment('% kos perolehan');
            $table->jsonb('jenis_penyelenggaraan_lalai')->default('[]')->comment('jenis penyelenggaraan pencegahan dijana automatik');
            $table->boolean('status_aktif')->default(true);
            $table->unsignedSmallInteger('susunan')->default(0);
            $table->timestamps();
        });

        Schema::create('aset_kontraktor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_syarikat', 200);
            $table->string('no_pendaftaran', 40)->nullable()->comment('No. SSM / CIDB');
            $table->string('no_telefon', 30)->nullable();
            $table->string('emel', 150)->nullable();
            $table->jsonb('bidang')->default('[]');
            $table->date('tarikh_tamat_pendaftaran')->nullable();
            $table->foreignId('id_pengguna')->nullable()->comment('akaun kontraktor (capaian terhad)')->constrained('users')->nullOnDelete();
            $table->boolean('status_aktif')->default(true);
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('nama_syarikat');
        });

        // SRS-AST-01/02/04: the register — many assets per lot, parent → sub-asset hierarchy.
        Schema::create('aset_fizikal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan_aset', 40)->unique();
            $table->string('qr_ref', 24)->unique();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->foreignUuid('id_aset_induk')->nullable();
            $table->foreignId('id_kategori')->constrained('aset_kategori');
            $table->string('jenis_aset', 30);
            $table->string('nama_aset', 200);
            $table->text('keterangan')->nullable();
            $table->text('alamat')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->unsignedSmallInteger('tahun_pembinaan')->nullable();
            $table->unsignedSmallInteger('tahun_perolehan')->nullable();
            $table->date('tarikh_perolehan')->nullable();
            $table->decimal('saiz_keluasan', 14, 2)->nullable();
            $table->string('unit_ukuran', 10)->default('m2');
            $table->decimal('kos_perolehan', 16, 2)->nullable();
            $table->decimal('nilai_semasa', 16, 2)->nullable();
            $table->date('tarikh_nilai_semasa')->nullable();
            $table->string('status_kondisi', 20)->default('baik')->comment('baik | perlu_perhatian | kritikal');
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tarikh_pemeriksaan_terakhir')->nullable();
            $table->string('status_aset', 30)->default('aktif')->comment('aktif | dalam_penyelenggaraan | tidak_aktif | dilupuskan | dihapus_kira | dirobohkan');
            $table->string('status_kelulusan', 20)->default('lulus')->comment('lulus | menunggu | ditolak (perolehan melebihi ambang — WFE)');
            $table->string('id_aliran', 64)->nullable();
            $table->boolean('status_arkib')->default(false);
            $table->date('tarikh_arkib')->nullable();
            $table->text('sebab_arkib')->nullable();
            $table->unsignedBigInteger('id_premis_pjk')->nullable()->comment('pautan premis_aset MOD-PJK (aset ialah premis disewa)');
            $table->unsignedBigInteger('id_sumber_gis')->nullable()->unique()->comment('gis_aset_titik.id bagi rekod diimport');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index(['lot_id']);
            $table->index(['status_kondisi', 'status_arkib']);
            $table->index('id_aset_induk');
        });
        Schema::table('aset_fizikal', function (Blueprint $table) {
            $table->foreign('id_aset_induk')->references('id')->on('aset_fizikal');
            $table->foreign('id_premis_pjk')->references('id')->on('premis_aset');
        });
        DB::statement('ALTER TABLE aset_fizikal ADD COLUMN koordinat geometry(Point, 4326)');
        DB::statement('CREATE INDEX aset_fizikal_koordinat_gix ON aset_fizikal USING GIST (koordinat)');
        // One premise ↔ one asset (link, don't duplicate).
        DB::statement('CREATE UNIQUE INDEX aset_fizikal_premis_unik ON aset_fizikal (id_premis_pjk) WHERE id_premis_pjk IS NOT NULL');
        DB::statement('CREATE INDEX aset_fizikal_nama_trgm ON aset_fizikal USING GIN (nama_aset gin_trgm_ops)');

        // SRS-AST-06: QR references are never reused — even after archiving (no delete, PK).
        Schema::create('aset_qr', function (Blueprint $table) {
            $table->string('qr_ref', 24)->primary();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->timestamp('tarikh_jana');
            $table->string('pautan_awam', 255);
            $table->boolean('status_aktif')->default(true);
            $table->timestamp('tarikh_nyahaktif')->nullable();
            $table->string('sebab_nyahaktif', 255)->nullable();
            $table->string('templat_label', 20)->nullable();
            $table->unsignedInteger('bilangan_cetakan')->default(0);
            $table->timestamp('tarikh_cetakan_terakhir')->nullable();
            $table->timestamps();
            $table->index('id_aset');
        });

        // SRS-AST-10: scan log.
        Schema::create('aset_qr_log_imbasan', function (Blueprint $table) {
            $table->id();
            $table->string('qr_ref', 24);
            $table->foreignUuid('id_aset')->nullable()->constrained('aset_fizikal');
            $table->timestamp('cap_masa');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('peranan_pengimbas', 120)->nullable();
            $table->decimal('latitud_peranti', 10, 7)->nullable();
            $table->decimal('longitud_peranti', 10, 7)->nullable();
            $table->decimal('ketepatan_m', 8, 2)->nullable();
            $table->string('jenis_paparan', 20)->comment('minimum | penuh | tidak_aktif | tidak_sah');
            $table->string('ejen_pengguna', 255)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->index(['id_aset', 'cap_masa']);
            $table->index('cap_masa');
        });

        // SRS-AST-11: free and structured tags.
        Schema::create('aset_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('nama_tag', 60);
            $table->string('jenis_tag', 20)->default('bebas')->comment('bebas | berstruktur');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_tambah');
            $table->unique(['id_aset', 'nama_tag']);
            $table->index('nama_tag');
        });

        // SRS-AST-20/21: inspections (PWA / manual / QR).
        Schema::create('aset_pemeriksaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->date('tarikh_pemeriksaan');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kaedah', 20)->default('manual')->comment('pwa | manual | qr');
            $table->string('status_kondisi_dilapor', 20);
            $table->text('isu_dikenalpasti')->nullable();
            $table->text('tindakan_disyorkan')->nullable();
            $table->jsonb('senarai_semak')->default('[]');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('ketepatan_gps_m', 8, 2)->nullable();
            $table->decimal('jarak_dari_aset_m', 10, 2)->nullable();
            $table->timestamp('masa_mula')->nullable();
            $table->timestamp('masa_tamat')->nullable();
            $table->string('status_penyegerakan', 20)->default('dalam_talian')->comment('dalam_talian | disegerak');
            $table->uuid('id_luar_talian')->nullable()->unique();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_aset', 'tarikh_pemeriksaan']);
        });

        // SRS-AST-05: condition history.
        Schema::create('aset_kondisi_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('status_lama', 20)->nullable();
            $table->string('status_baharu', 20);
            $table->timestamp('tarikh_perubahan');
            $table->foreignUuid('id_pemeriksaan')->nullable()->constrained('aset_pemeriksaan');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->index(['id_aset', 'tarikh_perubahan']);
        });

        // SRS-AST-18: value history by year, with the report it is based on.
        Schema::create('aset_nilai_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('nilai', 16, 2);
            $table->decimal('nilai_sebelum', 16, 2)->nullable();
            $table->string('kaedah_penilaian', 30)->comment('laporan_jpph | penilai_berdaftar | penilaian_dalaman | kos_perolehan | susut_nilai');
            $table->string('id_laporan_penilaian', 80)->nullable()->comment('no. rujukan laporan penilaian');
            $table->string('id_dokumen_dms', 64)->nullable();
            $table->date('tarikh_penilaian');
            $table->timestamp('tarikh_rekod');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->index(['id_aset', 'tahun']);
        });

        // SRS-AST-15: preventive maintenance schedule (one row per asset × jenis; due date rolls forward).
        Schema::create('jadual_penyelenggaraan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('jenis_penyelenggaraan', 40);
            $table->unsignedSmallInteger('kitaran_bulan');
            $table->date('tarikh_jatuh_tempoh');
            $table->unsignedSmallInteger('tempoh_amaran_hari')->default(14);
            $table->string('status', 20)->default('aktif')->comment('aktif | tidak_aktif');
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('dijana_automatik')->default(false);
            $table->date('tarikh_terakhir_dilaksana')->nullable();
            $table->date('tarikh_eskalasi')->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->unique(['id_aset', 'jenis_penyelenggaraan']);
            $table->index(['status', 'tarikh_jatuh_tempoh']);
        });

        // SRS-AST-14/17: maintenance work (preventive from a schedule, corrective, from MOD-PTN needs).
        Schema::create('rekod_penyelenggaraan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan', 30)->unique();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->foreignId('id_jadual')->nullable()->constrained('jadual_penyelenggaraan');
            $table->string('jenis_kerja', 20)->default('pembaikan')->comment('pencegahan | pembaikan | kecemasan');
            $table->string('jenis_penyelenggaraan', 40);
            $table->text('keterangan');
            $table->string('keutamaan', 20)->default('sederhana');
            $table->date('tarikh_dijadual')->nullable();
            $table->date('tarikh_mula')->nullable();
            $table->date('tarikh_siap')->nullable();
            $table->decimal('kos_anggaran', 14, 2)->nullable();
            $table->decimal('kos', 14, 2)->nullable();
            $table->foreignId('id_kontraktor')->nullable()->constrained('aset_kontraktor')->nullOnDelete();
            $table->string('nama_syarikat_pelaksana', 200)->nullable();
            $table->string('status', 30)->default('dirancang')->comment('dirancang | menunggu_kelulusan | diluluskan | dalam_pelaksanaan | selesai | ditolak | dibatalkan');
            $table->string('id_aliran', 64)->nullable();
            $table->unsignedBigInteger('id_gambar_sebelum')->nullable();
            $table->unsignedBigInteger('id_gambar_selepas')->nullable();
            $table->unsignedBigInteger('id_keperluan_ptn')->nullable()->unique()->comment('keperluan_penyelenggaraan.id (MOD-PTN)');
            $table->foreignUuid('id_pemeriksaan')->nullable()->constrained('aset_pemeriksaan');
            $table->string('sumber', 20)->default('manual')->comment('manual | jadual | ptn | pemeriksaan');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_penyelesaian')->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_aset', 'status']);
            $table->index(['status', 'tarikh_dijadual']);
            $table->index('tarikh_siap');
        });

        // SRS-AST-12: dated gallery (DMS holds the file).
        Schema::create('aset_gambar', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->foreignUuid('id_pemeriksaan')->nullable()->constrained('aset_pemeriksaan');
            $table->foreignUuid('id_rekod_penyelenggaraan')->nullable()->constrained('rekod_penyelenggaraan');
            $table->string('laluan_fail', 64)->nullable()->comment('id dokumen DMS');
            $table->timestamp('tarikh_gambar');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('ketepatan_m', 8, 2)->nullable();
            $table->foreignId('id_pegawai_rakam')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kategori_gambar', 20)->default('umum')->comment('umum | pemeriksaan | sebelum | selepas');
            $table->string('keterangan', 255)->nullable();
            $table->string('nama_fail', 255)->nullable();
            $table->uuid('id_luar_talian')->nullable()->unique();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_aset', 'tarikh_gambar']);
        });

        // SRS-AST-22: supporting documents through DMS (with expiry).
        Schema::create('aset_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('id_dokumen_dms', 64);
            $table->string('kategori_dokumen', 30);
            $table->string('tajuk', 200)->nullable();
            $table->date('tarikh_luput')->nullable();
            $table->timestamp('tarikh_muat_naik');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->timestamps();
            $table->index('id_aset');
        });

        Schema::create('aset_insurans', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('no_polisi', 60);
            $table->string('penanggung', 150);
            $table->string('jenis_perlindungan', 80)->nullable();
            $table->decimal('nilai_dilindungi', 16, 2)->nullable();
            $table->decimal('premium', 12, 2)->nullable();
            $table->date('tarikh_mula');
            $table->date('tarikh_tamat');
            $table->string('id_dokumen_dms', 64)->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_aset', 'tarikh_tamat']);
        });

        Schema::create('aset_jaminan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('perkara', 200);
            $table->string('pembekal', 150)->nullable();
            $table->date('tarikh_mula');
            $table->date('tarikh_tamat');
            $table->string('id_dokumen_dms', 64)->nullable();
            $table->text('catatan')->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_aset', 'tarikh_tamat']);
        });

        // Disposal / write-off / demolition — approved through MOD-WFE, asset archived (never deleted).
        Schema::create('aset_pelupusan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('jenis', 20)->comment('pelupusan | hapus_kira | roboh');
            $table->text('sebab');
            $table->string('kaedah', 30)->nullable()->comment('jualan | tender | sebut_harga | musnah | pindahan | lain');
            $table->decimal('nilai_pelupusan', 16, 2)->nullable();
            $table->date('tarikh_dicadang')->nullable();
            $table->string('status', 20)->default('menunggu')->comment('menunggu | lulus | tolak | ditarik_balik');
            $table->string('id_aliran', 64)->nullable();
            $table->foreignId('id_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->text('ulasan')->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->timestamps();
            $table->index(['id_aset', 'status']);
        });

        // SRS-AST-23: append-only trail of value / condition / responsible-officer changes.
        Schema::create('aset_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_aset')->constrained('aset_fizikal');
            $table->string('medan_diubah', 60);
            $table->text('nilai_sebelum')->nullable();
            $table->text('nilai_selepas')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sebab', 255)->nullable();
            $table->timestamp('cap_masa');
            $table->index(['id_aset', 'cap_masa']);
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION aset_audit_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'aset_audit is append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER aset_audit_no_update BEFORE UPDATE OR DELETE ON aset_audit FOR EACH ROW EXECUTE FUNCTION aset_audit_append_only();
            CREATE OR REPLACE FUNCTION aset_qr_no_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'aset_qr references are never deleted or reassigned';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER aset_qr_no_delete BEFORE DELETE ON aset_qr FOR EACH ROW EXECUTE FUNCTION aset_qr_no_delete();
            CREATE TRIGGER aset_qr_no_reassign BEFORE UPDATE OF qr_ref, id_aset ON aset_qr FOR EACH ROW EXECUTE FUNCTION aset_qr_no_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS aset_audit_no_update ON aset_audit; DROP TRIGGER IF EXISTS aset_qr_no_delete ON aset_qr; DROP TRIGGER IF EXISTS aset_qr_no_reassign ON aset_qr;');
        foreach (['aset_audit', 'aset_pelupusan', 'aset_jaminan', 'aset_insurans', 'aset_dokumen', 'aset_gambar', 'rekod_penyelenggaraan', 'jadual_penyelenggaraan', 'aset_nilai_sejarah', 'aset_kondisi_sejarah', 'aset_pemeriksaan', 'aset_tag', 'aset_qr_log_imbasan', 'aset_qr', 'aset_fizikal', 'aset_kontraktor', 'aset_kategori'] as $t) {
            Schema::dropIfExists($t);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS aset_audit_append_only(); DROP FUNCTION IF EXISTS aset_qr_no_delete();');
    }
};
