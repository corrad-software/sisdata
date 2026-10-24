<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-TSU · Tukar Syarat, Pelupusan dan Ukur — entities of SRS §10 Modul 10
 * (urusan_tanah = the `permohonan_tanah` satellite of SRS §4.1, 1:N lot via
 * urusan_lot). Money is DECIMAL; a closed urusan is locked by trigger
 * (SRS-TSU-24); decisions, Land Office status history and lot lineage are
 * append-only; nothing here is ever deleted (SRS-TSU-06/21).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Templates (SRS-TSU-01/02) ---------------------------------------------
        Schema::create('templat_proses', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_urusan', 40);
            $table->unsignedInteger('versi')->default(1);
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();
            $table->string('aliran_kelulusan', 80)->nullable()->comment('Kod aliran MOD-WFE bagi peringkat yang perlu kelulusan');
            $table->boolean('status_aktif')->default(true);
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['jenis_urusan', 'versi']);
        });
        DB::statement('CREATE UNIQUE INDEX templat_proses_satu_aktif ON templat_proses (jenis_urusan) WHERE status_aktif');

        Schema::create('templat_peringkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('templat_id')->constrained('templat_proses')->cascadeOnDelete();
            $table->unsignedSmallInteger('no_urutan');
            $table->string('kod', 60);
            $table->string('nama_peringkat', 150);
            $table->text('tindakan_diperlukan')->nullable();
            $table->string('pihak_bertanggungjawab', 30)->comment('mais | pejabat_tanah | ptg | jurukur | pbt | jpph | jupem');
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->jsonb('senarai_dokumen_wajib')->default('[]');
            $table->jsonb('senarai_kategori_kos')->default('[]');
            $table->boolean('perlu_kelulusan')->default(false);
            $table->string('kesan', 40)->nullable()->comment('Tindakan sistem apabila peringkat selesai');
            $table->timestamps();
            $table->unique(['templat_id', 'no_urutan']);
        });

        // ---- Urusan (SRS §4.1 permohonan_tanah) ------------------------------------
        Schema::create('urusan_tanah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis_urusan', 40);
            $table->foreignId('templat_id')->constrained('templat_proses');
            $table->string('no_rujukan_mais', 40)->unique();
            $table->string('no_rujukan_ptd', 60)->nullable();
            $table->string('no_rujukan_etanah', 60)->nullable();
            $table->string('pejabat_tanah', 150)->nullable();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('bahagian_pemilik', 30)->nullable();
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->string('tajuk', 255);
            $table->text('keterangan')->nullable();
            $table->jsonb('butiran')->default('{}')->comment('Medan khusus jenis urusan (kategori/syarat baharu, pihak penerima…)');
            $table->string('kaedah_pelupusan', 20)->nullable()->comment('jualan | tukaran | pindah_milik | pajakan');
            $table->decimal('nilai_penilaian', 16, 2)->nullable()->comment('Rahsia — nilai laporan penilaian JPPH terkini');
            $table->text('justifikasi')->nullable();
            $table->unsignedSmallInteger('peringkat_semasa')->nullable();
            $table->string('status', 30)->default('aktif');
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->string('status_ptd', 40)->nullable();
            $table->string('sumber_status_ptd', 10)->nullable()->comment('api | manual');
            $table->timestamp('tarikh_status_ptd')->nullable();
            $table->date('tarikh_susulan_ptd')->nullable();
            $table->date('tarikh_mula');
            $table->date('tarikh_sasaran_siap');
            $table->date('tarikh_siap')->nullable();
            $table->timestamp('tarikh_tutup')->nullable();
            $table->boolean('dikunci')->default(false);
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pendaftar')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();

            $table->index(['jenis_urusan', 'status']);
            $table->index(['kod_daerah', 'kod_mukim']);
        });

        Schema::create('urusan_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignUuid('lot_id')->constrained('lot_tanah')->restrictOnDelete();
            $table->string('peranan_lot', 20)->comment('asal | baharu | terlibat');
            $table->timestamp('tarikh_paut')->useCurrent();
            $table->unique(['urusan_id', 'lot_id']);
            $table->index('lot_id');
        });

        Schema::create('urusan_peringkat', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->unsignedSmallInteger('no_urutan');
            $table->string('kod', 60);
            $table->string('nama_peringkat', 150);
            $table->text('tindakan_diperlukan')->nullable();
            $table->string('pihak_bertanggungjawab', 30);
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->date('tarikh_mula')->nullable();
            $table->date('tarikh_sasaran')->nullable();
            $table->date('tarikh_selesai')->nullable();
            $table->string('status', 30)->default('belum_mula');
            $table->jsonb('senarai_dokumen_wajib')->default('[]');
            $table->jsonb('senarai_kategori_kos')->default('[]');
            $table->boolean('perlu_kelulusan')->default(false);
            $table->string('kesan', 40)->nullable();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedSmallInteger('aras_eskalasi')->default(0);
            $table->foreignId('id_pegawai_selesai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['urusan_id', 'no_urutan']);
            $table->index(['status', 'tarikh_sasaran']);
        });

        // ---- Costs (SRS-TSU-14/15) ------------------------------------------------
        Schema::create('kos_urusan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignId('peringkat_id')->nullable()->constrained('urusan_peringkat')->restrictOnDelete();
            $table->string('kategori_kos', 30);
            $table->string('keterangan', 255)->nullable();
            $table->decimal('amaun', 14, 2);
            $table->string('status_bayaran', 20)->default('belum_bayar');
            $table->string('no_invois', 60)->nullable();
            $table->date('tarikh_invois')->nullable();
            $table->date('tarikh_akhir_bayaran')->nullable();
            $table->date('tarikh_bayaran')->nullable();
            $table->string('no_resit', 60)->nullable();
            $table->string('id_dokumen', 64)->nullable();
            $table->string('rujukan_grp', 60)->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pengemaskini')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['urusan_id', 'kategori_kos']);
            $table->index(['status_bayaran', 'tarikh_akhir_bayaran']);
        });

        // ---- Surveyor procurement (SRS-TSU-08..13) ---------------------------------
        Schema::create('panel_jurukur', function (Blueprint $table) {
            $table->id();
            $table->string('nama_firma', 200);
            $table->string('no_lesen_jurukur', 40)->unique();
            $table->string('nama_jurukur_bertanggungjawab', 150);
            $table->jsonb('maklumat_perhubungan')->default('{}');
            $table->unsignedSmallInteger('pengalaman_tahun')->nullable();
            $table->string('status_panel', 20)->default('aktif');
            $table->date('tarikh_tamat_lesen');
            $table->foreignId('id_pengguna')->nullable()->comment('Akaun capaian terhad jurukur luar')->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
        });

        Schema::create('sebutharga_ukur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->string('no_jemputan', 40)->unique();
            $table->date('tarikh_jemputan');
            $table->timestamp('tarikh_tutup');
            $table->text('skop_kerja');
            $table->string('status', 30)->default('dibuka');
            $table->string('id_dokumen_jemputan', 64)->nullable();
            $table->unsignedBigInteger('id_tawaran_disyorkan')->nullable();
            $table->text('justifikasi_pemilihan')->nullable();
            $table->foreignId('id_penilai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_kelulusan')->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sebutharga_tawaran', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('sebutharga_id')->constrained('sebutharga_ukur')->restrictOnDelete();
            $table->foreignId('jurukur_id')->constrained('panel_jurukur');
            $table->string('status', 20)->default('dijemput')->comment('dijemput | diterima | tidak_respons');
            $table->text('harga_tawaran')->nullable()->comment('Disulitkan sehingga tarikh tutup');
            $table->unsignedInteger('tempoh_siap_hari')->nullable();
            $table->unsignedSmallInteger('pengalaman_tahun')->nullable();
            $table->timestamp('tarikh_terima')->nullable();
            $table->decimal('markah', 5, 2)->nullable();
            $table->text('catatan_penilaian')->nullable();
            $table->boolean('indikator_disyorkan')->default(false);
            $table->timestamps();
            $table->unique(['sebutharga_id', 'jurukur_id']);
        });

        Schema::create('lantikan_jurukur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignUuid('sebutharga_id')->nullable()->constrained('sebutharga_ukur');
            $table->foreignId('jurukur_id')->constrained('panel_jurukur');
            $table->string('no_surat_setuju_terima', 60);
            $table->date('tarikh_surat_setuju_terima')->nullable();
            $table->decimal('nilai_kontrak', 14, 2);
            $table->date('tarikh_mula');
            $table->unsignedInteger('tempoh_siap_hari');
            $table->date('tarikh_jangka_siap');
            $table->text('skop_kerja');
            $table->string('status', 20)->default('aktif');
            $table->date('tarikh_siap')->nullable();
            $table->unsignedSmallInteger('peratus_siap_semasa')->default(0);
            $table->boolean('lewat')->default(false);
            $table->string('id_dokumen_surat', 64)->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kemajuan_kerja_ukur', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lantikan_id')->constrained('lantikan_jurukur')->restrictOnDelete();
            $table->string('milestone', 150);
            $table->unsignedSmallInteger('peratus_siap');
            $table->unsignedSmallInteger('peratus_dijangka');
            $table->boolean('lewat')->default(false);
            $table->date('tarikh_lapor');
            $table->text('catatan')->nullable();
            $table->foreignId('id_pelapor')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- Survey results (SRS-TSU-16..19) ---------------------------------------
        Schema::create('hasil_ukur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignUuid('lantikan_id')->nullable()->constrained('lantikan_jurukur');
            $table->foreignUuid('lot_id')->nullable()->comment('Lot yang diukur semula (jika bukan lot baharu)')->constrained('lot_tanah');
            $table->string('no_lot_cadangan', 50)->nullable()->comment('No. lot baharu (pecah sempadan / penyatuan)');
            $table->string('format_fail', 10);
            $table->integer('sistem_koordinat');
            $table->decimal('keluasan_dikira', 16, 4);
            $table->decimal('keluasan_dokumen', 16, 4)->nullable();
            $table->decimal('sisihan_peratus', 8, 2)->nullable();
            $table->decimal('toleransi_peratus', 6, 2);
            $table->boolean('dalam_toleransi')->nullable();
            $table->string('status_pengesahan', 20)->default('menunggu');
            $table->text('catatan_pengesahan')->nullable();
            $table->foreignId('id_pemuat_naik')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pegawai_sah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_sah')->nullable();
            $table->string('id_dokumen_fail', 64)->nullable();
            $table->string('id_dokumen_pelan_akui', 64)->nullable();
            $table->uuid('id_lot_dicipta')->nullable();
            $table->unsignedBigInteger('id_spatial_dicadang')->nullable()->comment('data_spatial (MOD-PRO) yang dicadangkan untuk pengesahan');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE hasil_ukur ADD COLUMN geometri geometry(MultiPolygon, 4326) NOT NULL');
        DB::statement('CREATE INDEX hasil_ukur_geometri_gix ON hasil_ukur USING GIST (geometri)');

        Schema::create('lot_sejarah_pecahan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_lot_asal')->constrained('lot_tanah');
            $table->foreignUuid('id_lot_baharu')->constrained('lot_tanah');
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah');
            $table->string('jenis_perubahan', 30);
            $table->date('tarikh_berkuat_kuasa');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['id_lot_asal', 'id_lot_baharu']);
            $table->index('id_lot_baharu');
        });

        Schema::create('pemindahan_rekod', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignUuid('id_lot_asal')->constrained('lot_tanah');
            $table->foreignUuid('id_lot_baharu')->constrained('lot_tanah');
            $table->string('jenis_rekod', 40);
            $table->string('id_rekod', 64);
            $table->string('label_rekod', 255)->nullable();
            $table->string('status_pemindahan', 30)->default('draf');
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pengesah_1')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pengesah_2')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pemindahan')->nullable();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['urusan_id', 'status_pemindahan']);
        });

        // ---- Documents & decisions (SRS-TSU-24/25) ---------------------------------
        Schema::create('urusan_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignId('peringkat_id')->nullable()->constrained('urusan_peringkat');
            $table->string('id_dokumen_dms', 64);
            $table->string('kategori_dokumen', 40);
            $table->string('nama_fail', 255)->nullable();
            $table->date('tarikh_dokumen')->nullable();
            $table->date('tarikh_luput')->nullable();
            $table->timestamp('tarikh_muat_naik')->useCurrent();
            $table->foreignId('id_pemuat_naik')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['urusan_id', 'kategori_dokumen']);
        });

        Schema::create('urusan_keputusan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->foreignId('peringkat_id')->nullable()->constrained('urusan_peringkat');
            $table->string('konteks', 30)->default('urusan')->comment('urusan | sebutharga | pemindahan | hasil_ukur | ptd');
            $table->string('jenis_keputusan', 30);
            $table->string('kod_peringkat_wfe', 60)->nullable();
            $table->timestamp('tarikh_keputusan')->useCurrent();
            $table->text('justifikasi')->nullable();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('indikator_mfa')->default(false);
            $table->string('id_dokumen_kelulusan', 64)->nullable();
            $table->string('id_aliran', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['urusan_id', 'konteks']);
        });

        // ---- Land Office status (SRS-TSU-22) ---------------------------------------
        Schema::create('kemaskini_status_ptd', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('urusan_id')->constrained('urusan_tanah')->restrictOnDelete();
            $table->string('sumber', 10)->comment('api | manual');
            $table->string('status_ptd', 40);
            $table->date('tarikh_status');
            $table->text('catatan')->nullable();
            $table->string('rujukan_ptd', 60)->nullable();
            $table->date('tarikh_susulan')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tsu_penomboran', function (Blueprint $table) {
            $table->string('kunci', 40)->primary();
            $table->unsignedInteger('nombor')->default(0);
        });

        $this->triggers();
    }

    private function triggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tsu_tolak_ubah() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod % tidak boleh diubah atau dipadam (rekod audit MOD-TSU).', TG_TABLE_NAME USING ERRCODE = 'P0001';
            END $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION tsu_tiada_padam() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod % tidak boleh dipadam (SRS-TSU-06/24).', TG_TABLE_NAME USING ERRCODE = 'P0001';
            END $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION tsu_urusan_kunci() RETURNS trigger AS $$
            BEGIN
                IF OLD.dikunci THEN
                    RAISE EXCEPTION 'Urusan % telah ditutup dan dikunci (SRS-TSU-24).', OLD.no_rujukan_mais USING ERRCODE = 'P0001';
                END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION tsu_anak_kunci() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM urusan_tanah WHERE id = NEW.urusan_id AND dikunci) THEN
                    RAISE EXCEPTION 'Urusan telah ditutup dan dikunci (SRS-TSU-24).' USING ERRCODE = 'P0001';
                END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;

            -- SRS-TSU-06/21: a disposed lot, or an original lot after subdivision/amalgamation, is archived — never (soft-)deleted.
            CREATE OR REPLACE FUNCTION tsu_lot_tiada_padam() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL AND (
                    EXISTS (SELECT 1 FROM lot_sejarah_pecahan WHERE id_lot_asal = OLD.id OR id_lot_baharu = OLD.id)
                    OR EXISTS (SELECT 1 FROM urusan_lot ul JOIN urusan_tanah u ON u.id = ul.urusan_id
                               WHERE ul.lot_id = OLD.id AND u.jenis_urusan = 'pelupusan' AND u.status IN ('selesai', 'ditutup'))
                ) THEN
                    RAISE EXCEPTION 'Lot % diarkibkan oleh urusan tanah dan tidak boleh dipadam (SRS-TSU-06/21).', OLD.no_lot USING ERRCODE = 'P0001';
                END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;

            CREATE TRIGGER urusan_keputusan_hanya_tambah BEFORE UPDATE OR DELETE ON urusan_keputusan FOR EACH ROW EXECUTE FUNCTION tsu_tolak_ubah();
            CREATE TRIGGER kemaskini_status_ptd_hanya_tambah BEFORE UPDATE OR DELETE ON kemaskini_status_ptd FOR EACH ROW EXECUTE FUNCTION tsu_tolak_ubah();
            CREATE TRIGGER lot_sejarah_pecahan_hanya_tambah BEFORE UPDATE OR DELETE ON lot_sejarah_pecahan FOR EACH ROW EXECUTE FUNCTION tsu_tolak_ubah();
            CREATE TRIGGER kemajuan_kerja_ukur_hanya_tambah BEFORE UPDATE OR DELETE ON kemajuan_kerja_ukur FOR EACH ROW EXECUTE FUNCTION tsu_tolak_ubah();

            CREATE TRIGGER urusan_tanah_tiada_padam BEFORE DELETE ON urusan_tanah FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER urusan_lot_tiada_padam BEFORE DELETE ON urusan_lot FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER urusan_peringkat_tiada_padam BEFORE DELETE ON urusan_peringkat FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER urusan_dokumen_tiada_padam BEFORE DELETE ON urusan_dokumen FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER kos_urusan_tiada_padam BEFORE DELETE ON kos_urusan FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER hasil_ukur_tiada_padam BEFORE DELETE ON hasil_ukur FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();
            CREATE TRIGGER pemindahan_rekod_tiada_padam BEFORE DELETE ON pemindahan_rekod FOR EACH ROW EXECUTE FUNCTION tsu_tiada_padam();

            CREATE TRIGGER urusan_tanah_kunci BEFORE UPDATE ON urusan_tanah FOR EACH ROW EXECUTE FUNCTION tsu_urusan_kunci();
            CREATE TRIGGER urusan_peringkat_kunci BEFORE INSERT OR UPDATE ON urusan_peringkat FOR EACH ROW EXECUTE FUNCTION tsu_anak_kunci();
            CREATE TRIGGER kos_urusan_kunci BEFORE INSERT OR UPDATE ON kos_urusan FOR EACH ROW EXECUTE FUNCTION tsu_anak_kunci();
            CREATE TRIGGER urusan_dokumen_kunci BEFORE INSERT OR UPDATE ON urusan_dokumen FOR EACH ROW EXECUTE FUNCTION tsu_anak_kunci();
            CREATE TRIGGER urusan_lot_kunci BEFORE INSERT OR UPDATE ON urusan_lot FOR EACH ROW EXECUTE FUNCTION tsu_anak_kunci();
            CREATE TRIGGER hasil_ukur_kunci BEFORE INSERT OR UPDATE ON hasil_ukur FOR EACH ROW EXECUTE FUNCTION tsu_anak_kunci();

            CREATE TRIGGER lot_tanah_tsu_tiada_padam BEFORE UPDATE OF deleted_at ON lot_tanah FOR EACH ROW EXECUTE FUNCTION tsu_lot_tiada_padam();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS lot_tanah_tsu_tiada_padam ON lot_tanah');
        foreach (['tsu_penomboran', 'kemaskini_status_ptd', 'urusan_keputusan', 'urusan_dokumen', 'pemindahan_rekod', 'lot_sejarah_pecahan', 'hasil_ukur', 'kemajuan_kerja_ukur', 'lantikan_jurukur', 'sebutharga_tawaran', 'sebutharga_ukur', 'panel_jurukur', 'kos_urusan', 'urusan_peringkat', 'urusan_lot', 'urusan_tanah', 'templat_peringkat', 'templat_proses'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS tsu_lot_tiada_padam(); DROP FUNCTION IF EXISTS tsu_anak_kunci(); DROP FUNCTION IF EXISTS tsu_urusan_kunci(); DROP FUNCTION IF EXISTS tsu_tiada_padam(); DROP FUNCTION IF EXISTS tsu_tolak_ubah();');
    }
};
