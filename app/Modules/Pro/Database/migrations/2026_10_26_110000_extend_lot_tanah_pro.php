<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PRO extensions to the lead's lot_tanah / data_spatial core:
 *  - lineage of migrated / imported rows (SRS-DAT-08),
 *  - normalised lot number for duplicate detection (SRS-PRO-20, pg_trgm),
 *  - archive / merge state (SRS-PRO-23, SRS-PRO-20),
 *  - search tags (SRS-PRO-21),
 *  - data_spatial versioning + upload metadata (SRS-PRO-07..10),
 *  - CHECK constraints for the data-quality rules that never change (SRS-DAT-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_tanah', function (Blueprint $table) {
            $table->string('no_lot_normal', 50)->nullable()->comment('No. lot dinormalkan (huruf besar, tanpa tanda baca) untuk padanan duplikasi');
            $table->boolean('sintetik')->default(false)->comment('Data contoh sintetik — bukan rekod sebenar');
            $table->string('sumber_sistem', 100)->nullable()->comment('Salasilah: sistem sumber (SRS-DAT-08)');
            $table->string('fail_asal', 255)->nullable()->comment('Salasilah: jadual / fail asal');
            $table->string('kunci_asal', 100)->nullable()->comment('Salasilah: kunci rekod dalam sumber');
            $table->uuid('id_migrasi_larian')->nullable();
            $table->uuid('id_sesi_import')->nullable();
            $table->uuid('id_lot_gabung')->nullable()->comment('Lot induk apabila rekod ini digabungkan (duplikasi)');
            $table->string('status_sebelum_arkib', 30)->nullable();
            $table->timestamp('diarkib_pada')->nullable();
            $table->jsonb('tag')->default('[]');
            $table->text('catatan')->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();

            $table->index('no_lot_normal');
            $table->index('bahagian_pemilik');
            $table->index('klasifikasi');
        });

        DB::statement('CREATE INDEX lot_tanah_no_lot_normal_trgm ON lot_tanah USING gin (no_lot_normal gin_trgm_ops)');
        DB::statement('CREATE UNIQUE INDEX lot_tanah_salasilah_unique ON lot_tanah (sumber_sistem, fail_asal, kunci_asal) WHERE sumber_sistem IS NOT NULL AND deleted_at IS NULL');
        // Only invariants no module could legitimately violate are DB constraints; the
        // configurable rules (formats, ranges, status values) live in peraturan_kualiti_data.
        DB::statement('ALTER TABLE lot_tanah ADD CONSTRAINT lot_tanah_keluasan_check CHECK (keluasan IS NULL OR keluasan > 0)');
        DB::statement("ALTER TABLE lot_tanah ADD CONSTRAINT lot_tanah_klasifikasi_check CHECK (klasifikasi IN ('awam', 'dalaman', 'sulit', 'rahsia'))");
        DB::statement("ALTER TABLE lot_tanah ADD CONSTRAINT lot_tanah_bahagian_check CHECK (bahagian_pemilik IS NULL OR bahagian_pemilik IN ('hartanah', 'wakaf', 'baitulmal'))");

        Schema::table('data_spatial', function (Blueprint $table) {
            $table->unsignedInteger('versi')->default(1);
            $table->string('format_asal', 20)->nullable()->comment('geojson | wkt | kml | shp');
            $table->string('id_dokumen_sumber', 64)->nullable()->comment('Fail asal dalam DMS');
            $table->decimal('peratus_beza_keluasan', 10, 2)->nullable()->comment('Beza keluasan dikira berbanding keluasan diisytihar (%)');
            $table->foreignId('id_pemuat_naik')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_pengesahan')->nullable();
            $table->string('sumber_sistem', 100)->nullable();
            $table->string('kunci_asal', 100)->nullable();
        });

        DB::statement("ALTER TABLE data_spatial ADD CONSTRAINT data_spatial_status_check CHECK (status_pengesahan IN ('belum_disahkan', 'disahkan_juruukur', 'disahkan_ptg', 'bertindih'))");
        DB::statement("ALTER TABLE data_spatial ADD CONSTRAINT data_spatial_sumber_check CHECK (sumber_data IN ('jupem', 'ptg', 'pbt', 'juruukur', 'lakaran', 'satelit', 'sintetik'))");
        DB::statement('CREATE INDEX data_spatial_status_idx ON data_spatial (status_pengesahan) WHERE semasa');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS data_spatial_status_idx');
        DB::statement('ALTER TABLE data_spatial DROP CONSTRAINT IF EXISTS data_spatial_status_check');
        DB::statement('ALTER TABLE data_spatial DROP CONSTRAINT IF EXISTS data_spatial_sumber_check');
        Schema::table('data_spatial', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pemuat_naik');
            $table->dropColumn(['versi', 'format_asal', 'id_dokumen_sumber', 'peratus_beza_keluasan', 'catatan_pengesahan', 'sumber_sistem', 'kunci_asal']);
        });

        foreach (['keluasan', 'klasifikasi', 'bahagian'] as $check) {
            DB::statement("ALTER TABLE lot_tanah DROP CONSTRAINT IF EXISTS lot_tanah_{$check}_check");
        }
        DB::statement('DROP INDEX IF EXISTS lot_tanah_no_lot_normal_trgm');
        DB::statement('DROP INDEX IF EXISTS lot_tanah_salasilah_unique');
        Schema::table('lot_tanah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pencipta');
            $table->dropIndex(['no_lot_normal']);
            $table->dropIndex(['bahagian_pemilik']);
            $table->dropIndex(['klasifikasi']);
            $table->dropColumn(['no_lot_normal', 'sintetik', 'sumber_sistem', 'fail_asal', 'kunci_asal', 'id_migrasi_larian', 'id_sesi_import', 'id_lot_gabung', 'status_sebelum_arkib', 'diarkib_pada', 'tag', 'catatan']);
        });
    }
};
