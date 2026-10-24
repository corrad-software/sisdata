<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-ADM slice e: central search configuration (SRS-ADM-20). ADM_INDEKS_CARIAN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adm_indeks_carian', function (Blueprint $table) {
            $table->id();
            $table->string('entiti', 60)->unique();
            $table->string('label', 120)->nullable();
            $table->json('medan')->nullable()->comment('searchable fields, passed to the provider');
            $table->decimal('pemberat', 5, 2)->default(1);
            $table->boolean('aktif')->default(true);
            $table->string('status_indeks', 20)->nullable(); // beratur | berjalan | siap | gagal | tidak_disokong
            $table->timestamp('diindeks_semula_pada')->nullable();
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_sinonim_carian', function (Blueprint $table) {
            $table->id();
            $table->string('istilah', 120)->unique();
            $table->json('sinonim');
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adm_sinonim_carian');
        Schema::dropIfExists('adm_indeks_carian');
    }
};
