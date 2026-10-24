<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boundaries (negeri / daerah / mukim / PBT) and public points of interest,
 * all SRID 4326. Data comes from OpenStreetMap (ODbL) — see
 * app/Modules/Ref/Database/data/MANIFEST.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_sempadan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20); // negeri | daerah | mukim | pbt
            $table->string('kod', 20);
            $table->string('nama', 150);
            $table->geometry('geometri', subtype: 'multipolygon', srid: 4326);
            $table->boolean('anggaran')->default(false); // true: derived/approximate, not a surveyed boundary
            $table->decimal('toleransi_permudah', 10, 6)->nullable(); // degrees used for simplification
            $table->decimal('luas_km2', 12, 3)->nullable();
            $table->string('sumber', 255);
            $table->string('sumber_url', 500)->nullable();
            $table->string('lesen', 100)->nullable();
            $table->unsignedBigInteger('osm_id')->nullable();
            $table->date('diambil_pada')->nullable();
            $table->timestamps();

            $table->unique(['jenis', 'kod']);
            $table->spatialIndex('geometri');
        });

        Schema::create('ref_tempat_ibadat', function (Blueprint $table) {
            $table->id();
            $table->string('osm_jenis', 10); // node | way | relation
            $table->unsignedBigInteger('osm_id');
            $table->string('nama', 255)->nullable();
            $table->string('jenis', 20); // masjid | surau | lain
            $table->string('daerah_kod', 20)->nullable()->index();
            $table->string('mukim_kod', 20)->nullable()->index();
            $table->geometry('geometri', subtype: 'point', srid: 4326);
            $table->json('tag')->nullable();
            $table->string('sumber', 255);
            $table->string('lesen', 100);
            $table->date('diambil_pada');
            $table->timestamps();

            $table->unique(['osm_jenis', 'osm_id']);
            $table->spatialIndex('geometri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_tempat_ibadat');
        Schema::dropIfExists('ref_sempadan');
    }
};
