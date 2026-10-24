<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-ADU (SRS-ADU-01..27). MOD-PRO created `aduan` as a skeleton (lot_id,
 * kategori, tarikh_aduan, status, id_pegawai, tindakan_susulan, klasifikasi,
 * koordinat); this EXTENDS it with the SRS entity fields and creates the
 * satellite entities of the module. `id_pegawai` is the assigned officer
 * (id_pegawai_ditugaskan in the SRS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aduan', function (Blueprint $table) {
            $table->string('no_rujukan_aduan', 20)->nullable()->unique();
            $table->string('saluran_penerimaan', 30)->default('dalaman')
                ->comment('dalaman | portal | kaunter | emel | telefon | lapangan | sistem_aduan_pusat | ptn | tkt');
            $table->timestamp('tarikh_terima')->nullable();
            $table->string('subkategori', 30)->nullable();
            $table->string('kategori_dicadang', 30)->nullable()->comment('SRS-ADU-09 cadangan kata kunci');
            $table->text('keterangan')->nullable();
            $table->string('lokasi_teks', 500)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('tahap_keutamaan', 15)->default('sederhana')->comment('rendah | sederhana | tinggi | kritikal');
            $table->boolean('status_tanpa_nama')->default(false);
            $table->boolean('status_bukan_tanah_mais')->default(false);
            $table->string('kaedah_padanan_lot', 20)->nullable()->comment('koordinat | pilihan | nombor_lot | tiada');
            $table->text('jawapan_automatik')->nullable();
            $table->json('agensi_dicadang')->nullable();
            $table->boolean('jawapan_dihantar')->default(false);
            $table->uuid('id_aduan_induk')->nullable()->comment('aduan asal jika pendua');
            $table->uuid('id_aduan_pendua_dicadang')->nullable();
            $table->unsignedSmallInteger('skor_pendua')->nullable();
            $table->foreignId('id_penyelia')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aduan_sistem_luaran', 100)->nullable();
            $table->string('rujukan_sumber', 150)->nullable()->comment('ComplaintIntake sourceRef — idempotensi');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->unsignedSmallInteger('bilangan_buka_semula')->default(0);
            $table->timestamp('tarikh_tutup')->nullable();
            $table->string('bahagian_dirujuk', 30)->nullable()->comment('penguatkuasaan | perundangan | pengurusan_hartanah | agensi_luar');
            $table->boolean('sintetik')->default(false);

            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index(['status', 'kategori']);
            $table->index('tarikh_terima');
            $table->index('id_pegawai');
        });
        DB::statement('CREATE UNIQUE INDEX aduan_rujukan_sumber_unique ON aduan (saluran_penerimaan, rujukan_sumber) WHERE rujukan_sumber IS NOT NULL');
        DB::statement('CREATE INDEX aduan_koordinat_gix ON aduan USING GIST (koordinat)');
        DB::statement('CREATE INDEX aduan_keterangan_trgm ON aduan USING GIN (keterangan gin_trgm_ops)');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS aduan_no_rujukan_seq');

        // SRS-ADU-20/21: reporter identity, encrypted at rest (Laravel encrypted cast),
        // with keyed hashes only for status verification and rate limiting.
        Schema::create('pengadu', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->unique()->constrained('aduan')->cascadeOnDelete();
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->text('nama')->nullable();
            $table->text('no_telefon')->nullable();
            $table->text('emel')->nullable();
            $table->text('alamat')->nullable();
            $table->string('cincangan_emel', 64)->nullable()->index();
            $table->string('cincangan_telefon', 64)->nullable()->index();
            $table->string('saluran_makluman', 10)->default('emel')->comment('emel | sms | tiada');
            $table->boolean('status_penyulitan')->default(true);
            $table->boolean('status_tanpa_nama')->default(false);
            $table->string('klasifikasi_data', 10)->default('sulit');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
        });

        // SRS-ADU-18: immutable timeline (append-only trigger below).
        Schema::create('kronologi_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->timestamp('tarikh_masa');
            $table->string('jenis_tindakan', 40);
            $table->text('keterangan_tindakan')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_sebelum', 30)->nullable();
            $table->string('status_selepas', 30)->nullable();
            $table->string('id_dokumen_berkaitan', 64)->nullable();
            $table->boolean('komunikasi_pengadu')->default(false);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['aduan_id', 'tarikh_masa']);
        });

        Schema::create('subkategori_aduan', function (Blueprint $table) {
            $table->id();
            $table->string('kod_kategori', 20);
            $table->string('kod', 30);
            $table->string('nama', 150);
            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('susunan')->default(0);
            $table->timestamps();
            $table->unique(['kod_kategori', 'kod']);
        });

        // SRS-ADU-12/14: SLA per category (optionally subcategory), versioned by effective date.
        Schema::create('sla_kategori_aduan', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 20);
            $table->string('subkategori', 30)->nullable();
            $table->unsignedInteger('tempoh_respons');
            $table->unsignedInteger('tempoh_penyelesaian');
            $table->string('unit_masa', 15)->default('hari_bekerja')->comment('hari_bekerja | jam_bekerja');
            $table->json('laluan_eskalasi');
            $table->date('tarikh_berkuat_kuasa');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['kategori', 'subkategori', 'tarikh_berkuat_kuasa']);
        });

        Schema::create('jejak_sla_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->foreignId('id_sla')->nullable()->constrained('sla_kategori_aduan')->nullOnDelete();
            $table->unsignedSmallInteger('kitaran')->default(1)->comment('1 + bilangan buka semula');
            $table->timestamp('masa_mula');
            $table->timestamp('masa_sasaran_respons');
            $table->timestamp('masa_sasaran_selesai');
            $table->timestamp('masa_respons_sebenar')->nullable();
            $table->timestamp('masa_selesai_sebenar')->nullable();
            $table->string('status_sla', 20)->default('dalam_tempoh')
                ->comment('dalam_tempoh | hampir_tamat | dilepasi | dipenuhi | tidak_dipenuhi');
            $table->unsignedSmallInteger('bilangan_eskalasi')->default(0);
            $table->boolean('semasa')->default(true);
            $table->timestamps();
            $table->index(['aduan_id', 'semasa']);
            $table->index(['status_sla', 'semasa']);
        });

        Schema::create('eskalasi_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->unsignedSmallInteger('peringkat');
            $table->string('jenis', 15)->default('automatik')->comment('automatik | manual');
            $table->foreignId('id_pegawai_asal')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pegawai_eskalasi')->nullable()->constrained('users')->nullOnDelete();
            $table->json('penerima')->nullable();
            $table->text('sebab_eskalasi');
            $table->timestamp('tarikh_eskalasi');
            $table->timestamp('created_at')->nullable();
            $table->index('aduan_id');
        });

        Schema::create('lawatan_tapak_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->date('tarikh_jadual');
            $table->timestamp('tarikh_lawatan')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->text('penemuan')->nullable();
            $table->string('status', 15)->default('dijadualkan')->comment('dijadualkan | selesai | dibatalkan');
            $table->string('id_rekod_lawatan_ptn', 64)->nullable();
            $table->timestamps();
            $table->index(['tarikh_jadual', 'status']);
        });

        // SRS-ADU-02 / GEN-04: files live in DMS; this keeps the ADU-side metadata.
        Schema::create('dokumen_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->string('id_dokumen', 64)->comment('DMS dokumen.id');
            $table->string('jenis_dokumen', 30);
            $table->string('laluan_simpanan', 255)->nullable()->comment('dokumen/{id} — storan DMS');
            $table->unsignedBigInteger('saiz')->nullable();
            $table->string('jenis_fail', 100)->nullable();
            $table->string('sumber_muat_naik', 20)->comment('dalaman | portal | lapangan | sistem_aduan_pusat');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('resolusi_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->string('kategori_resolusi', 30);
            $table->text('keterangan_resolusi');
            $table->text('justifikasi');
            $table->foreignId('id_pegawai_penyelesai')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_penyelia_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 15)->default('menunggu')->comment('menunggu | lulus | tolak | ditarik_balik');
            $table->text('ulasan_pelulus')->nullable();
            $table->timestamp('tarikh_tutup')->nullable();
            $table->string('status_makluman_pengadu', 15)->default('belum')->comment('belum | dihantar | tidak_berkenaan');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->timestamps();
            $table->index(['aduan_id', 'status']);
        });

        Schema::create('peraturan_triaj', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_peraturan', 20)->comment('kata_kunci | semakan_berulang');
            $table->json('kata_kunci')->nullable();
            $table->string('kategori_cadangan', 20)->nullable();
            $table->string('keutamaan_cadangan', 15)->nullable();
            $table->unsignedInteger('tempoh_semakan_berulang')->nullable()->comment('hari');
            $table->unsignedInteger('jejari_meter')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        // SRS-ADU-11: routing by category / district → officer or role.
        Schema::create('peraturan_penugasan_aduan', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 20)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kod_peranan', 50)->nullable();
            $table->string('bahagian', 30)->nullable();
            $table->unsignedSmallInteger('keutamaan')->default(100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Process step 8: referral to enforcement / legal / other agency.
        Schema::create('rujukan_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->string('jenis_rujukan', 30)->comment('penguatkuasaan | perundangan | pengurusan_hartanah | agensi_luar');
            $table->string('nama_agensi', 200)->nullable();
            $table->text('keterangan');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 15)->default('dihantar')->comment('dihantar | diterima | selesai');
            $table->text('maklum_balas')->nullable();
            $table->timestamps();
        });

        // SRS-ADU-21: every access to reporter identity (append-only).
        Schema::create('audit_capaian_pengadu', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('no_rujukan_aduan', 20)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_masa');
            $table->json('medan_dicapai');
            $table->text('tujuan_capaian');
            $table->string('alamat_ip', 45)->nullable();
            $table->string('asas_capaian', 30)->comment('pegawai_ditugaskan | penyelia | unit_aduan');
            $table->index(['aduan_id', 'tarikh_masa']);
        });

        // SRS-ADU-05: notices to the (non-user) reporter, with delivery status.
        Schema::create('makluman_pengadu', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('peristiwa', 30);
            $table->string('saluran', 10);
            $table->string('status', 15)->default('dihantar')->comment('dihantar | gagal | dilangkau');
            $table->text('ralat')->nullable();
            $table->timestamps();
        });

        // SRS-ADU-07 / INT-10: status pushes to the central complaints system (outbox + retry).
        Schema::create('penyegerakan_aduan_pusat', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('aduan_id')->constrained('aduan');
            $table->string('id_aduan_sistem_luaran', 100);
            $table->string('arah', 10)->default('keluar');
            $table->string('status_aduan', 30);
            $table->json('muatan');
            $table->string('status', 15)->default('menunggu')->comment('menunggu | berjaya | gagal');
            $table->unsignedSmallInteger('cubaan')->default(0);
            $table->text('ralat_terakhir')->nullable();
            $table->timestamp('dihantar_pada')->nullable();
            $table->timestamps();
            $table->index(['status', 'cubaan']);
        });

        $this->appendOnly();
    }

    private function appendOnly(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION adu_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod aduan hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            -- SLA tracking: targets and recorded actual times can never be rewritten, rows never deleted.
            CREATE OR REPLACE FUNCTION adu_jejak_sla_kawal() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Jejak SLA tidak boleh dipadam' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF NEW.masa_mula IS DISTINCT FROM OLD.masa_mula
                   OR NEW.masa_sasaran_respons IS DISTINCT FROM OLD.masa_sasaran_respons
                   OR NEW.masa_sasaran_selesai IS DISTINCT FROM OLD.masa_sasaran_selesai
                   OR (OLD.masa_respons_sebenar IS NOT NULL AND NEW.masa_respons_sebenar IS DISTINCT FROM OLD.masa_respons_sebenar)
                   OR (OLD.masa_selesai_sebenar IS NOT NULL AND NEW.masa_selesai_sebenar IS DISTINCT FROM OLD.masa_selesai_sebenar) THEN
                    RAISE EXCEPTION 'Jejak SLA tidak boleh diubah' USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER jejak_sla_aduan_kawal BEFORE UPDATE OR DELETE ON jejak_sla_aduan
                FOR EACH ROW EXECUTE FUNCTION adu_jejak_sla_kawal();
        SQL);

        foreach (['kronologi_aduan', 'eskalasi_aduan', 'audit_capaian_pengadu'] as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION adu_tolak_ubah();
                CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION adu_tolak_ubah();
                REVOKE UPDATE, DELETE, TRUNCATE ON {$table} FROM PUBLIC;
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['penyegerakan_aduan_pusat', 'makluman_pengadu', 'audit_capaian_pengadu', 'rujukan_aduan', 'peraturan_penugasan_aduan', 'peraturan_triaj', 'resolusi_aduan', 'dokumen_aduan', 'lawatan_tapak_aduan', 'eskalasi_aduan', 'jejak_sla_aduan', 'sla_kategori_aduan', 'subkategori_aduan', 'kronologi_aduan', 'pengadu'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS adu_tolak_ubah() CASCADE; DROP FUNCTION IF EXISTS adu_jejak_sla_kawal() CASCADE; DROP SEQUENCE IF EXISTS aduan_no_rujukan_seq;');
            DB::statement('DROP INDEX IF EXISTS aduan_rujukan_sumber_unique');
            DB::statement('DROP INDEX IF EXISTS aduan_koordinat_gix');
            DB::statement('DROP INDEX IF EXISTS aduan_keterangan_trgm');
        }
        Schema::table('aduan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_penyelia');
            $table->dropConstrainedForeignId('id_pencipta');
            $table->dropColumn([
                'no_rujukan_aduan', 'saluran_penerimaan', 'tarikh_terima', 'subkategori', 'kategori_dicadang', 'keterangan', 'lokasi_teks',
                'kod_daerah', 'kod_mukim', 'tahap_keutamaan', 'status_tanpa_nama', 'status_bukan_tanah_mais', 'kaedah_padanan_lot',
                'jawapan_automatik', 'agensi_dicadang', 'jawapan_dihantar', 'id_aduan_induk', 'id_aduan_pendua_dicadang', 'skor_pendua',
                'id_aduan_sistem_luaran', 'rujukan_sumber', 'id_instans_aliran', 'bilangan_buka_semula', 'tarikh_tutup', 'bahagian_dirujuk', 'sintetik',
            ]);
        });
    }
};
