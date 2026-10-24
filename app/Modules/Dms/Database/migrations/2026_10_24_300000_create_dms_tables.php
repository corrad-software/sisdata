<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-DMS (SRS-DMS-01..26). Entity names follow SRS Modul 11 "Entiti data":
 *   DOKUMEN_KATEGORI → dokumen_kategori      (retention, size, allowed types per category)
 *   DOKUMEN_METADATA → dokumen_medan_metadata (admin-defined extra fields, values in dokumen.metadata)
 *   DOKUMEN_TAG      → dokumen.kata_kunci (jsonb array, indexed in the tsvector)
 *   DOKUMEN / DOKUMEN_VERSI / DOKUMEN_KEBENARAN / DOKUMEN_IMBASAN / DOKUMEN_ARKIB as named,
 *   plus dokumen_kuarantin (SRS-DMS-07) and dokumen_pelupusan (SRS-DMS-23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 50)->unique();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            // Subset of the global whitelist (dms.senarai_putih); null = all whitelisted types.
            $table->jsonb('jenis_fail_dibenarkan')->nullable();
            // Overrides the per-type-group default (25 MB office/images, 250 MB spatial).
            $table->unsignedInteger('had_saiz_mb')->nullable();
            $table->unsignedInteger('tempoh_retensi_bulan')->nullable();
            $table->unsignedInteger('tempoh_arkib_bulan')->nullable();
            $table->string('tindakan_selepas_arkib', 20)->default('semak'); // lupus | semak | kekal
            $table->unsignedInteger('tempoh_amaran_luput_hari')->nullable();
            $table->string('klasifikasi_lalai', 20)->default('dalaman');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('dokumen_medan_metadata', function (Blueprint $table) {
            $table->id();
            // null = applies to every category.
            $table->foreignId('kategori_id')->nullable()->constrained('dokumen_kategori')->cascadeOnDelete();
            $table->string('kod', 50);
            $table->string('label');
            $table->string('jenis', 20); // teks | nombor | tarikh | senarai | ya_tidak
            $table->jsonb('pilihan')->nullable();
            $table->boolean('wajib')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('susunan')->default(0);
            $table->timestamps();
            $table->unique(['kategori_id', 'kod']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS dokumen_no_rujukan_seq');

        Schema::create('dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan', 30)->unique();
            $table->foreignId('kategori_id')->constrained('dokumen_kategori');
            $table->string('modul_sumber', 20)->default('dms');
            $table->string('jenis_entiti_induk', 64)->nullable();
            $table->string('id_rekod_induk', 64)->nullable();
            $table->string('pautan_induk')->nullable();
            $table->string('tajuk');
            $table->string('nama_fail');
            $table->string('jenis_mime', 150);
            $table->string('sambungan', 16);
            $table->unsignedBigInteger('saiz_bait');
            $table->char('cincangan_fail', 64);
            $table->string('klasifikasi_keselamatan', 20)->default('dalaman');
            $table->string('status', 20)->default('aktif'); // aktif | tamat | diarkib | dilupus
            $table->date('tarikh_dokumen')->nullable();
            $table->date('tarikh_luput')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('kod_daerah', 20)->nullable();
            $table->string('kod_mukim', 20)->nullable();
            $table->foreignId('pemilik_proses_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('versi_semasa')->default(1);
            $table->jsonb('kata_kunci')->default('[]');
            $table->jsonb('metadata')->default('{}');
            $table->text('teks_kandungan')->nullable();
            $table->string('status_indeks', 20)->default('menunggu'); // menunggu | selesai | gagal | tiada
            $table->string('kaedah_indeks', 20)->nullable(); // teks | ocr
            $table->timestamp('amaran_luput_pada')->nullable();
            $table->timestamp('tarikh_arkib')->nullable();
            $table->timestamp('tarikh_lupus')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis_entiti_induk', 'id_rekod_induk']);
            $table->index(['status', 'klasifikasi_keselamatan']);
            $table->index('kod_daerah');
        });

        // SRS-DMS-12: full-text over metadata + content. 'simple' config — Postgres
        // ships no Malay dictionary, so no stemming; prefix matching covers it.
        DB::statement(<<<'SQL'
            ALTER TABLE dokumen ADD COLUMN carian tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple'::regconfig, coalesce(tajuk, '') || ' ' || coalesce(nama_fail, '') || ' ' || coalesce(no_rujukan, '')), 'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(kata_kunci::text, '')), 'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(metadata::text, '') || ' ' || coalesce(jenis_entiti_induk, '') || ' ' || coalesce(id_rekod_induk, '')), 'B') ||
                setweight(to_tsvector('simple'::regconfig, left(coalesce(teks_kandungan, ''), 500000)), 'C')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX dokumen_carian_gin ON dokumen USING GIN (carian)');
        DB::statement('CREATE INDEX dokumen_tajuk_trgm ON dokumen USING GIN (tajuk gin_trgm_ops)');
        DB::statement('CREATE INDEX dokumen_nama_fail_trgm ON dokumen USING GIN (nama_fail gin_trgm_ops)');

        Schema::create('dokumen_versi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->unsignedInteger('no_versi');
            $table->string('nama_fail');
            $table->string('jenis_mime', 150);
            $table->unsignedBigInteger('saiz_bait');
            $table->char('cincangan_fail', 64); // SHA-256 of the plaintext
            $table->string('kunci_storan')->nullable(); // null once disposed
            // SRS-DMS-20 envelope encryption: per-file data key wrapped by the KEK.
            $table->text('kunci_data_bersulit')->nullable();
            $table->string('id_kunci_induk', 16)->nullable();
            $table->string('algoritma', 30)->default('AES-256-GCM');
            $table->foreignId('dimuat_naik_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->jsonb('metadata_snapshot')->default('{}');
            $table->timestamps();
            $table->unique(['dokumen_id', 'no_versi']);
        });

        Schema::create('dokumen_kebenaran', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->string('jenis_penerima', 20); // pengguna | peranan
            $table->string('penerima', 64); // user id or role code
            $table->foreignId('diberi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tamat_pada')->nullable();
            $table->timestamps();
            $table->unique(['dokumen_id', 'jenis_penerima', 'penerima']);
        });

        Schema::create('dokumen_kuarantin', function (Blueprint $table) {
            $table->id();
            $table->string('nama_fail');
            $table->unsignedBigInteger('saiz_bait');
            $table->char('cincangan_fail', 64);
            $table->string('kunci_storan')->nullable();
            $table->text('kunci_data_bersulit')->nullable();
            $table->string('id_kunci_induk', 16)->nullable();
            $table->string('nama_ancaman')->nullable();
            $table->string('jenis_entiti_induk', 64)->nullable();
            $table->string('id_rekod_induk', 64)->nullable();
            $table->foreignId('dimuat_naik_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dimusnahkan_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('dokumen_imbasan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dokumen_id')->nullable()->constrained('dokumen')->nullOnDelete();
            $table->foreignId('kuarantin_id')->nullable()->constrained('dokumen_kuarantin')->nullOnDelete();
            $table->string('nama_fail');
            $table->char('cincangan_fail', 64);
            $table->string('enjin', 50);
            $table->string('versi_tandatangan')->nullable();
            $table->string('keputusan', 20); // bersih | dijangkiti | ralat
            $table->string('nama_ancaman')->nullable();
            $table->timestamp('tarikh_imbasan');
            $table->timestamps();
        });

        Schema::create('dokumen_arkib', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->timestamp('tarikh_arkib');
            $table->date('tarikh_layak_lupus')->nullable();
            $table->string('sebab', 30); // retensi_auto | manual
            $table->foreignId('diarkib_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dokumen_pelupusan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->text('justifikasi');
            $table->foreignId('dimohon_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aliran_kerja', 64)->nullable();
            $table->string('status', 20)->default('menunggu'); // menunggu | lulus | tolak | ditarik_balik | dibatalkan
            $table->unsignedBigInteger('diputuskan_oleh')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['geran', 'Geran / Hak Milik', 'sulit', null, null],
            ['pelan', 'Pelan Tanah / Pelan Ukur', 'dalaman', null, null],
            ['perjanjian', 'Perjanjian / Kontrak', 'sulit', 120, 120],
            ['resit', 'Resit / Bil', 'dalaman', 84, 12],
            ['surat_rasmi', 'Surat Rasmi', 'dalaman', 84, 36],
            ['gambar_tapak', 'Gambar Tapak', 'dalaman', 60, 24],
            ['laporan', 'Laporan', 'dalaman', 84, 36],
            ['fail_gis', 'Fail GIS / Spatial', 'dalaman', null, null],
            ['lain', 'Lain-lain', 'dalaman', 60, 24],
        ];
        DB::table('dokumen_kategori')->insert(array_map(fn (array $d) => [
            'kod' => $d[0],
            'nama' => $d[1],
            'klasifikasi_lalai' => $d[2],
            'tempoh_retensi_bulan' => $d[3],
            'tempoh_arkib_bulan' => $d[4],
            'tindakan_selepas_arkib' => $d[3] === null ? 'kekal' : 'semak',
            'jenis_fail_dibenarkan' => $d[0] === 'gambar_tapak' ? json_encode(['jpg', 'jpeg', 'png', 'tiff', 'tif']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults));
    }

    public function down(): void
    {
        foreach (['dokumen_pelupusan', 'dokumen_arkib', 'dokumen_imbasan', 'dokumen_kuarantin', 'dokumen_kebenaran', 'dokumen_versi', 'dokumen', 'dokumen_medan_metadata', 'dokumen_kategori'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP SEQUENCE IF EXISTS dokumen_no_rujukan_seq');
    }
};
