<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-KES (SRS-KES-01..24). MOD-PRO created `kes_mahkamah` (no_kes, jenis,
 * mahkamah, status, kos_guaman, klasifikasi) and `kes_mahkamah_lot` (N:N) as
 * skeletons; this EXTENDS them with the SRS entity fields and creates the
 * module's satellite tables. Column mapping to the SRS entity list:
 * no_kes = no_kes_mahkamah, jenis = jenis_kes, mahkamah = nama_mahkamah,
 * status = status_semasa, klasifikasi = klasifikasi_keselamatan,
 * kos_guaman = cumulative legal cost (recomputed with bcmath).
 * kes_mahkamah_lot = kes_lot of the SRS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kes_mahkamah', function (Blueprint $table) {
            $table->string('no_rujukan', 20)->nullable()->unique()->comment('Rujukan dalaman KES/YYYY/NNNNN');
            $table->string('tajuk', 300)->nullable()->comment('Tajuk kes, cth. MAIS lwn ...');
            $table->string('peringkat_mahkamah', 30)->nullable();
            $table->date('tarikh_pemfailan')->nullable();
            $table->text('ringkasan_tuntutan')->nullable();
            $table->decimal('nilai_tuntutan', 16, 2)->nullable();
            $table->string('peranan_mais', 20)->nullable()->comment('plaintif | defendan | pihak_ketiga | pencelah | pemohon | responden');
            $table->timestamp('tarikh_status')->nullable();
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('status_arkib')->default(false);
            $table->timestamp('tarikh_arkib')->nullable();
            $table->text('sebab_arkib')->nullable();
            $table->string('kod_daerah', 4)->nullable()->comment('Daerah lot utama (skop capaian)');
            $table->string('kod_mukim', 6)->nullable();
            $table->date('tarikh_prosiding_berikutnya')->nullable();
            $table->date('tarikh_keputusan')->nullable();
            $table->string('sumber', 20)->default('manual')->comment('manual | ptn | adu | hkm | efiling');
            $table->string('rujukan_sumber', 150)->nullable()->comment('LegalCases/LegalCaseIntake sourceRef — idempotensi');
            $table->string('id_instans_aliran', 64)->nullable()->comment('Aliran kelulusan pemfailan');
            $table->boolean('sintetik')->default(false);
            $table->index(['status', 'jenis']);
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index('id_pegawai_bertanggungjawab');
            $table->index('tarikh_pemfailan');
        });
        DB::statement('CREATE UNIQUE INDEX kes_mahkamah_rujukan_sumber_unique ON kes_mahkamah (sumber, rujukan_sumber) WHERE rujukan_sumber IS NOT NULL');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS kes_no_rujukan_seq');

        Schema::table('kes_mahkamah_lot', function (Blueprint $table) {
            $table->string('peranan_lot', 30)->default('subjek')->comment('subjek | berkaitan | terjejas');
            $table->timestamp('tarikh_paut')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            // Snapshot from LotRegistry at link time: lets reports filter by district/category without reading lot_tanah.
            $table->string('no_lot', 60)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('kategori_guna_tanah', 50)->nullable();
            $table->index('lot_id');
        });

        // SRS-KES-01: configurable case types; SRS-KES-07: configurable status stages.
        Schema::create('kes_jenis', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('susunan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('kes_status', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('susunan')->default(0);
            $table->boolean('terminal')->default(false)->comment('Kes selesai — status lot dipulihkan');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Panel peguam MAIS (firm registry); a firm user account (id_pengguna) sees only assigned cases.
        Schema::create('kes_panel_peguam', function (Blueprint $table) {
            $table->id();
            $table->string('nama_firma', 200);
            $table->string('no_pendaftaran', 60)->nullable();
            $table->string('pegawai_penghubung', 150)->nullable();
            $table->text('no_telefon')->nullable()->comment('disulitkan');
            $table->text('emel')->nullable()->comment('disulitkan');
            $table->text('alamat')->nullable()->comment('disulitkan');
            $table->string('status', 20)->default('aktif')->comment('aktif | digantung | tamat');
            $table->date('tarikh_lantikan')->nullable();
            $table->date('tarikh_tamat')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
        });

        // SRS-KES-03: parties by role. Names / IC / contact encrypted at rest (Rahsia personal data).
        Schema::create('kes_pihak', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->text('nama_pihak')->comment('disulitkan');
            $table->string('cincangan_nama', 64)->nullable()->comment('HMAC nama ternormal — carian tepat');
            $table->string('jenis_pihak', 20)->default('individu')->comment('individu | syarikat | agensi | mais');
            $table->string('peranan', 20)->comment('plaintif | defendan | pihak_ketiga | pencelah | pemohon | responden');
            $table->text('no_pengenalan')->nullable()->comment('disulitkan');
            $table->text('maklumat_perhubungan')->nullable()->comment('disulitkan');
            $table->string('status_wakilan', 20)->default('diwakili')->comment('diwakili | tidak_diwakili | sendiri');
            $table->boolean('adalah_mais')->default(false);
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('kes_mahkamah_id');
        });

        // SRS-KES-04: counsel for each party; MAIS counsel may be a panel firm (approved through MOD-WFE).
        Schema::create('kes_peguam', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->foreignId('kes_pihak_id')->nullable()->constrained('kes_pihak')->nullOnDelete();
            $table->foreignId('kes_panel_peguam_id')->nullable()->constrained('kes_panel_peguam')->nullOnDelete();
            $table->string('nama_peguam', 150);
            $table->string('nama_firma', 200)->nullable();
            $table->string('pegawai_penghubung', 150)->nullable();
            $table->text('no_telefon')->nullable()->comment('disulitkan');
            $table->text('emel')->nullable()->comment('disulitkan');
            $table->boolean('indikator_panel_mais')->default(false);
            $table->string('status_lantikan', 30)->default('aktif')->comment('aktif | menunggu_kelulusan | ditolak | tamat');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('kes_mahkamah_id');
        });

        // SRS-KES-08: proceedings (chronology + calendar).
        Schema::create('kes_prosiding', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->timestamp('tarikh_prosiding');
            $table->string('jenis_prosiding', 30)->comment('sebutan | bicara | pengurusan_kes | pendengaran | keputusan | pengemukaan_dokumen | rayuan | lain');
            $table->string('status', 20)->default('dijadualkan')->comment('dijadualkan | selesai | ditangguh | dibatalkan');
            $table->text('hasil')->nullable();
            $table->text('arahan_mahkamah')->nullable();
            $table->date('tarikh_berikutnya')->nullable();
            $table->string('jenis_berikutnya', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->string('sumber', 20)->default('manual')->comment('manual | efiling');
            $table->foreignId('id_pegawai_rekod')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('eskalasi_dihantar_pada')->nullable()->comment('SRS-KES-11');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['kes_mahkamah_id', 'tarikh_prosiding']);
            $table->index(['status', 'tarikh_prosiding']);
        });

        // SRS-KES-10: other important dates — document filing deadlines, appeal / compliance deadlines.
        Schema::create('kes_tarikh_penting', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->string('jenis', 30)->comment('pengemukaan_dokumen | had_rayuan | had_pematuhan | lain');
            $table->date('tarikh');
            $table->string('keterangan', 500)->nullable();
            $table->string('status', 20)->default('belum')->comment('belum | selesai | dibatalkan');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['status', 'tarikh']);
        });

        Schema::create('kes_status_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->string('status_lama', 40)->nullable();
            $table->string('status_baharu', 40);
            $table->timestamp('tarikh_perubahan');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('kes_mahkamah_id');
        });

        Schema::create('kes_pegawai_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->foreignId('id_pegawai_lama')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pegawai_baharu')->constrained('users');
            $table->timestamp('tarikh_tukar');
            $table->text('justifikasi');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index('kes_mahkamah_id');
        });

        // SRS-KES-15: legal costs (DECIMAL; payment approval through MOD-WFE, verification with GRP MAIS).
        Schema::create('kos_guaman', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->string('kategori_kos', 30)->comment('fi_peguam | kos_mahkamah | kos_berkaitan');
            $table->string('keterangan', 500)->nullable();
            $table->decimal('amaun', 14, 2);
            $table->date('tarikh');
            $table->string('no_resit', 60)->nullable()->comment('No. resit / invois');
            $table->string('id_dokumen', 64)->nullable();
            $table->foreignId('kes_panel_peguam_id')->nullable()->constrained('kes_panel_peguam')->nullOnDelete();
            $table->string('status_bayaran', 30)->default('belum_dibayar')->comment('belum_dibayar | menunggu_kelulusan | diluluskan | dihantar_grp | dibayar | ditolak');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->string('rujukan_grp', 64)->nullable();
            $table->date('tarikh_bayar')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['kes_mahkamah_id', 'tarikh']);
            $table->index('status_bayaran');
        });

        // SRS-KES-16: judgment / order.
        Schema::create('kes_keputusan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->string('jenis_keputusan', 40)->comment('menang | kalah | selesai | penyelesaian_luar_mahkamah | ditolak');
            $table->date('tarikh_keputusan');
            $table->string('peringkat_mahkamah', 30)->nullable();
            $table->text('ringkasan_perintah')->nullable();
            $table->text('implikasi')->nullable();
            $table->string('id_dokumen_penghakiman', 64)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('kes_mahkamah_id');
        });

        // SRS-KES-17: follow-up actions, each a MOD-WFE task.
        Schema::create('kes_tindakan_susulan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->foreignId('kes_keputusan_id')->nullable()->constrained('kes_keputusan')->nullOnDelete();
            $table->string('jenis_tindakan', 40)->comment('pematuhan_perintah | penguatkuasaan_keputusan | pemfailan_rayuan | pengemaskinian_hak_milik | lain');
            $table->string('keterangan', 1000)->nullable();
            $table->string('id_tugasan_workflow', 64)->nullable();
            $table->date('tarikh_sasaran')->nullable();
            $table->string('status', 20)->default('dibuka')->comment('dibuka | dalam_tindakan | selesai | dibatalkan');
            $table->foreignUuid('id_lot_terjejas')->nullable()->constrained('lot_tanah');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_selesai')->nullable();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['kes_mahkamah_id', 'status']);
        });

        // SRS-KES-18: title / land-status updates made on the strength of a court order (append-only).
        Schema::create('kes_pengemaskinian_rekod', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->foreignId('kes_tindakan_susulan_id')->nullable()->constrained('kes_tindakan_susulan');
            $table->foreignUuid('lot_id')->nullable()->constrained('lot_tanah');
            $table->string('jenis_rekod', 30)->comment('hak_milik | status_tanah');
            $table->string('no_hak_milik', 80)->nullable();
            $table->string('jenis_perubahan', 30)->nullable();
            $table->text('ringkasan_perubahan');
            $table->string('no_kes_mahkamah', 80);
            $table->string('id_dokumen_perintah', 64);
            $table->string('id_cadangan_hkm', 64)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index('kes_mahkamah_id');
        });

        // Appeals and settlements (each approved through MOD-WFE).
        Schema::create('kes_rayuan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->foreignId('kes_keputusan_id')->nullable()->constrained('kes_keputusan')->nullOnDelete();
            $table->string('pihak_merayu', 20)->default('mais')->comment('mais | pihak_lawan');
            $table->string('mahkamah_rayuan', 150)->nullable();
            $table->string('peringkat_mahkamah', 30)->nullable();
            $table->string('no_kes_rayuan', 80)->nullable();
            $table->date('tarikh_had_failan')->nullable();
            $table->date('tarikh_failan')->nullable();
            $table->text('alasan')->nullable();
            $table->string('status', 30)->default('cadangan')->comment('cadangan | menunggu_kelulusan | ditolak | difailkan | diputuskan | ditarik_balik');
            $table->string('keputusan_rayuan', 40)->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('kes_mahkamah_id');
        });
        Schema::create('kes_penyelesaian', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->text('terma');
            $table->decimal('nilai_penyelesaian', 16, 2)->nullable();
            $table->date('tarikh_cadangan');
            $table->date('tarikh_persetujuan')->nullable();
            $table->string('status', 30)->default('cadangan')->comment('cadangan | menunggu_kelulusan | diluluskan | ditolak | dimeterai');
            $table->string('id_dokumen_perjanjian', 64)->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index('kes_mahkamah_id');
        });

        // SRS-KES-12: legal documents (stored in MOD-DMS; this is the case-side index with category + version).
        Schema::create('kes_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->string('id_dokumen_dms', 64);
            $table->string('kategori_dokumen', 40)->comment('surat_tuntutan | pliding | afidavit | perintah_mahkamah | penghakiman | perjanjian_penyelesaian | laporan_peguam | dokumen_aduan | lain');
            $table->string('nama_fail', 255)->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->timestamp('tarikh_muat_naik');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sumber', 20)->default('kes')->comment('kes | adu | ptn | dms');
            $table->timestamps();
            $table->unique(['kes_mahkamah_id', 'id_dokumen_dms']);
        });

        // Imported / system chronology entries (SRS-KES-19 complaint chronology, e-Filing sync…).
        Schema::create('kes_kronologi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah');
            $table->timestamp('tarikh');
            $table->string('jenis', 30)->comment('aduan | pemantauan | sistem | efiling | nota');
            $table->text('keterangan');
            $table->string('sumber', 30)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['kes_mahkamah_id', 'tarikh']);
        });

        // SRS-KES-13: full access log (view / download / print / export) with IP and timestamp. Append-only.
        Schema::create('kes_log_capaian', function (Blueprint $table) {
            $table->id();
            $table->uuid('kes_mahkamah_id')->nullable();
            $table->string('id_dokumen', 64)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis_tindakan', 30)->comment('lihat | lihat_senarai | lihat_pihak | muat_turun | cetak | eksport | ditolak');
            $table->timestamp('cap_masa');
            $table->string('alamat_ip', 45)->nullable();
            $table->string('ejen_pengguna', 255)->nullable();
            $table->string('keterangan', 500)->nullable();
            $table->index(['kes_mahkamah_id', 'cap_masa']);
            $table->index(['id_pengguna', 'cap_masa']);
        });

        // SRS-KES-06: lot "dalam tindakan undang-undang" while any linked case is active. Kept here (status_lot
        // stays MOD-PRO's lifecycle value, so the original status is never lost).
        Schema::create('kes_status_lot', function (Blueprint $table) {
            $table->foreignUuid('lot_id')->primary()->constrained('lot_tanah');
            $table->boolean('dalam_tindakan')->default(false);
            $table->unsignedSmallInteger('bil_kes_aktif')->default(0);
            $table->timestamp('tarikh_mula')->nullable();
            $table->timestamp('tarikh_tamat')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION kes_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'Rekod kes hanya-tambah / tidak boleh dipadam: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                        USING ERRCODE = 'insufficient_privilege';
                END;
                $$;
                -- Security control: case records are never deleted (closure = archive).
                CREATE TRIGGER kes_mahkamah_tiada_padam BEFORE DELETE ON kes_mahkamah
                    FOR EACH ROW EXECUTE FUNCTION kes_tolak_ubah();
            SQL);
            foreach (['kes_log_capaian', 'kes_status_sejarah', 'kes_pegawai_sejarah', 'kes_pengemaskinian_rekod'] as $table) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                        FOR EACH ROW EXECUTE FUNCTION kes_tolak_ubah();
                    CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                        FOR EACH STATEMENT EXECUTE FUNCTION kes_tolak_ubah();
                SQL);
            }
        }

        $now = now();
        $jenis = [
            ['sivil', 'Sivil'], ['jenayah', 'Jenayah'], ['faraid', 'Faraid'], ['wakaf', 'Wakaf'],
            ['sengketa_pemilikan', 'Sengketa pemilikan'], ['tuntutan_milikan', 'Tuntutan milikan'],
            ['pencerobohan', 'Pencerobohan'], ['kaveat', 'Kaveat'], ['rayuan', 'Rayuan'],
        ];
        DB::table('kes_jenis')->insert(array_map(fn ($r, $i) => ['kod' => $r[0], 'nama' => $r[1], 'susunan' => $i, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now], $jenis, array_keys($jenis)));
        $status = [
            ['didaftar', 'Didaftar', false], ['aktif', 'Aktif / difailkan', false], ['sebutan', 'Sebutan', false],
            ['dalam_bicara', 'Dalam bicara', false], ['tangguh', 'Ditangguhkan', false], ['keputusan', 'Keputusan dikeluarkan', false],
            ['rayuan', 'Dalam rayuan', false], ['selesai', 'Selesai', true],
        ];
        DB::table('kes_status')->insert(array_map(fn ($r, $i) => ['kod' => $r[0], 'nama' => $r[1], 'terminal' => $r[2], 'susunan' => $i, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now], $status, array_keys($status)));
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS kes_mahkamah_tiada_padam ON kes_mahkamah');
        }
        foreach (['kes_status_lot', 'kes_log_capaian', 'kes_kronologi', 'kes_dokumen', 'kes_penyelesaian', 'kes_rayuan', 'kes_pengemaskinian_rekod',
            'kes_tindakan_susulan', 'kes_keputusan', 'kos_guaman', 'kes_pegawai_sejarah', 'kes_status_sejarah', 'kes_tarikh_penting',
            'kes_prosiding', 'kes_peguam', 'kes_pihak', 'kes_panel_peguam', 'kes_status', 'kes_jenis'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS kes_tolak_ubah() CASCADE');
        }
        DB::statement('DROP SEQUENCE IF EXISTS kes_no_rujukan_seq');
        Schema::table('kes_mahkamah_lot', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pengguna');
            $table->dropIndex(['lot_id']);
            $table->dropColumn(['peranan_lot', 'tarikh_paut', 'no_lot', 'kod_daerah', 'kod_mukim', 'kategori_guna_tanah']);
        });
        DB::statement('DROP INDEX IF EXISTS kes_mahkamah_rujukan_sumber_unique');
        Schema::table('kes_mahkamah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pegawai_bertanggungjawab');
            $table->dropConstrainedForeignId('id_pencipta');
            $table->dropIndex(['status', 'jenis']);
            $table->dropIndex(['kod_daerah', 'kod_mukim']);
            $table->dropIndex(['tarikh_pemfailan']);
            $table->dropUnique(['no_rujukan']);
            $table->dropColumn(['no_rujukan', 'tajuk', 'peringkat_mahkamah', 'tarikh_pemfailan', 'ringkasan_tuntutan', 'nilai_tuntutan', 'peranan_mais',
                'tarikh_status', 'status_arkib', 'tarikh_arkib', 'sebab_arkib', 'kod_daerah', 'kod_mukim', 'tarikh_prosiding_berikutnya',
                'tarikh_keputusan', 'sumber', 'rujukan_sumber', 'id_instans_aliran', 'sintetik']);
        });
    }
};
