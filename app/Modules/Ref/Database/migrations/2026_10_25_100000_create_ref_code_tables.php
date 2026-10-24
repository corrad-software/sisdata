<?php

use App\Modules\Ref\Support\RefSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned reference code tables (SRS-DAT-05). See RefSchema for the
 * common columns and duplicate-prevention indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_negeri', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('kod_iso', 10)->nullable();
            $table->string('jenis', 30)->default('negeri'); // negeri | wilayah_persekutuan
        });
        RefSchema::currentUniqueIndexes('ref_negeri');

        Schema::create('ref_daerah', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('negeri_kod', 20)->index();
            $table->string('kod_pendek', 4);
            $table->boolean('kod_disahkan')->default(false);
            $table->json('nama_lain')->nullable();
            $table->string('wikidata', 20)->nullable();
            $table->unsignedBigInteger('osm_relation_id')->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_daerah', ['negeri_kod']);

        Schema::create('ref_mukim', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('daerah_kod', 20)->index();
            $table->string('kod_pendek', 4);
            $table->boolean('kod_disahkan')->default(false);
            $table->json('nama_lain')->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_mukim', ['daerah_kod']);

        Schema::create('ref_pbt', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('singkatan', 20);
            $table->string('jenis', 30); // majlis_bandaraya | majlis_perbandaran | majlis_daerah
            $table->string('negeri_kod', 20)->index();
            $table->json('daerah_kod')->nullable();
            $table->string('wikidata', 20)->nullable();
            $table->unsignedBigInteger('osm_relation_id')->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_pbt');

        Schema::create('ref_kategori_guna_tanah', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('rujukan_undang', 100)->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_kategori_guna_tanah');

        Schema::create('ref_jenis_hak_milik', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('singkatan', 10);
            $table->string('peringkat', 20); // negeri | daerah | tiada
            $table->boolean('sementara')->default(false);
            $table->boolean('hak_milik')->default(true); // false: TOL / rizab — not a registered title
            $table->string('rujukan_undang', 100)->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_jenis_hak_milik');

        Schema::create('ref_jenis_dokumen', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->string('klasifikasi_lalai', 20)->nullable();
        });
        RefSchema::currentUniqueIndexes('ref_jenis_dokumen');

        Schema::create('ref_kategori_aduan', function (Blueprint $table) {
            RefSchema::columns($table);
        });
        RefSchema::currentUniqueIndexes('ref_kategori_aduan');

        Schema::create('ref_klasifikasi_data', function (Blueprint $table) {
            RefSchema::columns($table);
            $table->unsignedSmallInteger('aras');
        });
        RefSchema::currentUniqueIndexes('ref_klasifikasi_data');
    }

    public function down(): void
    {
        foreach (['ref_klasifikasi_data', 'ref_kategori_aduan', 'ref_jenis_dokumen', 'ref_jenis_hak_milik', 'ref_kategori_guna_tanah', 'ref_pbt', 'ref_mukim', 'ref_daerah', 'ref_negeri'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
