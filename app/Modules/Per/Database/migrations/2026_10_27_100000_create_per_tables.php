<?php

use App\Modules\Per\Support\ChannelCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PER · Rekod Pemerolehan (SRS Modul 4).
 *
 * One common case table (`pemerolehan`) + channel-specific fields in JSONB
 * (`butiran_saluran`, validated per channel by Support\ChannelFields from
 * Support\ChannelCatalog) instead of eleven sub-tables: the eleven forms are
 * generated from that field configuration (plan slice PER-c), adding a field
 * needs no migration, and reports that read specific fields use expression
 * indexes / the views below. Personal names from those fields live encrypted
 * in `butiran_peribadi`. Data with real cardinality gets real tables: 5A
 * payments, parties (heirs…), documents, stages and approvals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saluran_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->string('kod_saluran', 40)->unique();
            $table->string('nama_saluran', 150);
            $table->string('singkatan', 10);
            $table->string('rujukan_lampiran_h', 20);
            $table->string('ikon', 40);
            $table->string('warna', 7);
            $table->string('bahagian_bertanggungjawab', 30)->comment('hartanah | wakaf | baitulmal');
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('susunan')->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        // SRS-PER-17: configurable stages per channel (snapshotted into each case on creation).
        Schema::create('peringkat_saluran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_saluran')->constrained('saluran_pemerolehan')->cascadeOnDelete();
            $table->string('kod_peringkat', 40);
            $table->string('nama_peringkat', 150);
            $table->string('jenis', 20)->default('biasa')->comment('biasa | kelulusan | pendaftaran | simpanan');
            $table->unsignedSmallInteger('susunan');
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['id_saluran', 'kod_peringkat']);
        });

        Schema::create('senarai_semak_dokumen_saluran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_saluran')->constrained('saluran_pemerolehan')->cascadeOnDelete();
            $table->string('jenis_dokumen', 60);
            $table->string('nama_dokumen', 200);
            $table->string('kategori_dms', 40)->default('lain');
            $table->boolean('status_wajib')->default(true);
            $table->boolean('dijana_sistem')->default(false)->comment('dipenuhi oleh langkah tutup kes (daftar peti besi)');
            $table->string('peringkat_diperlukan', 40)->comment('kod peringkat yang tidak boleh dimasuki tanpanya, atau "tutup"');
            $table->unsignedSmallInteger('susunan_paparan')->default(0);
            $table->timestamps();
            $table->unique(['id_saluran', 'jenis_dokumen']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS pemerolehan_no_kes_seq');

        Schema::create('pemerolehan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_kes', 30)->unique();
            $table->foreignId('id_saluran')->constrained('saluran_pemerolehan');
            $table->string('tajuk', 255);
            $table->foreignUuid('lot_id')->nullable()->constrained('lot_tanah')->nullOnDelete();
            // SRS-PER-20: expected lot (not yet registered) — re-linked when the lot appears.
            $table->string('no_lot_dijangka', 50)->nullable();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('kod_daerah', 4)->nullable();
            $table->date('tarikh_mula_kes');
            $table->date('tarikh_pemerolehan')->nullable();
            $table->string('peringkat_semasa', 40)->nullable();
            $table->string('status_pemerolehan', 40)->default('dalam_proses')
                ->comment('permohonan_awam_menunggu_semakan | dalam_proses | menunggu_kelulusan | diluluskan | ditolak | selesai | dibatalkan');
            $table->string('status_kelengkapan_dokumen', 20)->default('tidak_lengkap');
            $table->string('kod_bahagian', 30);
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pendaftar')->nullable()->constrained('users')->nullOnDelete();
            // SRS-PER-24
            $table->decimal('nilai_pasaran', 16, 2)->nullable();
            $table->decimal('nilai_pemerolehan', 16, 2)->nullable();
            $table->string('sumber_dana', 100)->nullable();
            $table->string('rujukan_penilaian_jpph', 100)->nullable();
            $table->jsonb('butiran_saluran')->default('{}');
            $table->text('butiran_peribadi')->nullable()->comment('encrypted JSON — personal channel fields');
            $table->jsonb('butiran_hak_milik')->nullable()->comment('title + vault data recorded at closure');
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->string('id_hak_milik_dijana', 64)->nullable()->comment('MOD-HKM title id');
            $table->date('tarikh_selesai')->nullable();
            $table->unsignedInteger('tempoh_keseluruhan_hari')->nullable();
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->string('sumber_rekod', 20)->default('dalaman')->comment('dalaman | portal_wakaf | sintetik');
            $table->string('rujukan_luaran', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->string('klasifikasi', 10)->default('sulit');
            $table->unsignedInteger('versi_rekod')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_saluran', 'status_pemerolehan']);
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index('lot_id');
            $table->index(['kod_mukim', 'no_lot_dijangka']);
        });
        DB::statement("CREATE INDEX pemerolehan_tarikh_akhir_5a_idx ON pemerolehan ((butiran_saluran->>'tarikh_akhir_bayaran')) WHERE (butiran_saluran->>'tarikh_akhir_bayaran') IS NOT NULL");
        DB::statement("CREATE INDEX pemerolehan_keputusan_mmkn_idx ON pemerolehan ((butiran_saluran->>'jenis_keputusan_mmkn')) WHERE (butiran_saluran->>'jenis_keputusan_mmkn') IS NOT NULL");

        Schema::create('peringkat_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_pemerolehan')->constrained('pemerolehan')->cascadeOnDelete();
            $table->string('kod_peringkat', 40);
            $table->string('nama_peringkat', 150);
            $table->string('jenis', 20)->default('biasa');
            $table->unsignedSmallInteger('susunan');
            $table->foreignId('id_pegawai_bertanggungjawab')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_masuk')->nullable();
            $table->timestamp('tarikh_keluar')->nullable();
            $table->unsignedInteger('tempoh_hari')->nullable();
            $table->unsignedInteger('tempoh_sasaran_hari');
            $table->string('status_peringkat', 20)->default('belum')->comment('belum | semasa | selesai');
            $table->string('status_eskalasi', 20)->default('tiada')->comment('tiada | dieskalasi');
            $table->timestamp('tarikh_eskalasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['id_pemerolehan', 'kod_peringkat']);
            $table->index(['status_peringkat', 'status_eskalasi']);
        });

        Schema::create('dokumen_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_pemerolehan')->constrained('pemerolehan')->cascadeOnDelete();
            $table->foreignId('id_semak')->nullable()->constrained('senarai_semak_dokumen_saluran')->nullOnDelete();
            $table->string('jenis_dokumen', 60);
            $table->string('rujukan_dms', 64)->nullable()->comment('MOD-DMS document id');
            $table->unsignedInteger('no_versi')->default(1);
            $table->date('tarikh_dokumen')->nullable();
            $table->date('tarikh_terima')->nullable();
            $table->foreignId('id_pegawai_terima')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_dokumen', 20)->default('diterima')->comment('diterima | disahkan | ditolak');
            // SRS-PER-22: physical originals of pre-title documents.
            $table->string('kod_peti_besi', 30)->nullable();
            $table->string('no_fail', 50)->nullable();
            $table->string('lokasi_rak', 50)->nullable();
            $table->string('status_dokumen_asal', 30)->nullable()->comment('asal_dalam_simpanan | salinan_diakui | salinan | dikeluarkan | tiada');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['id_pemerolehan', 'id_semak']);
        });

        Schema::create('pergerakan_dokumen_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_dokumen_pemerolehan')->constrained('dokumen_pemerolehan')->cascadeOnDelete();
            $table->string('jenis', 10)->comment('keluar | pulang');
            $table->timestamp('tarikh');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tujuan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('pihak_berkaitan_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_pemerolehan')->constrained('pemerolehan')->cascadeOnDelete();
            $table->string('peranan_pihak', 30);
            $table->string('nama', 200);
            $table->string('hubungan', 100)->nullable();
            $table->string('jenis_dokumen_pengenalan', 30)->nullable()->comment('mykad | pasport | no_syarikat | lain');
            $table->text('no_dokumen_pengenalan')->nullable()->comment('encrypted');
            $table->string('no_pengenalan_4akhir', 4)->nullable()->comment('for masked display');
            $table->string('bahagian_pusaka', 30)->nullable();
            $table->string('status_pengesahan', 20)->default('belum_disahkan')->comment('belum_disahkan | disahkan | ditolak');
            $table->string('sumber_pengesahan', 30)->nullable()->comment('manual | e_faraid');
            $table->text('catatan')->nullable();
            $table->string('klasifikasi', 10)->default('sulit');
            $table->timestamps();
            $table->index('id_pemerolehan');
        });

        Schema::create('kelulusan_pemerolehan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_pemerolehan')->constrained('pemerolehan')->cascadeOnDelete();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->string('aras_kelulusan', 60)->comment('kod peringkat WFE, atau "keputusan_akhir"');
            $table->foreignId('id_pegawai_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jawatan_pelulus', 150)->nullable();
            $table->string('tindakan', 30)->comment('hantar | lulus | tolak | kembali | tangguh | ditarik_balik | dibatalkan');
            $table->timestamp('tarikh_masa');
            $table->text('justifikasi')->nullable();
            $table->timestamps();
            $table->index(['id_pemerolehan', 'tarikh_masa']);
        });

        // SRS-PER-05: 5A payments (GRP receipts) — confirmed by Bahagian Kewangan.
        Schema::create('bayaran_notis_5a', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_pemerolehan')->constrained('pemerolehan')->cascadeOnDelete();
            $table->decimal('jumlah_dibayar', 16, 2);
            $table->date('tarikh_bayaran');
            $table->string('mod_bayaran', 30)->comment('eft | cek | tunai | grp | lain');
            $table->string('no_resit', 60);
            $table->string('status_pengesahan', 20)->default('belum_disahkan')->comment('belum_disahkan | disahkan | ditolak');
            $table->foreignId('id_pegawai_rekod')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pegawai_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pengesahan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index('id_pemerolehan');
        });

        $this->seedChannels();
        $this->createViews();
    }

    private function seedChannels(): void
    {
        $now = now();
        $order = 0;
        foreach (ChannelCatalog::channels() as $code => $c) {
            $id = DB::table('saluran_pemerolehan')->insertGetId([
                'kod_saluran' => $code,
                'nama_saluran' => $c['nama'],
                'singkatan' => $c['singkatan'],
                'rujukan_lampiran_h' => $c['lampiran_h'],
                'ikon' => $c['ikon'],
                'warna' => $c['warna'],
                'bahagian_bertanggungjawab' => $c['bahagian'],
                'tempoh_sasaran_hari' => $c['tempoh_sasaran_hari'],
                'keterangan' => $c['keterangan'],
                'susunan' => ++$order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($c['peringkat'] as $i => [$kod, $nama, $jenis, $hari]) {
                DB::table('peringkat_saluran')->insert([
                    'id_saluran' => $id, 'kod_peringkat' => $kod, 'nama_peringkat' => $nama, 'jenis' => $jenis,
                    'susunan' => $i + 1, 'tempoh_sasaran_hari' => $hari, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($c['dokumen'] as $i => $d) {
                DB::table('senarai_semak_dokumen_saluran')->insert([
                    'id_saluran' => $id, 'jenis_dokumen' => $d[0], 'nama_dokumen' => $d[1], 'kategori_dms' => $d[2],
                    'peringkat_diperlukan' => $d[3], 'dijana_sistem' => $d[4] ?? false, 'status_wajib' => true,
                    'susunan_paparan' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** SRS §Modul 4 views (SRS-DAT-07). */
    private function createViews(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW vw_pemerolehan_mengikut_saluran AS
            SELECT s.kod_saluran, s.nama_saluran, p.kod_daerah, p.status_pemerolehan,
                   date_part('year', p.tarikh_mula_kes)::int AS tahun,
                   count(*) AS bilangan, coalesce(sum(p.nilai_pemerolehan), 0) AS jumlah_nilai
            FROM pemerolehan p JOIN saluran_pemerolehan s ON s.id = p.id_saluran
            WHERE p.deleted_at IS NULL
            GROUP BY s.kod_saluran, s.nama_saluran, p.kod_daerah, p.status_pemerolehan, date_part('year', p.tarikh_mula_kes)
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW vw_notis_5a_belum_dibayar AS
            SELECT p.id, p.no_kes, p.kod_daerah,
                   (p.butiran_saluran->>'tarikh_akhir_bayaran')::date AS tarikh_akhir_bayaran,
                   coalesce((p.butiran_saluran->>'jumlah_premium')::numeric, 0) + coalesce((p.butiran_saluran->>'cukai_tahun_pertama')::numeric, 0)
                     + coalesce((p.butiran_saluran->>'bayaran_ukur')::numeric, 0) + coalesce((p.butiran_saluran->>'bayaran_lain')::numeric, 0) AS jumlah_perlu_dibayar,
                   coalesce((SELECT sum(b.jumlah_dibayar) FROM bayaran_notis_5a b WHERE b.id_pemerolehan = p.id AND b.status_pengesahan = 'disahkan'), 0) AS jumlah_disahkan
            FROM pemerolehan p JOIN saluran_pemerolehan s ON s.id = p.id_saluran
            WHERE s.kod_saluran = 'pemberimilikan_5a' AND p.deleted_at IS NULL
              AND p.status_pemerolehan NOT IN ('selesai', 'dibatalkan')
              AND (p.butiran_saluran->>'tarikh_akhir_bayaran') IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW vw_kes_lewat_tempoh_sasaran AS
            SELECT p.id, p.no_kes, p.kod_daerah, s.kod_saluran, r.kod_peringkat, r.nama_peringkat, r.tarikh_masuk,
                   r.tempoh_sasaran_hari, (current_date - r.tarikh_masuk::date) AS hari_dalam_peringkat, r.status_eskalasi
            FROM peringkat_pemerolehan r
            JOIN pemerolehan p ON p.id = r.id_pemerolehan
            JOIN saluran_pemerolehan s ON s.id = p.id_saluran
            WHERE r.status_peringkat = 'semasa' AND p.deleted_at IS NULL
              AND (current_date - r.tarikh_masuk::date) > r.tempoh_sasaran_hari
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_kes_lewat_tempoh_sasaran');
        DB::statement('DROP VIEW IF EXISTS vw_notis_5a_belum_dibayar');
        DB::statement('DROP VIEW IF EXISTS vw_pemerolehan_mengikut_saluran');
        foreach (['bayaran_notis_5a', 'kelulusan_pemerolehan', 'pihak_berkaitan_pemerolehan', 'pergerakan_dokumen_pemerolehan',
            'dokumen_pemerolehan', 'peringkat_pemerolehan', 'pemerolehan', 'senarai_semak_dokumen_saluran', 'peringkat_saluran', 'saluran_pemerolehan'] as $t) {
            Schema::dropIfExists($t);
        }
        DB::statement('DROP SEQUENCE IF EXISTS pemerolehan_no_kes_seq');
    }
};
