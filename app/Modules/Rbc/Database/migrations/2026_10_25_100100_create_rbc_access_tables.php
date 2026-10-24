<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC tables (SRS-RBC-04..09): dynamic roles, their permissions and
     * scopes, user ↔ role, user scopes, the persisted permission catalogue,
     * and SSO claim → role/scope mapping rules (SRS-RBC-02).
     */
    public function up(): void
    {
        // Persisted copy of PermissionRegistry::all(). A key seen for the first
        // time gets the base-role defaults once; after that admins own it.
        Schema::create('kebenaran', function (Blueprint $table) {
            $table->string('kunci', 150)->primary();
            $table->string('modul', 30)->index();
            $table->string('fungsi', 60);
            $table->string('tindakan', 30);
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('peranan', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 60)->unique();
            $table->string('nama');
            // One of the seven RFP 11.2.3 categories, or null for a custom role.
            $table->string('kategori_rfp', 60)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | tidak_aktif
            $table->boolean('boleh_padam')->default(true);
            $table->timestamps();
        });

        Schema::create('peranan_kebenaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peranan_id')->constrained('peranan')->cascadeOnDelete();
            $table->string('kebenaran', 150);
            $table->string('modul', 30);
            $table->string('fungsi', 60);
            $table->string('tindakan', 30);
            $table->timestamps();
            $table->unique(['peranan_id', 'kebenaran']);
            $table->index('kebenaran');
        });

        // Role-level scope (SRS-RBC-06 "skop daerah dan kategori data").
        Schema::create('peranan_skop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peranan_id')->constrained('peranan')->cascadeOnDelete();
            $table->string('jenis_skop', 30);
            $table->string('nilai_skop', 100);
            $table->timestamps();
            $table->unique(['peranan_id', 'jenis_skop', 'nilai_skop']);
        });

        Schema::create('pengguna_peranan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('peranan_id')->constrained('peranan')->cascadeOnDelete();
            // manual (admin) | sso (claim mapping, re-synced every SSO login)
            $table->string('sumber', 10)->default('manual');
            $table->foreignId('diberi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'peranan_id']);
        });

        Schema::create('pengguna_skop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis_skop', 30);
            $table->string('nilai_skop', 100);
            $table->string('sumber', 10)->default('manual');
            $table->timestamps();
            $table->unique(['user_id', 'jenis_skop', 'nilai_skop']);
        });

        // SRS-RBC-02: "if claim <jenis_tuntutan> equals <nilai>, grant role and/or scope".
        Schema::create('pemetaan_tuntutan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_tuntutan', 20); // roles | bahagian | unit | jawatan
            $table->string('nilai', 150); // exact (case-insensitive) or wildcard pattern with *
            $table->foreignId('peranan_id')->nullable()->constrained('peranan')->cascadeOnDelete();
            $table->string('skop_jenis', 30)->nullable();
            $table->string('skop_nilai', 100)->nullable(); // literal, or "{nilai}" = the claim value
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('keutamaan')->default(100);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemetaan_tuntutan');
        Schema::dropIfExists('pengguna_skop');
        Schema::dropIfExists('pengguna_peranan');
        Schema::dropIfExists('peranan_skop');
        Schema::dropIfExists('peranan_kebenaran');
        Schema::dropIfExists('peranan');
        Schema::dropIfExists('kebenaran');
    }
};
