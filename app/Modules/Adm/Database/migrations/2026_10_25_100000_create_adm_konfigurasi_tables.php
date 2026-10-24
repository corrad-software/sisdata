<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-ADM slice a + b: system parameters (SRS-ADM-01), versioned reference
 * codes (SRS-ADM-02, SRS-DAT-05), report templates (SRS-ADM-03) and the
 * second-admin approval queue for critical configuration (SRS-ADM-18/19).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only admin overrides live here; defaults are declared in code by
        // each module (Parameters::declare). No row = declared default.
        Schema::create('adm_parameter', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 120)->unique();
            $table->string('modul', 20)->index();
            $table->text('nilai')->nullable()->comment('JSON-encoded value');
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_kod_rujukan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kod', 60);
            $table->string('kod', 60);
            $table->string('keterangan', 255);
            $table->string('kod_induk', 60)->nullable();
            $table->unsignedInteger('susunan')->default(0);
            $table->string('status', 20)->default('aktif');
            $table->date('tarikh_mula');
            $table->date('tarikh_tamat')->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // SRS-DAT-05: no duplicate code inside a type, ever (deactivated codes keep their slot).
            $table->unique(['jenis_kod', 'kod']);
            $table->index(['jenis_kod', 'status', 'susunan']);
        });

        Schema::create('adm_kod_rujukan_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kod_rujukan')->constrained('adm_kod_rujukan')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->json('data');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_kod_rujukan', 'versi']);
        });

        Schema::create('adm_templat', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 80)->unique();
            $table->string('nama', 200);
            $table->string('jenis', 30)->default('laporan');
            $table->string('format_output', 10)->default('pdf');
            $table->longText('kandungan');
            $table->json('medan')->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->string('status', 20)->default('aktif');
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_templat_versi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_templat')->constrained('adm_templat')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('format_output', 10);
            $table->longText('kandungan');
            $table->json('medan')->nullable();
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_templat', 'versi']);
        });

        Schema::create('adm_konfig_kelulusan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_konfig', 30);
            $table->string('kunci', 120);
            $table->json('nilai_sebelum')->nullable();
            $table->json('nilai_selepas')->nullable();
            // Secrets awaiting approval: the real value, encrypted (SRS-ADM-28); nilai_selepas only shows a mask.
            $table->text('nilai_rahsia_tersulit')->nullable();
            $table->text('justifikasi');
            $table->foreignId('id_pemohon')->constrained('users');
            $table->string('alamat_ip_pemohon', 45)->nullable();
            $table->foreignId('id_pelulus')->nullable()->constrained('users');
            $table->text('catatan_pelulus')->nullable();
            $table->string('status', 20)->default('menunggu');
            $table->string('id_instans_aliran', 64)->nullable()->comment('MOD-WFE instance; null = built-in two-person approval');
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['jenis_konfig', 'kunci', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adm_konfig_kelulusan');
        Schema::dropIfExists('adm_templat_versi');
        Schema::dropIfExists('adm_templat');
        Schema::dropIfExists('adm_kod_rujukan_sejarah');
        Schema::dropIfExists('adm_kod_rujukan');
        Schema::dropIfExists('adm_parameter');
    }
};
