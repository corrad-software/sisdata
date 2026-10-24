<?php

namespace App\Modules\Ref\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Column set shared by every versioned reference table (SRS-DAT-05).
 *
 * A code (kod) can have several rows over time — one per versi. Exactly one
 * row per code is "current" (sah_hingga IS NULL); older versions keep their
 * values with the date they stopped being valid. Codes are therefore NOT
 * foreign keys: other tables store the code string and resolve it against
 * the version valid at the relevant date.
 */
class RefSchema
{
    public static function columns(Blueprint $table): void
    {
        $table->id();
        $table->string('kod', 20);
        $table->string('nama', 150);
        $table->text('keterangan')->nullable();
        $table->unsignedSmallInteger('susunan')->default(0);
        $table->boolean('aktif')->default(true);
        $table->unsignedInteger('versi')->default(1);
        $table->date('sah_dari');
        $table->date('sah_hingga')->nullable();
        $table->string('sumber', 255)->nullable();
        $table->string('sumber_url', 500)->nullable();
        $table->timestamps();

        $table->unique(['kod', 'versi']);
        $table->index(['kod', 'sah_dari']);
    }

    /**
     * Duplicate prevention: one current row per code and (within $scope) one
     * current row per name, case-insensitive.
     *
     * @param  list<string>  $nameScope  extra columns the name is unique within (e.g. daerah_kod)
     */
    public static function currentUniqueIndexes(string $table, array $nameScope = []): void
    {
        DB::statement("CREATE UNIQUE INDEX {$table}_kod_semasa_unique ON {$table} (kod) WHERE sah_hingga IS NULL");

        $cols = implode(', ', [...$nameScope, 'lower(nama)']);
        DB::statement("CREATE UNIQUE INDEX {$table}_nama_semasa_unique ON {$table} ({$cols}) WHERE sah_hingga IS NULL");

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_tarikh_sah_check CHECK (sah_hingga IS NULL OR sah_hingga >= sah_dari)");
    }
}
