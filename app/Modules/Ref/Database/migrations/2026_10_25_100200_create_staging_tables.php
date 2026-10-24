<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-track staging area (SRS Bab 15, SRS-DAT-08). Source rows are kept
 * here as-is (no uniqueness on business keys — duplicates are the migration's
 * job to find), each with its lineage: sumber_sistem, fail_asal, kunci_asal
 * and the migrasi_larian that loaded it. MOD-PRO imports into lot_tanah.
 *
 * Today only SYNTHETIC rows exist (`php artisan ref:sample-lots`), flagged
 * sintetik = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migrasi_larian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 50);
            $table->string('sumber_sistem', 100);
            $table->json('parameter')->nullable();
            $table->string('status', 20)->default('berjalan'); // berjalan | selesai | gagal
            $table->unsignedInteger('bilangan_diterima')->default(0);
            $table->unsignedInteger('bilangan_ditolak')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamp('mula_pada');
            $table->timestamp('tamat_pada')->nullable();
            $table->timestamps();
        });

        $lineage = function (Blueprint $table): void {
            $table->boolean('sintetik')->default(false);
            $table->string('sumber_sistem', 100);
            $table->string('fail_asal', 255);
            $table->string('kunci_asal', 100);
            $table->timestamp('dimuat_pada');
            $table->timestamps();
            $table->unique(['sumber_sistem', 'fail_asal', 'kunci_asal']);
        };

        Schema::create('staging_lot_tanah', function (Blueprint $table) use ($lineage) {
            $table->uuid('id')->primary();
            $table->foreignUuid('migrasi_larian_id')->constrained('migrasi_larian');
            $table->string('no_lot', 30);
            $table->string('jenis_lot', 10); // LOT | PT
            $table->string('negeri_kod', 20);
            $table->string('daerah_kod', 20)->index();
            $table->string('mukim_kod', 20);
            $table->decimal('keluasan', 14, 4);
            $table->string('unit_keluasan', 20); // meter_persegi | hektar
            $table->string('kategori_guna_tanah_kod', 20)->nullable();
            $table->string('zoning', 100)->nullable();
            $table->text('syarat_nyata')->nullable();
            $table->text('sekatan_kepentingan')->nullable();
            $table->boolean('wakaf')->default(false);
            $table->string('jenis_wakaf', 10)->nullable(); // am | khas
            $table->boolean('baitulmal')->default(false);
            $table->geometry('geometri', subtype: 'polygon', srid: 4326)->nullable();
            $lineage($table);

            $table->index(['mukim_kod', 'no_lot']);
            $table->spatialIndex('geometri');
        });

        Schema::create('staging_hak_milik', function (Blueprint $table) use ($lineage) {
            $table->uuid('id')->primary();
            $table->foreignUuid('migrasi_larian_id')->constrained('migrasi_larian');
            $table->foreignUuid('staging_lot_tanah_id')->constrained('staging_lot_tanah')->cascadeOnDelete();
            $table->string('no_hak_milik', 30);
            $table->string('jenis_hak_milik_kod', 20);
            $table->date('tarikh_daftar')->nullable();
            $table->date('tarikh_tamat')->nullable();
            $table->unsignedSmallInteger('tempoh_pajakan_tahun')->nullable();
            $table->text('sekatan_kepentingan')->nullable();
            $table->string('lokasi_simpanan_fizikal', 150)->nullable();
            $lineage($table);
        });

        Schema::create('staging_pemilik', function (Blueprint $table) use ($lineage) {
            $table->uuid('id')->primary();
            $table->foreignUuid('migrasi_larian_id')->constrained('migrasi_larian');
            $table->foreignUuid('staging_hak_milik_id')->constrained('staging_hak_milik')->cascadeOnDelete();
            $table->string('peranan', 30); // pemilik_berdaftar | pemegang_amanah | pewakaf
            $table->string('jenis_pemilik', 20); // individu | organisasi
            $table->string('nama', 200);
            $table->string('no_pengenalan', 20)->nullable();
            $table->string('bahagian', 20)->nullable(); // e.g. 1/1, 1/2
            $lineage($table);
        });

        // Synthetic people must carry an IC number that cannot belong to anyone:
        // place-of-birth code "00" is not issued by JPN.
        DB::statement("ALTER TABLE staging_pemilik ADD CONSTRAINT staging_pemilik_ic_sintetik_check CHECK (sintetik = false OR no_pengenalan IS NULL OR no_pengenalan ~ '^[0-9]{6}-00-[0-9]{4}$')");
        DB::statement('ALTER TABLE staging_lot_tanah ADD CONSTRAINT staging_lot_tanah_keluasan_check CHECK (keluasan > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_pemilik');
        Schema::dropIfExists('staging_hak_milik');
        Schema::dropIfExists('staging_lot_tanah');
        Schema::dropIfExists('migrasi_larian');
    }
};
