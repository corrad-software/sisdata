<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account lifecycle, sessions, login log, alerts, security policy and
     * access recertification (SRS-RBC-12..22, 26, 27).
     */
    public function up(): void
    {
        Schema::create('status_akaun_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status_lama', 20);
            $table->string('status_baru', 20);
            $table->string('sebab')->nullable();
            $table->foreignId('dilakukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('kata_laluan_sejarah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kata_laluan');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        // Admin-tunable security parameters (SRS-RBC-13/16/18/19). Stored in
        // the DB so they change without a deploy.
        Schema::create('polisi_keselamatan', function (Blueprint $table) {
            $table->string('kunci', 60)->primary();
            $table->json('nilai');
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // SESI: one row per authenticated session; `session_id` points at the
        // Laravel `sessions` row (database driver).
        Schema::create('sesi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kaedah_log_masuk', 20)->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('sidik_peranti', 64)->nullable();
            $table->timestamp('tarikh_mula');
            $table->timestamp('tarikh_aktiviti_akhir');
            $table->timestamp('tarikh_tamat')->nullable();
            // log_keluar | tamat_masa | paksa | status_akaun | had_serentak | ikatan_gagal
            $table->string('kaedah_tamat', 20)->nullable();
            $table->foreignId('ditamatkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['user_id', 'tarikh_tamat']);
        });

        Schema::create('log_log_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pengecam')->nullable();
            $table->string('kaedah', 20); // kata_laluan | sso
            $table->boolean('berjaya');
            $table->string('sebab', 60)->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('peranti', 30)->nullable();
            $table->string('pelayar', 60)->nullable();
            $table->string('platform', 60)->nullable();
            $table->string('sidik_peranti', 64)->nullable();
            $table->string('lokasi')->nullable();
            $table->string('negara', 60)->nullable();
            $table->boolean('mencurigakan')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_id', 'created_at']);
            $table->index(['alamat_ip', 'created_at']);
        });

        Schema::create('amaran_keselamatan', function (Blueprint $table) {
            $table->id();
            // gagal_berulang | peranti_baharu | lokasi_baharu | capaian_tanpa_kebenaran
            $table->string('jenis', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alamat_ip', 45)->nullable();
            $table->json('butiran')->nullable();
            $table->timestamp('disemak_pada')->nullable();
            $table->foreignId('disemak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // SRS-RBC-27: periodic access recertification by data owners.
        Schema::create('recert_kitaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('bahagian', 100)->nullable(); // null = all users
            $table->date('tarikh_mula');
            $table->date('tarikh_akhir');
            $table->string('status', 20)->default('dibuka'); // dibuka | ditutup
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditutup_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('recert_keputusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitaran_id')->constrained('recert_kitaran')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('peranan_id')->constrained('peranan')->cascadeOnDelete();
            $table->string('keputusan', 20)->default('belum'); // belum | disahkan | ditarik
            $table->string('catatan')->nullable();
            $table->foreignId('disemak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disemak_pada')->nullable();
            $table->timestamps();
            $table->unique(['kitaran_id', 'user_id', 'peranan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recert_keputusan');
        Schema::dropIfExists('recert_kitaran');
        Schema::dropIfExists('amaran_keselamatan');
        Schema::dropIfExists('log_log_masuk');
        Schema::dropIfExists('sesi');
        Schema::dropIfExists('polisi_keselamatan');
        Schema::dropIfExists('kata_laluan_sejarah');
        Schema::dropIfExists('status_akaun_log');
    }
};
