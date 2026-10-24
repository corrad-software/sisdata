<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-CKI · Pemantauan Cukai Tanah (SRS Modul 6). The §4.1 `cukai` skeleton
 * created by MOD-PRO is the BILL table (`bil_cukai` in the module entity
 * list — PLAN §14 #2: Bab 4 names win) and is EXTENDED here, never
 * re-created. New tables follow the SRS entity names: obligasi_cukai,
 * transaksi_bayaran_cukai, kelulusan_bayaran, daftar_liputan_integrasi,
 * log_integrasi_cukai, mod_operasi_integrasi, konflik_penyegerakan,
 * penyelarasan_grp_cukai, plus resit_cukai, lejar_cukai, pelarasan_cukai and
 * rekonsiliasi_cukai_larian. Money is DECIMAL(14,2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_liputan_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_agensi', 30)->unique()->comment('PTD-<kod_daerah> atau kod PBT');
            $table->string('jenis_agensi', 10)->comment('ptd | pbt');
            $table->string('kod_pbt', 20)->nullable();
            $table->string('kod_ptd', 4)->nullable()->comment('Kod daerah Pejabat Tanah');
            $table->string('nama_agensi', 200);
            $table->boolean('perkhidmatan_semakan')->default(false);
            $table->boolean('perkhidmatan_bayaran')->default(false);
            $table->string('mod_semasa', 10)->default('api')->comment('api | sandaran');
            $table->unsignedSmallInteger('kegagalan_berturut')->default(0);
            $table->timestamp('semakan_terakhir')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('tarikh_kemas_kini')->nullable();
            $table->timestamps();
        });

        Schema::create('obligasi_cukai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->unsignedBigInteger('id_premis')->nullable()->comment('premis_aset (MOD-PJK) bagi cukai taksiran premis');
            $table->string('jenis_cukai', 20)->comment('tanah | petak | taksiran_pbt | premium | fi | lain');
            $table->string('pihak_pengeluar', 10)->comment('ptd | pbt | ptg | lain');
            $table->string('kod_pbt', 20)->nullable();
            $table->string('kod_ptd', 4)->nullable();
            $table->string('kod_agensi', 30)->index();
            $table->string('no_akaun_cukai', 60);
            $table->string('status_liputan_api', 10)->default('luar')->comment('dalam | luar');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('aktif')->default(true);
            $table->boolean('sintetik')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index('lot_id');
        });
        DB::statement('CREATE UNIQUE INDEX obligasi_cukai_akaun_unik ON obligasi_cukai (jenis_cukai, kod_agensi, no_akaun_cukai) WHERE deleted_at IS NULL');

        Schema::table('cukai', function (Blueprint $table) {
            $table->foreignUuid('obligasi_id')->nullable()->after('lot_id')->constrained('obligasi_cukai');
            $table->unsignedSmallInteger('tahun_taksiran')->nullable();
            $table->string('pihak_pengeluar', 10)->nullable();
            $table->string('kod_agensi', 30)->nullable();
            $table->string('kod_pbt', 20)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->string('kod_mukim', 6)->nullable();
            $table->decimal('amaun_pokok', 14, 2)->default(0);
            $table->decimal('amaun_denda', 14, 2)->default(0);
            $table->decimal('amaun_dibayar', 14, 2)->default(0);
            $table->date('tarikh_bil')->nullable();
            $table->date('tarikh_bayar')->nullable();
            $table->string('no_resit', 60)->nullable();
            $table->string('sumber_rekod', 10)->default('manual')->comment('api | manual | import');
            $table->string('id_dokumen_bil', 64)->nullable();
            $table->timestamp('disegerak_pada')->nullable();
            $table->boolean('denda_dikenakan')->default(false);
            $table->boolean('sintetik')->default(false);
            $table->unsignedInteger('versi')->default(1);
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index(['status_bayaran', 'tarikh_akhir']);
            $table->index('tahun_taksiran');
        });
        DB::statement('CREATE UNIQUE INDEX cukai_bil_unik ON cukai (obligasi_id, tahun_taksiran, no_bil) WHERE deleted_at IS NULL AND obligasi_id IS NOT NULL');

        Schema::create('kelulusan_bayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_bil')->constrained('cukai');
            $table->decimal('amaun', 14, 2);
            $table->string('peringkat_pelulus', 30)->comment('kewangan | ketua_bahagian | pengurusan_atasan');
            $table->unsignedTinyInteger('tahap')->default(1);
            $table->string('id_aliran', 64)->nullable()->comment('Instance MOD-WFE');
            $table->foreignId('id_pegawai_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pegawai_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_kelulusan')->nullable();
            $table->text('justifikasi')->nullable();
            $table->string('status', 20)->default('berjalan')->comment('berjalan | lulus | tolak | ditarik_balik | dibatalkan');
            $table->timestamps();
        });

        Schema::create('transaksi_bayaran_cukai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_permohonan', 30)->unique();
            $table->foreignUuid('id_bil')->constrained('cukai');
            $table->string('kaedah_bayaran', 20)->comment('eft | bank_draf | fpx | kaunter_ptd | kaunter_pbt');
            $table->decimal('amaun', 14, 2);
            $table->date('tarikh_bayaran')->nullable();
            $table->string('no_rujukan_eft', 60)->nullable();
            $table->string('no_bank_draf', 60)->nullable();
            $table->string('bank_penerbit', 120)->nullable();
            $table->string('rujukan_fpx', 60)->nullable();
            $table->string('nama_kaunter', 120)->nullable();
            $table->string('no_resit', 60)->nullable();
            $table->string('id_dokumen_resit', 64)->nullable();
            $table->string('id_dokumen_permohonan', 64)->nullable();
            $table->string('status_transaksi', 20)->default('menunggu_kelulusan')
                ->comment('menunggu_kelulusan | diluluskan | ditolak | dihantar_grp | dibayar | selesai | dibatalkan');
            $table->foreignId('id_kelulusan')->nullable()->constrained('kelulusan_bayaran')->nullOnDelete();
            $table->boolean('melalui_api')->default(false);
            $table->string('rujukan_grp', 60)->nullable();
            $table->string('status_grp', 20)->nullable();
            $table->string('rujukan_transaksi_api', 60)->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('id_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status_transaksi', 'kod_daerah']);
        });
        Schema::table('kelulusan_bayaran', function (Blueprint $table) {
            $table->foreignUuid('id_transaksi')->nullable()->after('id')->constrained('transaksi_bayaran_cukai');
        });

        Schema::create('resit_cukai', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_transaksi')->nullable()->constrained('transaksi_bayaran_cukai');
            $table->foreignUuid('id_bil')->nullable()->constrained('cukai');
            $table->string('no_resit', 60);
            $table->string('no_bil_rujukan', 60)->nullable()->comment('No. bil yang tertera pada resit');
            $table->string('kod_agensi', 30)->nullable();
            $table->decimal('amaun', 14, 2);
            $table->date('tarikh_resit');
            $table->string('sumber', 10)->comment('api | manual');
            $table->string('id_dokumen', 64)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->boolean('sintetik')->default(false);
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('no_resit');
        });

        Schema::create('lejar_cukai', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_bil')->constrained('cukai');
            $table->date('tarikh');
            $table->string('jenis', 15)->comment('bil | denda | bayaran | pelarasan');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('kredit', 14, 2)->default(0);
            $table->string('rujukan', 80)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('id_bil');
        });

        Schema::create('pelarasan_cukai', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_bil')->constrained('cukai');
            $table->string('medan', 40);
            $table->string('nilai_lama', 255)->nullable();
            $table->string('nilai_baharu', 255)->nullable();
            $table->text('justifikasi');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('log_integrasi_cukai', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_panggilan', 30)->comment('semak_bil | bayar | ping | grp_hantar | grp_status | grp_bayaran');
            $table->string('kod_agensi', 30)->nullable();
            $table->foreignUuid('id_obligasi')->nullable()->constrained('obligasi_cukai')->nullOnDelete();
            $table->timestamp('masa_panggilan');
            $table->unsignedInteger('masa_tindak_balas')->nullable()->comment('ms');
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->boolean('berjaya');
            $table->string('kod_ralat', 60)->nullable();
            $table->jsonb('ringkasan_payload')->nullable();
            $table->string('rujukan_transaksi', 80)->nullable();
            $table->unsignedSmallInteger('bilangan_cuba_semula')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kod_agensi', 'masa_panggilan']);
        });

        Schema::create('mod_operasi_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_agensi', 30)->index();
            $table->string('mod', 10)->comment('sandaran');
            $table->timestamp('tarikh_mula');
            $table->timestamp('tarikh_tamat')->nullable();
            $table->string('sebab', 255)->nullable();
            $table->foreignId('id_pegawai_maklum')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('konflik_penyegerakan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_bil')->constrained('cukai');
            $table->jsonb('nilai_manual');
            $table->jsonb('nilai_api');
            $table->jsonb('medan_berkonflik');
            $table->string('status_resolusi', 20)->default('menunggu')->comment('menunggu | terima_api | kekal_manual');
            $table->foreignId('id_pegawai_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_resolusi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('rekonsiliasi_cukai_larian', function (Blueprint $table) {
            $table->id();
            $table->date('dari');
            $table->date('hingga');
            $table->unsignedInteger('bil_padan')->default(0);
            $table->unsignedInteger('bil_sisihan')->default(0);
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('penyelarasan_grp_cukai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_larian')->nullable()->constrained('rekonsiliasi_cukai_larian')->cascadeOnDelete();
            $table->foreignUuid('id_transaksi')->nullable()->constrained('transaksi_bayaran_cukai');
            $table->foreignUuid('id_bil')->nullable()->constrained('cukai');
            $table->foreignId('id_resit')->nullable()->constrained('resit_cukai');
            $table->string('jenis', 30)->default('penyelarasan')
                ->comment('penyelarasan | bil_tanpa_resit | resit_tanpa_bil | beza_amaun_resit | beza_amaun_grp | tiada_dalam_grp');
            $table->string('rujukan_grp', 60)->nullable();
            $table->decimal('amaun_sistem', 14, 2)->nullable();
            $table->decimal('amaun_grp', 14, 2)->nullable();
            $table->decimal('amaun_resit', 14, 2)->nullable();
            $table->string('status_padanan', 20)->default('menunggu')->comment('menunggu | padan | sisihan | diselesaikan');
            $table->text('catatan_sisihan')->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('cki_penomboran', function (Blueprint $table) {
            $table->string('jenis', 20);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nombor_akhir')->default(0);
            $table->primary(['jenis', 'tahun']);
        });

        // Kawalan keselamatan: bills and receipts are never deleted; the ledger
        // and corrections are append-only (corrections go through pelarasan_cukai).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION cki_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod cukai tidak boleh % (%); gunakan rekod pelarasan', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
            CREATE OR REPLACE FUNCTION cki_resit_kunci() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'UPDATE' AND NEW.no_resit = OLD.no_resit AND NEW.amaun = OLD.amaun AND NEW.tarikh_resit = OLD.tarikh_resit
                   AND NEW.sumber = OLD.sumber
                   AND (OLD.id_dokumen IS NULL OR NEW.id_dokumen IS NOT DISTINCT FROM OLD.id_dokumen)
                   AND (OLD.id_bil IS NULL OR NEW.id_bil IS NOT DISTINCT FROM OLD.id_bil)
                   AND (OLD.id_transaksi IS NULL OR NEW.id_transaksi IS NOT DISTINCT FROM OLD.id_transaksi) THEN
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Resit cukai tidak boleh diubah atau dipadam (%)', TG_OP USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
            CREATE TRIGGER cukai_tiada_padam BEFORE DELETE ON cukai FOR EACH ROW EXECUTE FUNCTION cki_tolak_ubah();
            CREATE TRIGGER resit_cukai_kunci BEFORE UPDATE OR DELETE ON resit_cukai FOR EACH ROW EXECUTE FUNCTION cki_resit_kunci();
            CREATE TRIGGER lejar_cukai_hanya_tambah BEFORE UPDATE OR DELETE ON lejar_cukai FOR EACH ROW EXECUTE FUNCTION cki_tolak_ubah();
            CREATE TRIGGER pelarasan_cukai_hanya_tambah BEFORE UPDATE OR DELETE ON pelarasan_cukai FOR EACH ROW EXECUTE FUNCTION cki_tolak_ubah();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cukai_tiada_padam ON cukai');
        foreach (['cki_penomboran', 'penyelarasan_grp_cukai', 'rekonsiliasi_cukai_larian', 'konflik_penyegerakan', 'mod_operasi_integrasi', 'log_integrasi_cukai', 'pelarasan_cukai', 'lejar_cukai', 'resit_cukai'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('kelulusan_bayaran', fn (Blueprint $t) => $t->dropConstrainedForeignId('id_transaksi'));
        Schema::dropIfExists('transaksi_bayaran_cukai');
        Schema::dropIfExists('kelulusan_bayaran');
        DB::statement('DROP INDEX IF EXISTS cukai_bil_unik');
        Schema::table('cukai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obligasi_id');
            $table->dropConstrainedForeignId('id_pencipta');
            $table->dropIndex(['kod_daerah', 'kod_mukim']);
            $table->dropIndex(['status_bayaran', 'tarikh_akhir']);
            $table->dropIndex(['tahun_taksiran']);
            $table->dropColumn(['tahun_taksiran', 'pihak_pengeluar', 'kod_agensi', 'kod_pbt', 'kod_daerah', 'kod_mukim', 'amaun_pokok', 'amaun_denda', 'amaun_dibayar',
                'tarikh_bil', 'tarikh_bayar', 'no_resit', 'sumber_rekod', 'id_dokumen_bil', 'disegerak_pada', 'denda_dikenakan', 'sintetik', 'versi']);
        });
        Schema::dropIfExists('obligasi_cukai');
        Schema::dropIfExists('daftar_liputan_integrasi');
        DB::unprepared('DROP FUNCTION IF EXISTS cki_tolak_ubah(); DROP FUNCTION IF EXISTS cki_resit_kunci();');
    }
};
