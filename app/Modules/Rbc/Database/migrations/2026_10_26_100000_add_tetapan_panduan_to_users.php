<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS-GEN-08: per-user interactive guide preferences —
     * {"aktif": bool, "selesai": {"<tour key>": "<version>"}}. Null = not yet
     * chosen, so the ADM default `rbc.panduan_aktif_lalai` applies.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('tetapan_panduan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tetapan_panduan');
        });
    }
};
