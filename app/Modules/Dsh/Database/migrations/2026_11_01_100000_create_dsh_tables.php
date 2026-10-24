<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-DSH owns no master data (SRS §9 Modul 1, "Entiti data"): only its
 * configuration and per-user state. Everything else is read (never written)
 * from the owning modules' tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides / additions to the widget catalogue (SRS-DSH-09/10).
        Schema::create('dsh_definisi_widget', function (Blueprint $t) {
            $t->id();
            $t->string('kod_widget', 80)->unique();
            $t->string('tajuk', 160)->nullable();
            $t->string('jenis_visual', 30);
            $t->string('pertanyaan_sumber', 120)->comment('kpi:<kunci KPI> atau sistem:<kod>');
            $t->json('aras_dashboard')->comment('eksekutif | pengurusan | operasi');
            $t->unsignedTinyInteger('saiz')->default(1);
            $t->unsignedSmallInteger('susunan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Per-user personal layout (SRS-DSH-16).
        Schema::create('dsh_susun_atur_pengguna', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $t->string('aras', 20);
            $t->string('kod_widget', 80);
            $t->unsignedSmallInteger('posisi');
            $t->unsignedTinyInteger('saiz')->default(1);
            $t->boolean('tersembunyi')->default(false);
            $t->string('jenis_visual', 30)->nullable();
            $t->timestamps();
            $t->unique(['id_pengguna', 'aras', 'kod_widget']);
        });

        // Traffic-light / heatmap thresholds per indicator (SRS-DSH-10).
        Schema::create('dsh_ambang_indikator', function (Blueprint $t) {
            $t->id();
            $t->string('kod_indikator', 80)->unique();
            $t->string('arah', 12)->default('tinggi')->comment('tinggi = nilai tinggi lebih baik; rendah = nilai rendah lebih baik');
            $t->decimal('nilai_hijau', 18, 2);
            $t->decimal('nilai_kuning', 18, 2);
            $t->decimal('nilai_merah', 18, 2)->nullable();
            $t->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Saved filter views (SRS-DSH-14).
        Schema::create('dsh_paparan_kegemaran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $t->string('nama_paparan', 120);
            $t->string('aras', 20)->nullable();
            $t->jsonb('parameter_penapis');
            $t->boolean('lalai')->default(false);
            $t->timestamps();
            $t->unique(['id_pengguna', 'nama_paparan']);
        });

        // Transaction drafts (SRS-DSH-22) — never touches master records.
        Schema::create('dsh_draf_transaksi', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $t->string('modul', 10);
            $t->string('jenis', 60);
            $t->string('tajuk', 200);
            $t->string('pautan', 255)->nullable()->comment('Laluan borang untuk menyambung draf');
            $t->jsonb('muatan_json');
            $t->timestamp('tarikh_simpan');
            $t->timestamps();
            $t->index(['id_pengguna', 'modul']);
        });

        // AI prompt log (SRS-DSH-17) — also mirrored to the central audit trail.
        Schema::create('dsh_log_prompt_ai', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $t->text('teks_prompt');
            $t->jsonb('pertanyaan_dijana');
            $t->jsonb('skop_rbac');
            $t->jsonb('jawapan')->nullable();
            $t->string('enjin', 20);
            $t->string('status', 20)->comment('dijawab | terhad | tidak_difahami | ralat');
            $t->timestamp('cap_masa');
            $t->timestamps();
            $t->index(['id_pengguna', 'cap_masa']);
        });

        // KPI snapshots (scheduled digests, exports and history).
        Schema::create('dsh_snapshot', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $t->string('aras', 20);
            $t->string('tandatangan_skop', 40);
            $t->jsonb('penapis');
            $t->jsonb('nilai');
            $t->string('sumber', 20)->default('manual')->comment('manual | berjadual');
            $t->timestamp('dijana_pada');
            $t->timestamps();
            $t->index(['id_pengguna', 'dijana_pada']);
        });

        // Scheduled e-mail digest subscriptions (via MOD-NTF).
        Schema::create('dsh_langganan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $t->string('kekerapan', 12)->comment('harian | mingguan | bulanan');
            $t->string('aras', 20);
            $t->jsonb('penapis');
            $t->boolean('aktif')->default(true);
            $t->timestamp('terakhir_dihantar')->nullable();
            $t->timestamps();
            $t->unique(['id_pengguna', 'aras']);
        });
    }

    public function down(): void
    {
        foreach (['dsh_langganan', 'dsh_snapshot', 'dsh_log_prompt_ai', 'dsh_draf_transaksi', 'dsh_paparan_kegemaran', 'dsh_ambang_indikator', 'dsh_susun_atur_pengguna', 'dsh_definisi_widget'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
