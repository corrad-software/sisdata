<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A human name for the property ("Wisma MAIS Seksyen 3", "Stesen Minyak
 * Wakaf Johan Setia"). Officers recognise properties by name far more than by
 * lot number; the 360° profile and lot list show it above the lot number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_tanah', function (Blueprint $table) {
            $table->string('nama_hartanah', 200)->nullable()->after('no_lot');
        });
        DB::statement('CREATE INDEX lot_tanah_nama_hartanah_trgm ON lot_tanah USING gin (nama_hartanah gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::table('lot_tanah', function (Blueprint $table) {
            $table->dropIndex('lot_tanah_nama_hartanah_trgm');
            $table->dropColumn('nama_hartanah');
        });
    }
};
