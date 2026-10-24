<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PTN · Pemantauan Tanah — the ten SRS entities (jadual_pemantauan,
 * rekod_lawatan, item_senarai_semak, templat_senarai_semak, gambar_lawatan,
 * isu_pemantauan, tindakan_pembetulan, risk_rating_lot,
 * konfigurasi_ambang_warna, laporan_lawatan) plus configuration and support
 * tables. Every operational row carries kod_daerah / kod_mukim / klasifikasi
 * copied from its lot so RBC scoping (ScopedByAccess) works on the row itself.
 *
 * Immutability (SRS-PTN-07, SRS-PTN-10, acceptance #4) is enforced in the
 * database too, so no interface can bypass it: a validated visit, its
 * checklist and photos, a locked report and the geotag of every photo
 * cannot be changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-PTN-02: risk-based frequency (visits per year) per lot category.
        Schema::create('kekerapan_pemantauan', function (Blueprint $table) {
            $table->id();
            $table->string('kategori_lot', 30)->default('*')->comment('kategori_guna_tanah lot, atau * untuk semua');
            $table->string('tahap_risiko', 20)->comment('rendah | sederhana | tinggi | kritikal');
            $table->unsignedSmallInteger('kekerapan_setahun');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['kategori_lot', 'tahap_risiko']);
        });

        // SRS-PTN-04: configurable checklist templates, versioned.
        Schema::create('templat_senarai_semak', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 50);
            $table->string('nama', 200);
            $table->string('kategori_lot', 30)->nullable()->comment('null = semua kategori lot');
            $table->string('jenis_pemeriksaan', 30)->default('rutin')->comment('rutin | susulan | aduan | khas');
            $table->jsonb('senarai_item');
            $table->unsignedInteger('versi')->default(1);
            $table->boolean('status_aktif')->default(true);
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kod', 'versi']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS ptn_no_lawatan_seq');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS ptn_no_isu_seq');

        // SRS-PTN-01: monitoring plan / schedule.
        Schema::create('jadual_pemantauan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedSmallInteger('kekerapan')->comment('lawatan setahun');
            $table->unsignedSmallInteger('bil_dalam_tahun')->default(1);
            $table->date('tarikh_sasaran');
            $table->foreignId('id_pegawai_bertugas')->nullable()->constrained('users')->nullOnDelete();
            $table->text('objektif');
            $table->string('jenis_pemeriksaan', 30)->default('rutin');
            $table->foreignId('id_templat')->nullable()->constrained('templat_senarai_semak')->nullOnDelete();
            $table->string('keutamaan_risiko', 20)->default('rendah');
            $table->decimal('skor_keutamaan', 6, 2)->default(0);
            $table->jsonb('faktor_keutamaan')->default('{}');
            $table->string('status_pelaksanaan', 30)->default('dirancang')
                ->comment('dirancang | ditugaskan | dalam_pelaksanaan | selesai | dibatalkan');
            $table->boolean('dijana_automatik')->default(false);
            $table->unsignedInteger('versi')->default(1)->comment('dinaikkan setiap perubahan — pengesanan konflik luar talian');
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['tahun', 'kod_daerah']);
            $table->index(['lot_id', 'tarikh_sasaran']);
            $table->index(['id_pegawai_bertugas', 'status_pelaksanaan']);
        });

        Schema::create('rekod_lawatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan', 30)->unique();
            $table->foreignUuid('id_jadual')->nullable()->constrained('jadual_pemantauan')->nullOnDelete();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->foreignId('id_templat')->nullable()->constrained('templat_senarai_semak')->nullOnDelete();
            $table->unsignedInteger('versi_templat')->nullable();
            $table->date('tarikh_lawatan');
            $table->timestamp('masa_mula')->nullable();
            $table->timestamp('masa_tamat')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('ketepatan_gps_m', 8, 2)->nullable();
            $table->decimal('jarak_dari_lot_m', 10, 2)->nullable();
            $table->boolean('dalam_lingkungan')->nullable();
            $table->string('keadaan_tapak', 20)->nullable()->comment('baik | sederhana | buruk | kritikal | terbiar');
            $table->text('ringkasan_penemuan')->nullable();
            $table->text('isu_pencerobohan')->nullable();
            $table->text('isu_penyelenggaraan')->nullable();
            $table->text('cadangan_tindakan')->nullable();
            $table->string('status_lawatan', 20)->default('draf')
                ->comment('draf | dihantar | konflik | dikembalikan | disahkan | ditolak');
            $table->string('status_pengesahan', 20)->default('belum')
                ->comment('belum | menunggu | disahkan | ditolak');
            $table->foreignId('id_pegawai_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_disahkan')->nullable();
            $table->string('sebab_konflik')->nullable();
            $table->jsonb('data_tambahan')->default('{}')->comment('penyelenggaraan[] dan cadangan[] — dijana menjadi rekod semasa dihantar');
            $table->uuid('id_luar_talian')->nullable()->unique()->comment('UUID peranti — penyegerakan idempoten');
            $table->string('sumber', 10)->default('web')->comment('web | pwa');
            $table->timestamp('disegerak_pada')->nullable();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['lot_id', 'tarikh_lawatan']);
            $table->index(['kod_daerah', 'status_lawatan']);
        });
        DB::statement('ALTER TABLE rekod_lawatan ADD COLUMN koordinat_lawatan geometry(Point, 4326)');

        Schema::create('item_senarai_semak', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_lawatan')->constrained('rekod_lawatan')->cascadeOnDelete();
            $table->foreignId('id_templat_senarai_semak')->nullable()->constrained('templat_senarai_semak')->nullOnDelete();
            $table->string('kod_item', 50);
            $table->string('kategori_item', 30);
            $table->string('label', 255);
            $table->string('status_item', 20)->comment('baik | isu | kritikal');
            $table->text('catatan')->nullable();
            $table->string('kategori_isu', 30)->nullable()->comment('kategori penemuan jika isu/kritikal');
            $table->string('tahap_keterukan', 20)->nullable();
            $table->timestamps();
            $table->unique(['id_lawatan', 'kod_item']);
        });

        Schema::create('gambar_lawatan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_lawatan')->constrained('rekod_lawatan')->cascadeOnDelete();
            $table->uuid('laluan_simpanan')->nullable()->comment('id dokumen MOD-DMS (storan objek bersulit)');
            $table->timestamp('tarikh_masa')->nullable();
            $table->decimal('arah_kamera', 6, 2)->nullable()->comment('darjah dari utara');
            $table->decimal('ketepatan_m', 8, 2)->nullable();
            $table->string('sumber_gambar', 10)->default('kamera')->comment('kamera (dalam aplikasi) | galeri');
            $table->string('status_pengesahan_lingkungan', 20)->default('tiada_koordinat')
                ->comment('dalam | luar | tiada_koordinat');
            $table->decimal('jarak_dari_lot_m', 10, 2)->nullable();
            $table->boolean('perlu_semakan')->default(false);
            $table->string('keputusan_semakan', 20)->nullable()->comment('diterima | ditolak');
            $table->text('catatan_semakan')->nullable();
            $table->foreignId('id_penyemak')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_semakan')->nullable();
            $table->foreignUuid('tag_rekod_tanah')->nullable()->constrained('lot_tanah')->nullOnDelete();
            $table->string('tag_lapisan_gis', 60)->nullable();
            $table->string('kod_item', 50)->nullable();
            $table->unsignedBigInteger('id_tindakan')->nullable()->comment('gambar bukti tindakan pembetulan (selepas)');
            $table->string('jenis', 10)->default('umum')->comment('umum | sebelum | selepas');
            $table->string('kapsyen', 255)->nullable();
            $table->string('nama_fail', 255)->nullable();
            $table->text('imej_kecil')->nullable()->comment('lakaran kecil JPEG base64 (≤480 px) untuk laporan dan perbandingan');
            $table->uuid('id_luar_talian')->nullable()->unique();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE gambar_lawatan ADD COLUMN koordinat_gambar geometry(Point, 4326)');

        Schema::create('isu_pemantauan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan', 30)->unique();
            $table->foreignUuid('id_lawatan')->nullable()->constrained('rekod_lawatan')->nullOnDelete();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->string('kod_item', 50)->nullable();
            $table->string('kategori_isu', 30);
            $table->string('tahap_keterukan', 20)->comment('rendah | sederhana | tinggi | kritikal');
            $table->text('keterangan');
            $table->date('tarikh_dibuka');
            $table->string('status_isu', 20)->default('terbuka')->comment('terbuka | dalam_tindakan | selesai | ditutup');
            $table->date('tarikh_ditutup')->nullable();
            $table->uuid('id_aduan_berkaitan')->nullable();
            $table->string('id_kes_berkaitan', 64)->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['lot_id', 'status_isu']);
            $table->index(['kod_daerah', 'kategori_isu']);
        });

        Schema::create('tindakan_pembetulan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_isu')->constrained('isu_pemantauan')->cascadeOnDelete();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->text('langkah_tindakan');
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tarikh_sasaran');
            $table->date('tarikh_sasaran_asal');
            $table->date('tarikh_siap')->nullable();
            $table->string('status_kemajuan', 30)->default('belum_mula')
                ->comment('belum_mula | dalam_tindakan | menunggu_pengesahan | selesai');
            $table->unsignedTinyInteger('peratus_kemajuan')->default(0);
            $table->string('kod_warna_status', 10)->default('hijau');
            $table->text('justifikasi_lanjutan')->nullable();
            $table->text('catatan_kemajuan')->nullable();
            $table->foreignId('id_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_disahkan')->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['id_pegawai_bertanggungjawab', 'status_kemajuan']);
            $table->index('tarikh_sasaran');
        });

        // SRS-PTN-11/12: risk score history per lot (semasa = latest).
        Schema::create('risk_rating_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lot_id')->constrained('lot_tanah')->cascadeOnDelete();
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->timestamp('tarikh_kiraan');
            $table->decimal('skor_pencerobohan', 6, 2)->default(0);
            $table->decimal('skor_tunggakan_cukai', 6, 2)->default(0);
            $table->decimal('skor_pertikaian', 6, 2)->default(0);
            $table->decimal('skor_dokumen', 6, 2)->default(0);
            $table->decimal('skor_tamat_kontrak', 6, 2)->default(0);
            $table->decimal('skor_potensi_hasil', 6, 2)->default(0);
            $table->decimal('skor_keseluruhan', 6, 2)->default(0);
            $table->string('kod_warna', 10);
            $table->boolean('paksa_merah')->default(false)->comment('penemuan kritikal terbuka (SRS-PTN-17)');
            $table->jsonb('butiran')->default('{}');
            $table->boolean('semasa')->default(true);
            $table->timestamps();
            $table->index(['lot_id', 'tarikh_kiraan']);
            $table->index(['kod_daerah', 'semasa']);
        });
        DB::statement('CREATE UNIQUE INDEX risk_rating_lot_satu_semasa ON risk_rating_lot (lot_id) WHERE semasa');

        // SRS-PTN-15: colour thresholds and weights, effective-dated (history kept).
        Schema::create('konfigurasi_ambang_warna', function (Blueprint $table) {
            $table->id();
            $table->string('kategori_risiko', 30);
            $table->decimal('ambang_hijau', 6, 2)->default(0);
            $table->decimal('ambang_kuning', 6, 2)->nullable();
            $table->decimal('ambang_merah', 6, 2)->nullable();
            $table->integer('tempoh_kelewatan_kuning')->nullable()->comment('hari sebelum tarikh akhir → kuning');
            $table->integer('tempoh_kelewatan_merah')->nullable()->comment('hari selepas tarikh akhir → merah');
            $table->decimal('pemberat', 6, 2)->nullable()->comment('pemberat kategori dalam skor keseluruhan (SRS-PTN-11)');
            $table->timestamp('tarikh_berkuat_kuasa');
            $table->foreignId('id_pengubah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kategori_risiko', 'tarikh_berkuat_kuasa']);
        });

        Schema::create('laporan_lawatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_lawatan')->constrained('rekod_lawatan');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->string('jenis', 10)->default('asal')->comment('asal | tambahan');
            $table->uuid('id_laporan_asal')->nullable();
            $table->string('format', 10)->default('pdf');
            $table->jsonb('kandungan');
            $table->char('cincangan_kandungan', 64);
            $table->text('catatan_pembetulan')->nullable();
            $table->uuid('id_dokumen_dms')->nullable();
            $table->string('status', 20)->default('menunggu')->comment('menunggu | disahkan | ditolak');
            $table->string('id_aliran', 64)->nullable();
            $table->timestamp('tarikh_jana');
            $table->timestamp('tarikh_sah')->nullable();
            $table->foreignId('id_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('status_kunci')->default(false);
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['id_lawatan', 'jenis']);
        });
        Schema::table('laporan_lawatan', fn (Blueprint $table) => $table->foreign('id_laporan_asal')->references('id')->on('laporan_lawatan'));

        // SRS-PTN-08: proposed master-data updates, applied only after WFE approval.
        Schema::create('cadangan_kemaskini_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_lawatan')->constrained('rekod_lawatan')->cascadeOnDelete();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->string('medan', 40);
            $table->text('nilai_semasa')->nullable();
            $table->text('nilai_dicadang');
            $table->text('justifikasi');
            $table->string('status', 20)->default('menunggu')->comment('menunggu | lulus | tolak | gagal');
            $table->string('id_aliran', 64)->nullable();
            $table->foreignId('id_pencadang')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->text('ulasan_keputusan')->nullable();
            $table->timestamps();
        });

        // SRS-PTN-21: maintenance needs found during monitoring (read by MOD-AST via Contracts\MaintenanceNeeds).
        Schema::create('keperluan_penyelenggaraan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_lawatan')->nullable()->constrained('rekod_lawatan')->nullOnDelete();
            $table->foreignUuid('id_isu')->nullable()->constrained('isu_pemantauan')->nullOnDelete();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->string('jenis', 40);
            $table->text('keterangan');
            $table->string('keutamaan', 20)->default('sederhana');
            $table->date('tarikh_jadual')->nullable();
            $table->string('status', 20)->default('dikenal_pasti')->comment('dikenal_pasti | dijadual | selesai | dibatalkan');
            $table->string('id_aset_fizikal', 64)->nullable()->comment('pautan MOD-AST');
            $table->decimal('anggaran_kos', 14, 2)->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['lot_id', 'status']);
        });

        // SRS-PTN-05: offline sync log (audit + conflict handling).
        Schema::create('log_penyegerakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('id_luar_talian');
            $table->foreignUuid('id_lawatan')->nullable()->constrained('rekod_lawatan')->nullOnDelete();
            $table->string('jenis', 20)->comment('lawatan | gambar | hantar');
            $table->string('status', 20)->comment('diterima | pendua | konflik | ditolak');
            $table->jsonb('butiran')->default('{}');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['id_pengguna', 'created_at']);
        });

        $this->triggers();
    }

    private function triggers(): void
    {
        // A validated visit (and its checklist and photos) can never change (SRS-PTN-10, acceptance #4).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ptn_lawatan_kunci() RETURNS trigger AS $$
            BEGIN
                IF OLD.status_pengesahan = 'disahkan' THEN
                    RAISE EXCEPTION 'PTN_DIKUNCI: rekod lawatan yang telah disahkan tidak boleh diubah' USING ERRCODE = 'P0001';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;
            CREATE TRIGGER rekod_lawatan_kunci BEFORE UPDATE OR DELETE ON rekod_lawatan
                FOR EACH ROW EXECUTE FUNCTION ptn_lawatan_kunci();

            CREATE OR REPLACE FUNCTION ptn_anak_lawatan_kunci() RETURNS trigger AS $$
            DECLARE st text;
            BEGIN
                SELECT status_pengesahan INTO st FROM rekod_lawatan
                    WHERE id = (CASE WHEN TG_OP = 'INSERT' THEN NEW.id_lawatan ELSE OLD.id_lawatan END);
                IF st = 'disahkan' THEN
                    RAISE EXCEPTION 'PTN_DIKUNCI: lawatan telah disahkan — senarai semak dan gambar dikunci' USING ERRCODE = 'P0001';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;
            CREATE TRIGGER item_senarai_semak_kunci BEFORE INSERT OR UPDATE OR DELETE ON item_senarai_semak
                FOR EACH ROW EXECUTE FUNCTION ptn_anak_lawatan_kunci();
            CREATE TRIGGER gambar_lawatan_kunci BEFORE INSERT OR UPDATE OR DELETE ON gambar_lawatan
                FOR EACH ROW EXECUTE FUNCTION ptn_anak_lawatan_kunci();

            -- Geotag and timestamp of a photo can never be changed (security control).
            CREATE OR REPLACE FUNCTION ptn_geotag_tetap() RETURNS trigger AS $$
            BEGIN
                -- The location is written once, right after insert (NULL → point); never again.
                IF (OLD.koordinat_gambar IS NOT NULL AND NEW.koordinat_gambar IS DISTINCT FROM OLD.koordinat_gambar)
                   OR NEW.tarikh_masa IS DISTINCT FROM OLD.tarikh_masa
                   OR NEW.arah_kamera IS DISTINCT FROM OLD.arah_kamera
                   OR NEW.ketepatan_m IS DISTINCT FROM OLD.ketepatan_m
                   OR NEW.sumber_gambar IS DISTINCT FROM OLD.sumber_gambar
                   OR NEW.laluan_simpanan IS DISTINCT FROM OLD.laluan_simpanan THEN
                    RAISE EXCEPTION 'PTN_GEOTAG: geotag, cap masa dan sumber gambar tidak boleh diubah' USING ERRCODE = 'P0001';
                END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;
            CREATE TRIGGER gambar_lawatan_geotag BEFORE UPDATE ON gambar_lawatan
                FOR EACH ROW EXECUTE FUNCTION ptn_geotag_tetap();

            -- A locked report is final; corrections only via a linked addendum.
            CREATE OR REPLACE FUNCTION ptn_laporan_kunci() RETURNS trigger AS $$
            BEGIN
                IF OLD.status_kunci THEN
                    RAISE EXCEPTION 'PTN_DIKUNCI: laporan lawatan yang telah disahkan dikunci daripada pindaan' USING ERRCODE = 'P0001';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;
            CREATE TRIGGER laporan_lawatan_kunci BEFORE UPDATE OR DELETE ON laporan_lawatan
                FOR EACH ROW EXECUTE FUNCTION ptn_laporan_kunci();
        SQL);
    }

    public function down(): void
    {
        foreach (['log_penyegerakan', 'keperluan_penyelenggaraan', 'cadangan_kemaskini_lot', 'laporan_lawatan', 'konfigurasi_ambang_warna', 'risk_rating_lot', 'tindakan_pembetulan', 'isu_pemantauan', 'gambar_lawatan', 'item_senarai_semak', 'rekod_lawatan', 'jadual_pemantauan', 'templat_senarai_semak', 'kekerapan_pemantauan'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['ptn_lawatan_kunci', 'ptn_anak_lawatan_kunci', 'ptn_geotag_tetap', 'ptn_laporan_kunci'] as $fn) {
            DB::statement("DROP FUNCTION IF EXISTS {$fn}() CASCADE");
        }
        DB::statement('DROP SEQUENCE IF EXISTS ptn_no_lawatan_seq');
        DB::statement('DROP SEQUENCE IF EXISTS ptn_no_isu_seq');
    }
};
