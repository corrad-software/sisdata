<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk import (SRS-PRO-15..17, SRS-DAT-03) and scoped API keys (SRS-PRO-29).
 * API keys are Sanctum personal_access_tokens; kunci_api_pro adds the
 * per-key policy (rate limit, calling system) and usage is logged per call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemetaan_import', function (Blueprint $table) {
            $table->id();
            $table->string('entiti_sasaran', 64);
            $table->string('nama', 100);
            $table->jsonb('pemetaan')->comment('{"lajur dalam fail": "medan sasaran"}');
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entiti_sasaran', 'nama']);
        });

        Schema::create('sesi_import', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entiti_sasaran', 64);
            $table->string('nama_fail', 255);
            $table->string('format_fail', 10);
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('pemetaan')->nullable();
            $table->jsonb('lajur_fail')->nullable();
            $table->unsignedInteger('bil_baris_dibaca')->default(0);
            $table->unsignedInteger('bil_sah')->default(0);
            $table->unsignedInteger('bil_berjaya')->default(0);
            $table->unsignedInteger('bil_ditolak')->default(0);
            $table->unsignedInteger('bil_diabaikan')->default(0);
            $table->string('lokasi_fail_asal', 64)->nullable()->comment('ID dokumen DMS');
            $table->string('catatan_fail_asal', 255)->nullable();
            $table->string('status_sesi', 20)->default('pratonton')->comment('pratonton | dikomit | dibatalkan');
            $table->timestamp('tarikh_komit')->nullable();
            $table->timestamps();

            $table->index(['entiti_sasaran', 'created_at']);
        });

        Schema::create('baris_import', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_sesi')->constrained('sesi_import')->cascadeOnDelete();
            $table->unsignedInteger('no_baris');
            $table->jsonb('data');
            $table->string('status', 20)->comment('sah | ditolak | diabaikan | dikomit');
            $table->string('id_rekod', 64)->nullable();
            $table->timestamps();

            $table->unique(['id_sesi', 'no_baris']);
        });

        Schema::create('ralat_import', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('id_sesi')->constrained('sesi_import')->cascadeOnDelete();
            $table->unsignedInteger('no_baris');
            $table->string('medan', 64)->nullable();
            $table->text('nilai_diberi')->nullable();
            $table->string('kod_ralat', 50);
            $table->string('sebab_penolakan', 500);

            $table->index(['id_sesi', 'no_baris']);
        });

        Schema::create('kunci_api_pro', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_token')->unique()->comment('personal_access_tokens.id');
            $table->foreignId('id_pemilik')->constrained('users')->cascadeOnDelete();
            $table->string('sistem_pemanggil', 100);
            $table->jsonb('skop');
            $table->unsignedInteger('had_kadar_seminit')->default(60);
            $table->timestamp('tamat_pada')->nullable();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('log_penggunaan_api', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_token')->nullable();
            $table->string('sistem_pemanggil', 100)->nullable();
            $table->string('kaedah', 10);
            $table->string('laluan', 255);
            $table->unsignedSmallInteger('kod_status');
            $table->string('alamat_ip', 45)->nullable();
            $table->timestamp('tarikh_masa');

            $table->index(['id_token', 'tarikh_masa']);
        });
    }

    public function down(): void
    {
        foreach (['log_penggunaan_api', 'kunci_api_pro', 'ralat_import', 'baris_import', 'sesi_import', 'pemetaan_import'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
