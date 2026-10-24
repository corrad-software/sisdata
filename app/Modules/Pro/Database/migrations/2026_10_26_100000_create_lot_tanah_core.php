<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The anchor of the whole data model (ADR-01, SRS §4): every satellite record
 * (hak_milik, pemerolehan, pajakan, cukai, aduan…) holds a lot_id FK to this
 * table. Created by the lead before G1 so MOD-HKM and MOD-PER can reference it
 * while MOD-PRO is built in parallel; MOD-PRO extends it with later migrations.
 *
 * Daerah/mukim are stored as MOD-REF codes (daerah "1008", mukim "100803"),
 * not FKs — reference rows are versioned (see app/Modules/Ref).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_tanah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_lot', 50);
            $table->string('kod_mukim', 6);
            $table->string('kod_daerah', 4);
            $table->string('kod_negeri', 2)->default('10');
            $table->decimal('keluasan', 16, 4)->nullable();
            $table->string('unit_keluasan', 10)->default('m2');
            $table->string('kategori_guna_tanah', 30)->nullable();
            $table->string('zoning', 50)->nullable();
            $table->text('syarat_nyata')->nullable();
            $table->string('bahagian_pemilik', 30)->nullable()->comment('hartanah | wakaf | baitulmal');
            $table->string('status_lot', 30)->default('aktif');
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->foreignId('id_data_owner')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_data_steward')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('versi_rekod')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index('status_lot');
        });

        DB::statement('ALTER TABLE lot_tanah ADD COLUMN koordinat_pusat geometry(Point, 4326)');
        DB::statement('CREATE INDEX lot_tanah_koordinat_pusat_gix ON lot_tanah USING GIST (koordinat_pusat)');
        // SRS-PRO-13 / SRS-DAT-01: a lot number is unique within its mukim.
        DB::statement('CREATE UNIQUE INDEX lot_tanah_no_lot_mukim_unique ON lot_tanah (kod_mukim, no_lot) WHERE deleted_at IS NULL');

        // SRS-PRO-07: Data Spatial is a full entity of its own, not a column on the lot.
        Schema::create('data_spatial', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lot_id')->constrained('lot_tanah')->cascadeOnDelete();
            $table->integer('srid_asal')->default(4326);
            $table->decimal('keluasan_dikira', 16, 4)->nullable();
            $table->string('sumber_data', 30)->comment('jupem | ptg | pbt | juruukur | lakaran | satelit | sintetik');
            $table->string('rujukan_fail_sumber')->nullable();
            $table->date('tarikh_perolehan_data')->nullable();
            $table->string('status_pengesahan', 30)->default('belum_disahkan')
                ->comment('belum_disahkan | disahkan_juruukur | disahkan_ptg | bertindih');
            $table->foreignId('id_pegawai_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pengesahan')->nullable();
            $table->text('catatan_pertindihan')->nullable();
            $table->boolean('semasa')->default(true);
            $table->timestamps();

            $table->index(['lot_id', 'semasa']);
        });

        DB::statement('ALTER TABLE data_spatial ADD COLUMN geometri geometry(MultiPolygon, 4326) NOT NULL');
        DB::statement('CREATE INDEX data_spatial_geometri_gix ON data_spatial USING GIST (geometri)');
        DB::statement('CREATE UNIQUE INDEX data_spatial_satu_semasa ON data_spatial (lot_id) WHERE semasa');
    }

    public function down(): void
    {
        Schema::dropIfExists('data_spatial');
        Schema::dropIfExists('lot_tanah');
    }
};
