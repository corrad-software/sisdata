<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-TKT · Tiket Sokongan Sistem (SRS-TKT-01..21). Entity names follow the
 * SRS (tkt_tiket, tkt_konteks, tkt_kronologi, tkt_lampiran,
 * tkt_sla_konfigurasi, tkt_eskalasi, tkt_cadangan_kb, tkt_pautan_aduan,
 * tkt_log_integrasi) plus the configuration tables the requirements need
 * (support teams, assignment rules, central-system mapping, support
 * activities, monthly reports). Timeline and escalation records are
 * append-only (DB trigger), like MOD-ADU's.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS tkt_tiket_nombor_seq START 1');

        Schema::create('tkt_pasukan', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('nama', 150);
            $table->string('aras', 4)->default('L1'); // L1 | L2
            $table->string('jenis', 20)->default('dalaman'); // dalaman (MAIS/BTM) | vendor
            $table->string('nama_vendor', 150)->nullable();
            $table->json('modul')->nullable(); // null = semua modul
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('tkt_ahli_pasukan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pasukan')->constrained('tkt_pasukan')->cascadeOnDelete();
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->string('peranan', 20)->default('juruteknik'); // juruteknik | penyelia | pengurus
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['id_pasukan', 'id_pengguna']);
        });

        Schema::create('tkt_sla_konfigurasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('keterukan'); // 1..4
            $table->unsignedInteger('ambang_respons'); // minit masa perkhidmatan
            $table->unsignedInteger('ambang_penyelesaian'); // minit masa perkhidmatan
            $table->string('waktu_perkhidmatan', 20)->default('waktu_pejabat'); // waktu_pejabat | 24x7
            $table->unsignedTinyInteger('ambang_amaran_peratus')->default(75);
            $table->json('hierarki_eskalasi');
            $table->date('tarikh_berkuat_kuasa');
            $table->boolean('aktif')->default(true);
            $table->string('catatan', 500)->nullable();
            $table->timestamps();
            $table->index(['keterukan', 'aktif', 'tarikh_berkuat_kuasa']);
        });

        Schema::create('tkt_peraturan_penugasan', function (Blueprint $table) {
            $table->id();
            $table->string('modul', 20)->nullable();
            $table->string('kategori', 30)->nullable();
            $table->unsignedTinyInteger('keterukan')->nullable();
            $table->foreignId('id_pasukan')->constrained('tkt_pasukan');
            $table->foreignId('id_juruteknik')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('keutamaan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('tkt_tiket', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombor_rujukan', 30)->unique();
            $table->foreignId('id_pelapor')->nullable()->constrained('users')->nullOnDelete();
            $table->string('emel_pelapor', 200)->nullable();
            $table->string('saluran', 30)->default('aplikasi'); // aplikasi | portal | emel | pemantauan | sistem_aduan_pusat
            $table->string('rujukan_sumber', 120)->nullable();
            $table->string('kategori', 30); // ralat | soalan | permintaan_perubahan | capaian
            $table->unsignedTinyInteger('keterukan'); // 1..4
            $table->string('kesan', 20)->nullable(); // seorang | sebahagian | semua
            $table->string('kesegeraan', 20)->nullable(); // rendah | sederhana | tinggi
            $table->unsignedTinyInteger('keterukan_dicadang')->nullable();
            $table->string('modul', 20)->nullable()->index();
            $table->string('tajuk', 200);
            $table->text('penerangan');
            $table->string('status', 30)->default('baharu')->index();
            $table->string('status_sebelum_jeda', 30)->nullable();
            $table->string('klasifikasi', 12)->default('dalaman'); // dalaman | sulit
            $table->boolean('isu_keselamatan')->default(false);
            $table->foreignId('id_pasukan')->nullable()->constrained('tkt_pasukan')->nullOnDelete();
            $table->string('aras_sokongan', 4)->default('L1');
            $table->foreignId('id_juruteknik')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_vendor', 150)->nullable();
            $table->string('rujukan_vendor', 120)->nullable();
            $table->timestamp('tarikh_serah_vendor')->nullable();
            $table->foreignId('id_sla_konfigurasi')->nullable()->constrained('tkt_sla_konfigurasi')->nullOnDelete();
            $table->string('waktu_perkhidmatan', 20)->default('waktu_pejabat');
            $table->timestamp('tarikh_buka');
            $table->timestamp('tarikh_sasaran_respons')->nullable();
            $table->timestamp('tarikh_sasaran_selesai')->nullable();
            $table->timestamp('tarikh_respons_pertama')->nullable();
            $table->timestamp('tarikh_selesai')->nullable();
            $table->timestamp('tarikh_disahkan')->nullable();
            $table->timestamp('tarikh_tutup')->nullable();
            $table->timestamp('sla_jeda_mula')->nullable();
            $table->unsignedInteger('sla_jeda_minit')->default(0);
            $table->boolean('respons_dilepasi')->default(false);
            $table->boolean('selesai_dilepasi')->default(false);
            $table->string('sebab_lepas_sla', 500)->nullable();
            $table->unsignedTinyInteger('peringkat_eskalasi')->default(0);
            $table->text('punca_akar')->nullable();
            $table->text('penyelesaian')->nullable();
            $table->text('tindakan_pembetulan')->nullable();
            $table->unsignedInteger('masa_henti_minit')->nullable();
            $table->string('versi_sistem', 40)->nullable();
            $table->uuid('id_tiket_induk')->nullable();
            $table->uuid('id_tiket_pendua_dicadang')->nullable();
            $table->decimal('skor_pendua', 4, 3)->nullable();
            $table->unsignedSmallInteger('bilangan_buka_semula')->default(0);
            $table->timestamp('peringatan_pengesahan_pada')->nullable();
            $table->boolean('ditutup_automatik')->default(false);
            $table->unsignedTinyInteger('kepuasan_skor')->nullable();
            $table->string('kepuasan_ulasan', 1000)->nullable();
            $table->timestamp('kepuasan_pada')->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->string('status_kelulusan', 20)->nullable(); // menunggu | lulus | tolak
            $table->unsignedBigInteger('id_artikel_kb')->nullable();
            $table->string('id_aduan_adu', 64)->nullable();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->unique(['saluran', 'rujukan_sumber']);
            $table->index(['id_pelapor', 'status']);
            $table->index(['id_juruteknik', 'status']);
            $table->index(['keterukan', 'status']);
            $table->index('tarikh_buka');
        });
        DB::statement('CREATE INDEX tkt_tiket_tajuk_trgm ON tkt_tiket USING gin (lower(tajuk) gin_trgm_ops)');

        Schema::create('tkt_konteks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->unique()->constrained('tkt_tiket')->cascadeOnDelete();
            $table->string('skrin', 200)->nullable();
            $table->string('modul', 20)->nullable();
            $table->string('kod_fungsi', 120)->nullable();
            $table->string('url', 1000)->nullable();
            $table->json('peranan_pengguna')->nullable();
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('pelayar', 400)->nullable();
            $table->string('versi_sistem', 40)->nullable();
            $table->string('versi_ui', 40)->nullable();
            $table->string('rujukan_rekod', 200)->nullable();
            $table->json('id_permintaan')->nullable(); // X-Request-Id terkini (SRS-ADM-12)
            $table->string('id_permintaan_cipta', 64)->nullable();
            $table->string('saiz_skrin', 30)->nullable();
            $table->string('zon_masa', 60)->nullable();
            $table->timestamp('cap_masa_tangkapan');
            $table->timestamps();
        });

        Schema::create('tkt_kronologi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->constrained('tkt_tiket')->cascadeOnDelete();
            $table->string('jenis_catatan', 30); // dalaman | kelihatan_pelapor | perubahan_status | tugasan | eskalasi | sistem
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->text('kandungan')->nullable();
            $table->string('status_dari', 30)->nullable();
            $table->string('status_ke', 30)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('cap_masa');
            $table->index(['id_tiket', 'id']);
        });

        Schema::create('tkt_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->constrained('tkt_tiket')->cascadeOnDelete();
            $table->string('id_dokumen', 64); // MOD-DMS
            $table->string('nama_fail', 255);
            $table->string('jenis_fail', 120)->nullable();
            $table->unsignedBigInteger('saiz')->default(0);
            $table->string('status_imbasan', 20)->default('bersih');
            $table->string('lokasi_storan', 120);
            $table->boolean('tangkapan_skrin')->default(false);
            $table->boolean('persetujuan_tangkapan_skrin')->default(false);
            $table->timestamp('persetujuan_pada')->nullable();
            $table->boolean('kelihatan_pelapor')->default(true);
            $table->foreignId('id_pemuat_naik')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tkt_eskalasi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->constrained('tkt_tiket')->cascadeOnDelete();
            $table->unsignedTinyInteger('peringkat');
            $table->string('sasaran', 20); // respons | selesai | manual
            $table->foreignId('id_penerima')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis_penerima', 60)->nullable(); // juruteknik | penyelia | pengurus | peranan:<kod>
            $table->string('sebab', 20); // hampir_sla | lepas_sla | manual
            $table->unsignedSmallInteger('kitaran')->default(0);
            $table->string('catatan', 1000)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cap_masa');
            $table->index(['id_tiket', 'sasaran', 'sebab', 'peringkat']);
        });

        Schema::create('tkt_cadangan_kb', function (Blueprint $table) {
            $table->id();
            $table->string('id_tiket_draf', 64)->index(); // sesi borang (client) — sebelum tiket wujud
            $table->foreignUuid('id_tiket')->nullable()->constrained('tkt_tiket')->nullOnDelete();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_artikel_kb', 500); // pautan kandungan penuh
            $table->string('tajuk', 300)->nullable();
            $table->decimal('skor_relevan', 6, 4)->nullable();
            $table->unsignedTinyInteger('kedudukan')->default(0);
            $table->boolean('diklik')->default(false);
            $table->boolean('tiket_diteruskan')->nullable(); // null = belum diputuskan
            $table->string('modul', 20)->nullable();
            $table->timestamp('cap_masa');
            $table->unique(['id_tiket_draf', 'id_artikel_kb']);
        });

        Schema::create('tkt_pemetaan_aduan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20); // kategori | keterukan | status | medan
            $table->string('nilai_tempatan', 60);
            $table->string('nilai_berpusat', 60);
            $table->string('arah', 10)->default('dua_hala'); // masuk | keluar | dua_hala
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['jenis', 'aktif']);
        });

        Schema::create('tkt_pautan_aduan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->unique()->constrained('tkt_tiket')->cascadeOnDelete();
            $table->string('nombor_aduan_berpusat', 80)->unique();
            $table->string('arah_asal', 12); // tempatan | berpusat
            $table->string('status_penyegerakan', 20)->default('menunggu'); // menunggu | berjaya | gagal
            $table->string('status_terakhir_disegerak', 30)->nullable();
            $table->timestamp('cap_masa_penyegerakan_akhir')->nullable();
            $table->timestamps();
        });

        Schema::create('tkt_log_integrasi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_tiket')->nullable()->constrained('tkt_tiket')->nullOnDelete();
            $table->string('arah', 10); // masuk | keluar
            $table->string('endpoint', 200);
            $table->string('kunci_idempotensi', 120)->nullable()->unique();
            $table->string('ringkasan_payload', 500)->nullable();
            $table->json('muatan')->nullable();
            $table->string('status', 20)->default('menunggu'); // menunggu | berjaya | gagal | diabaikan
            $table->unsignedSmallInteger('kod_status')->nullable();
            $table->unsignedSmallInteger('bilangan_cuba_semula')->default(0);
            $table->timestamp('cuba_seterusnya_pada')->nullable();
            $table->text('mesej_ralat')->nullable();
            $table->timestamp('cap_masa');
            $table->timestamps();
            $table->index(['arah', 'status']);
        });

        Schema::create('tkt_aktiviti_sokongan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 40); // tampalan_keselamatan | penyelenggaraan_pencegahan | masa_henti | pemindahan_teknologi | tindakan_pencegahan
            $table->date('tarikh');
            $table->string('tajuk', 200);
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('tempoh_minit')->nullable();
            $table->string('punca_akar', 1000)->nullable();
            $table->string('rujukan', 120)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['jenis', 'tarikh']);
        });

        Schema::create('tkt_laporan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->string('bulan', 7)->unique(); // YYYY-MM
            $table->json('ringkasan');
            $table->string('id_dokumen_pdf', 64)->nullable();
            $table->string('id_dokumen_xlsx', 64)->nullable();
            $table->string('ralat', 500)->nullable();
            $table->timestamp('dijana_pada');
            $table->foreignId('id_penjana')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // SRS-TKT-11/18: the timeline and escalation records are append-only.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tkt_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod tiket hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
        SQL);
        foreach (['tkt_kronologi', 'tkt_eskalasi'] as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION tkt_tolak_ubah();
                CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION tkt_tolak_ubah();
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['tkt_laporan_bulanan', 'tkt_aktiviti_sokongan', 'tkt_log_integrasi', 'tkt_pautan_aduan', 'tkt_pemetaan_aduan', 'tkt_cadangan_kb', 'tkt_eskalasi', 'tkt_lampiran', 'tkt_kronologi', 'tkt_konteks', 'tkt_tiket', 'tkt_peraturan_penugasan', 'tkt_sla_konfigurasi', 'tkt_ahli_pasukan', 'tkt_pasukan'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS tkt_tolak_ubah() CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS tkt_tiket_nombor_seq');
    }
};
