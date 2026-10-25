<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-KBA · Pangkalan Pengetahuan Berbantu AI. Embeddings, the semantic
 * index, full-text + trigram indexes and the access metadata all live in the
 * SAME PostgreSQL database (pgvector, SRS-KBA-06) — no separate vector store.
 * Each segment carries its own classification, allowed roles and district so
 * retrieval is filtered before ranking (SRS-KBA-07/08).
 */
return new class extends Migration
{
    /** nomic-embed-text → 768; changing models with another size needs a new migration. */
    private const DIMENSI = 768;

    public function up(): void
    {
        foreach (['vector', 'pg_trgm'] as $ext) {
            if (DB::selectOne('SELECT 1 AS ada FROM pg_extension WHERE extname = ?', [$ext]) === null) {
                throw new RuntimeException("Sambungan PostgreSQL [{$ext}] tiada — jalankan infra/bin/sisdata-db-setup.sh sebagai superuser.");
            }
        }

        Schema::create('kba_sumber_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 190)->unique(); // baseline:docs/… | dms:<uuid> | muat_naik:<uuid> | artikel:<id>
            $table->string('asal', 20); // baseline | dms | muat_naik | artikel
            $table->uuid('id_dokumen_dms')->nullable()->index();
            $table->string('laluan_fail')->nullable();
            $table->string('jenis_dokumen', 30); // URS/BRS/SRS/SDS/reka_bentuk/manual_*/SOP/nota_keluaran/artikel_tiket/…
            $table->string('tajuk');
            $table->string('versi', 40)->nullable();
            $table->unsignedInteger('no_versi')->default(0);
            $table->string('modul', 10)->nullable();
            $table->foreignId('id_pemilik')->nullable()->constrained('users')->nullOnDelete();
            $table->string('klasifikasi', 20)->default('dalaman');
            $table->jsonb('senarai_peranan_dibenarkan')->default('[]');
            $table->string('kod_daerah', 20)->nullable();
            $table->jsonb('peraturan_seksyen')->default('[]'); // [{padanan, klasifikasi, peranan}] per-section overrides
            $table->string('format', 20)->default('markdown'); // markdown | teks | pdf | docx
            $table->longText('teks_sumber')->nullable(); // Markdown/teks for sources not held by DMS
            $table->char('cincangan_kandungan', 64)->nullable();
            $table->string('status_indeks', 20)->default('menunggu'); // menunggu | sedang | selesai | gagal | tiada_teks
            $table->string('mesej_indeks', 500)->nullable();
            $table->unsignedInteger('bilangan_segmen')->default(0);
            $table->timestamp('tarikh_indeks_akhir')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['asal', 'aktif']);
            $table->index('jenis_dokumen');
        });

        Schema::create('kba_segmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_sumber')->constrained('kba_sumber_dokumen')->cascadeOnDelete();
            $table->unsignedInteger('no_versi_sumber');
            $table->unsignedInteger('urutan');
            $table->string('nombor_seksyen', 40)->nullable();
            $table->string('tajuk_seksyen', 300)->nullable();
            $table->string('laluan_seksyen', 600)->nullable();
            $table->text('kandungan_teks');
            $table->char('cincangan', 64);
            $table->string('model_embedding', 80)->nullable();
            $table->string('klasifikasi', 20)->default('dalaman');
            $table->jsonb('senarai_peranan_dibenarkan')->default('[]');
            $table->string('kod_daerah', 20)->nullable();
            $table->uuid('id_dokumen_dms')->nullable();
            $table->string('versi_sistem_dirujuk', 40)->nullable();
            $table->string('modul', 10)->nullable();
            $table->boolean('mengandungi_arahan')->default(false); // SRS-KBA-19: embedded instructions detected
            $table->string('status', 10)->default('aktif'); // aktif | lapuk
            $table->timestamps();

            $table->index(['id_sumber', 'status', 'urutan']);
            $table->index(['status', 'klasifikasi']);
            $table->index(['modul', 'status']);
        });
        DB::statement('ALTER TABLE kba_segmen ADD COLUMN embedding vector('.self::DIMENSI.')');
        DB::statement("ALTER TABLE kba_segmen ADD COLUMN carian tsvector GENERATED ALWAYS AS (
            setweight(to_tsvector('simple', coalesce(tajuk_seksyen, '')), 'A') ||
            setweight(to_tsvector('simple', coalesce(laluan_seksyen, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(kandungan_teks, '')), 'C')
        ) STORED");
        DB::statement("CREATE INDEX kba_segmen_embedding_hnsw ON kba_segmen USING hnsw (embedding vector_cosine_ops) WHERE status = 'aktif'");
        DB::statement('CREATE INDEX kba_segmen_carian_gin ON kba_segmen USING gin (carian)');
        DB::statement('CREATE INDEX kba_segmen_kandungan_trgm ON kba_segmen USING gin (kandungan_teks gin_trgm_ops)');
        DB::statement('CREATE INDEX kba_segmen_tajuk_trgm ON kba_segmen USING gin (tajuk_seksyen gin_trgm_ops)');
        DB::statement('CREATE INDEX kba_segmen_peranan_gin ON kba_segmen USING gin (senarai_peranan_dibenarkan)');

        Schema::create('kba_artikel', function (Blueprint $table) {
            $table->id();
            $table->string('tajuk');
            $table->string('kategori', 60)->nullable();
            $table->string('modul', 10)->nullable();
            $table->longText('kandungan');
            $table->string('status', 20)->default('draf'); // draf | dalam_semakan | diterbitkan | lapuk
            $table->unsignedInteger('versi')->default(1);
            $table->unsignedInteger('versi_terbit')->nullable(); // version users see while a newer draft is reviewed
            $table->string('klasifikasi', 20)->default('dalaman');
            $table->jsonb('senarai_peranan_dibenarkan')->default('[]');
            $table->foreignId('id_pemilik')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_penyemak')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_terbit')->nullable();
            $table->date('tarikh_semakan_akhir')->nullable();
            $table->date('tarikh_semakan_seterusnya')->nullable();
            $table->string('sumber_asal', 10)->default('manual'); // manual | tiket | ai
            $table->string('id_tiket_asal', 64)->nullable()->index();
            $table->string('versi_sistem_dirujuk', 40)->nullable();
            $table->boolean('perlu_semakan')->default(false);
            $table->string('sebab_perlu_semakan', 300)->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->string('catatan_semakan', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'modul']);
        });

        Schema::create('kba_versi_artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_artikel')->constrained('kba_artikel')->cascadeOnDelete();
            $table->unsignedInteger('nombor_versi');
            $table->string('tajuk');
            $table->longText('kandungan');
            $table->foreignId('id_pengubah')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sebab_perubahan', 500)->nullable();
            $table->timestamp('cap_masa');

            $table->unique(['id_artikel', 'nombor_versi']);
        });

        Schema::create('kba_perbualan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->string('tajuk', 200);
            $table->boolean('disembunyikan')->default(false);
            $table->timestamps();

            $table->index(['id_pengguna', 'updated_at']);
        });

        Schema::create('kba_log_prompt', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_perbualan')->nullable()->index();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('peranan')->default('[]');
            $table->string('alamat_ip', 45)->nullable();
            $table->string('saluran', 20)->default('aplikasi'); // aplikasi | kontrak (TKT/PWS/DSH)
            $table->text('prompt');
            $table->string('prompt_dinormalkan', 500);
            $table->string('bahasa', 2)->default('ms');
            $table->text('jawapan')->nullable();
            $table->string('mod_jawapan', 15)->nullable(); // ekstraktif | generatif
            $table->string('model_digunakan', 80)->nullable();
            $table->string('model_embedding', 80)->nullable();
            $table->jsonb('senarai_id_segmen_dicapai')->default('[]');
            $table->jsonb('senarai_sumber_dipetik')->default('[]');
            $table->unsignedInteger('bilangan_token')->default(0);
            $table->unsignedInteger('masa_respons_ms')->default(0);
            $table->float('skor_tertinggi')->nullable();
            $table->string('modul', 10)->nullable();
            $table->string('status', 15); // berjaya | tiada_sumber | ditolak
            $table->string('sebab', 300)->nullable();
            $table->timestamp('cap_masa');

            $table->index(['id_pengguna', 'cap_masa']);
            $table->index(['status', 'cap_masa']);
            $table->index('prompt_dinormalkan');
        });

        Schema::create('kba_maklum_balas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_log_prompt')->unique()->constrained('kba_log_prompt')->cascadeOnDelete();
            $table->string('penilaian', 20); // membantu | tidak_membantu | sumber_salah
            $table->string('ulasan', 2000)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cap_masa');
            $table->string('status_tindakan', 20)->default('baharu'); // baharu | dalam_tindakan | selesai | ditolak
            $table->string('catatan_tindakan', 1000)->nullable();
            $table->foreignId('id_pentadbir')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status_tindakan', 'penilaian']);
        });

        Schema::create('kba_analitik', function (Blueprint $table) {
            $table->id();
            $table->string('soalan_dinormalkan', 500);
            $table->unsignedInteger('bilangan_ditanya');
            $table->unsignedSmallInteger('kadar_jawapan_memuaskan'); // %
            $table->foreignId('id_sumber_paling_dirujuk')->nullable()->constrained('kba_sumber_dokumen')->nullOnDelete();
            $table->foreignId('id_artikel_paling_dirujuk')->nullable()->constrained('kba_artikel')->nullOnDelete();
            $table->string('modul', 10)->nullable();
            $table->string('tempoh', 7); // YYYY-MM
            $table->timestamps();

            $table->unique(['tempoh', 'soalan_dinormalkan']);
        });

        Schema::create('kba_pemetaan_kebenaran', function (Blueprint $table) {
            $table->id();
            $table->string('klasifikasi', 20);
            $table->string('peranan', 64);
            $table->string('jenis_capaian', 10)->default('baca'); // baca | semak | terbit
            $table->date('tarikh_berkuat_kuasa');
            $table->timestamps();

            $table->unique(['klasifikasi', 'peranan', 'jenis_capaian']);
        });

        Schema::create('kba_keluaran', function (Blueprint $table) {
            $table->id();
            $table->string('versi', 40)->unique();
            $table->string('catatan', 1000)->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('bilangan_artikel_ditanda')->default(0);
            $table->timestamps();
        });

        $today = now()->toDateString();
        DB::table('kba_pemetaan_kebenaran')->insert([
            ['klasifikasi' => 'sulit', 'peranan' => 'pentadbir_sistem', 'jenis_capaian' => 'baca', 'tarikh_berkuat_kuasa' => $today, 'created_at' => now(), 'updated_at' => now()],
            ['klasifikasi' => 'rahsia', 'peranan' => 'pentadbir_sistem', 'jenis_capaian' => 'baca', 'tarikh_berkuat_kuasa' => $today, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        foreach (['kba_keluaran', 'kba_pemetaan_kebenaran', 'kba_analitik', 'kba_maklum_balas', 'kba_log_prompt', 'kba_perbualan', 'kba_versi_artikel', 'kba_artikel', 'kba_segmen', 'kba_sumber_dokumen'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
