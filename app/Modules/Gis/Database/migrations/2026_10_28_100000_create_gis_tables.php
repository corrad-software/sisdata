<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-GIS entities (SRS §Modul 12 "Entiti data"). Lot geometry itself stays
 * in MOD-PRO's lot_tanah / data_spatial (the single master record, SRS-GIS-03,
 * and its version store, SRS-GIS-30) — this module only READS those. What MOD-GIS
 * owns: layer configuration + metadata, imported spatial datasets, spatial QA
 * findings, saved analysis scenarios / map views, and physical-asset points.
 * Every geometry is SRID 4326 with a GIST index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-GIS-12/32: per-layer configuration (classification, publication, order, opacity).
        Schema::create('gis_lapisan', function (Blueprint $table) {
            $table->id();
            $table->string('kod_lapisan', 60)->unique();
            $table->string('nama', 150);
            $table->string('kumpulan', 20)->default('hartanah');
            $table->string('jenis_geometri', 10)->default('polygon');
            $table->string('klasifikasi_keselamatan', 10)->default('dalaman')->comment('awam | dalaman | sulit | rahsia');
            $table->string('status_terbit', 20)->default('terbit')->comment('terbit | draf | tidak_aktif');
            $table->unsignedInteger('urutan_paparan')->default(100);
            $table->decimal('ketelusan_lalai', 3, 2)->default(0.6);
            $table->boolean('papar_lalai')->default(false);
            $table->boolean('terbit_ogc')->default(false)->comment('Diterbitkan sebagai WMS/WFS melalui proksi');
            $table->boolean('lapisan_awam')->default(false)->comment('Boleh digunakan kumpulan lapisan awam (portal)');
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE gis_lapisan ADD CONSTRAINT gis_lapisan_klasifikasi_check CHECK (klasifikasi_keselamatan IN ('awam', 'dalaman', 'sulit', 'rahsia'))");
        DB::statement("ALTER TABLE gis_lapisan ADD CONSTRAINT gis_lapisan_status_check CHECK (status_terbit IN ('terbit', 'draf', 'tidak_aktif'))");

        // SRS-GIS-29: metadata per layer and per imported dataset.
        Schema::create('gis_metadata_lapisan', function (Blueprint $table) {
            $table->id();
            $table->string('kod_lapisan', 60)->nullable()->index();
            $table->uuid('id_import')->nullable()->index();
            $table->string('sumber_data', 255)->nullable();
            $table->string('pemilik_data', 150)->nullable();
            $table->date('tarikh_kemas_kini')->nullable();
            $table->string('status_pengesahan', 30)->default('belum_disahkan')->comment('belum_disahkan | disahkan | ditolak');
            $table->string('sistem_unjuran', 60)->default('EPSG:4326 (WGS 84)');
            $table->string('tahap_ketepatan', 100)->nullable();
            $table->text('had_penggunaan')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX gis_metadata_lapisan_kod_unique ON gis_metadata_lapisan (kod_lapisan) WHERE kod_lapisan IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX gis_metadata_lapisan_import_unique ON gis_metadata_lapisan (id_import) WHERE id_import IS NOT NULL');

        // SRS-GIS-22: import sessions + their features (validated before acceptance).
        Schema::create('gis_log_import', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_set_data', 150);
            $table->string('nama_fail', 255);
            $table->string('format', 20)->comment('geojson | kml | kmz | shp | dxf | gpkg');
            $table->integer('srid_asal')->nullable();
            $table->string('kategori', 30)->default('umum')->comment('umum | lot | zoning | infrastruktur | risiko | sempadan');
            $table->unsignedInteger('bilangan_ciri')->default(0);
            $table->unsignedInteger('bilangan_sah')->default(0);
            $table->unsignedInteger('bilangan_ralat')->default(0);
            $table->string('status', 20)->default('disemak')->comment('disemak | ditolak | sedia | diterima | dibatalkan');
            $table->jsonb('ralat_fail')->default('[]');
            $table->jsonb('amaran')->default('[]');
            $table->jsonb('medan')->default('[]');
            $table->string('medan_no_lot', 60)->nullable();
            $table->string('id_dokumen', 64)->nullable()->comment('Fail asal dalam DMS');
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diterima_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('gis_ciri_import', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_import')->constrained('gis_log_import')->cascadeOnDelete();
            $table->unsignedInteger('no_ciri');
            $table->jsonb('atribut')->default('{}');
            $table->boolean('sah')->default(true);
            $table->jsonb('ralat')->default('[]');
            $table->uuid('lot_id')->nullable()->comment('Lot dipadankan (baca sahaja; geometri lot diterbitkan melalui MOD-PRO)');
            $table->timestamps();
            $table->index(['id_import', 'no_ciri']);
        });
        DB::statement('ALTER TABLE gis_ciri_import ADD COLUMN geom geometry(Geometry, 4326)');
        DB::statement('CREATE INDEX gis_ciri_import_geom_gix ON gis_ciri_import USING GIST (geom)');

        // SRS-GIS-27/28: spatial QA findings (feed the GIS Specialist's correction work).
        Schema::create('gis_ralat_kualiti', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_lot')->nullable()->index();
            $table->uuid('id_lot_lain')->nullable();
            $table->string('kod_daerah', 4)->nullable()->index();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('jenis_ralat', 30)->comment('geometri_tidak_sah | tiada_geometri | luar_selangor | luar_mukim | pertindihan | jurang | geometri_kosong');
            $table->string('kunci', 120)->comment('Kunci unik penemuan untuk larian semula');
            $table->text('perihal');
            $table->decimal('nilai', 16, 4)->nullable()->comment('Luas pertindihan / jurang (m²) atau jarak (m)');
            $table->text('cadangan')->nullable();
            $table->string('status_pembetulan', 20)->default('baharu')->comment('baharu | dalam_tindakan | selesai | diabaikan');
            $table->text('catatan')->nullable();
            $table->timestamp('dikesan_pada');
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('kunci');
            $table->index(['jenis_ralat', 'status_pembetulan']);
        });
        DB::statement('ALTER TABLE gis_ralat_kualiti ADD COLUMN geom geometry(Geometry, 4326)');
        DB::statement('CREATE INDEX gis_ralat_kualiti_geom_gix ON gis_ralat_kualiti USING GIST (geom)');

        // SRS-GIS-20: named, shareable analysis scenarios.
        Schema::create('gis_senario_analisis', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('jenis_analisis', 30)->comment('penimbal | tindihan | pertanyaan | keluasan | kedekatan | kluster');
            $table->jsonb('parameter_json');
            $table->jsonb('ringkasan_hasil')->default('{}');
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->string('kebenaran_kongsi', 20)->default('peribadi')->comment('peribadi | pengguna | semua');
            $table->jsonb('dikongsi_dengan')->default('[]')->comment('ID pengguna apabila kebenaran_kongsi = pengguna');
            $table->timestamps();
        });

        // Saved map views / bookmarks (layer set, extent, basemap).
        Schema::create('gis_paparan_tersimpan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->jsonb('lapisan')->default('[]');
            $table->jsonb('pusat');
            $table->decimal('zum', 5, 2);
            $table->string('peta_asas', 30)->default('osm');
            $table->boolean('dikongsi')->default(false);
            $table->timestamps();
        });

        // Layer 10 (Aset Fizikal) points until MOD-AST owns physical assets.
        Schema::create('gis_aset_titik', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_lot')->nullable()->index();
            $table->string('nama', 150);
            $table->string('kategori', 30)->comment('bangunan | premis | infrastruktur | lain');
            $table->string('status_keadaan', 30)->default('baik')->comment('baik | perlu_perhatian | kritikal');
            $table->string('kod_daerah', 4)->nullable()->index();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->text('catatan')->nullable();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE gis_aset_titik ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX gis_aset_titik_geom_gix ON gis_aset_titik USING GIST (geom)');
    }

    public function down(): void
    {
        foreach (['gis_aset_titik', 'gis_paparan_tersimpan', 'gis_senario_analisis', 'gis_ralat_kualiti', 'gis_ciri_import', 'gis_log_import', 'gis_metadata_lapisan', 'gis_lapisan'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
