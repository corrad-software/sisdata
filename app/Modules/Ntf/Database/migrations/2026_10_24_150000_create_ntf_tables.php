<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-NTF data entities (SRS Modul 15): NTF_JENIS, NTF_TEMPLAT,
 * NTF_PENERIMA_PERATURAN, NTF_MESEJ, NTF_PENGHANTARAN, NTF_STATUS_PENGGUNA,
 * NTF_KEUTAMAAN_PENGGUNA, NTF_ESKALASI_LOG.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Notification type: seeded from code (NotificationTypes::register), then admin-tunable (SRS-NTF-03/08).
        Schema::create('ntf_jenis', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 100)->unique();
            $table->string('nama');
            $table->string('modul', 20)->index();
            $table->string('keutamaan', 10); // kritikal | tinggi | biasa | maklumat
            $table->boolean('wajib')->default(false);
            $table->jsonb('saluran_lalai');
            $table->string('kekerapan', 20)->default('serta_merta'); // serta_merta | ringkasan
            $table->unsignedInteger('tempoh_eskalasi_minit')->nullable();
            $table->unsignedInteger('tempoh_pendiaman_minit')->default(0);
            $table->jsonb('hari_peringatan')->nullable();
            $table->jsonb('medan')->default('[]');
            $table->jsonb('medan_sensitif')->default('[]');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Versioned Malay templates; exactly one active per type (SRS-NTF-05, SRS-ADM-03).
        Schema::create('ntf_templat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_id')->constrained('ntf_jenis')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('subjek');
            $table->text('kandungan');
            $table->boolean('aktif')->default(false);
            $table->string('catatan')->nullable();
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['jenis_id', 'versi']);
        });

        // Recipient rules: extra recipients by role / permission / bahagian / daerah / user (SRS-NTF-03).
        Schema::create('ntf_penerima_peraturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_id')->constrained('ntf_jenis')->cascadeOnDelete();
            $table->string('sasaran', 20); // peranan | kebenaran | bahagian | daerah | pengguna
            $table->string('nilai');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['jenis_id', 'sasaran', 'nilai']);
        });

        // One rendered message per trigger, shared by all its recipients.
        Schema::create('ntf_mesej', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_id')->constrained('ntf_jenis');
            $table->foreignId('templat_id')->nullable()->constrained('ntf_templat')->nullOnDelete();
            $table->string('keutamaan', 10);
            $table->string('modul', 20);
            $table->string('subjek');
            $table->text('kandungan');
            $table->string('pautan')->nullable();
            $table->jsonb('data')->default('{}');
            $table->string('kunci_dedupe', 191)->nullable()->index();
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['keutamaan', 'created_at']);
        });

        // Delivery log per recipient per channel — never edited/deleted by users (SRS-NTF-12/15/16).
        Schema::create('ntf_penghantaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesej_id')->constrained('ntf_mesej');
            $table->foreignId('penerima_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('saluran', 20); // dalam_sistem | emel | sms | whatsapp
            $table->string('status', 20)->index(); // beratur | ditahan | dihantar | diterima | dibaca | gagal | gagal_muktamad
            $table->unsignedSmallInteger('bilangan_cubaan')->default(0);
            $table->string('kod_ralat', 60)->nullable();
            $table->text('mesej_ralat')->nullable();
            $table->boolean('ringkasan')->default(false);
            $table->timestamp('tarikh_beratur')->nullable();
            $table->timestamp('tarikh_hantar')->nullable();
            $table->timestamp('tarikh_terima')->nullable();
            $table->timestamp('tarikh_baca')->nullable();
            $table->timestamp('tarikh_gagal')->nullable();
            $table->timestamps();
            $table->index(['penerima_id', 'saluran']);
            $table->index(['saluran', 'created_at']);
        });

        // The user's personal view of a message: read / archived / logically deleted (SRS-NTF-11..13).
        Schema::create('ntf_status_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesej_id')->constrained('ntf_mesej')->cascadeOnDelete();
            $table->foreignId('pengguna_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamp('diarkib_pada')->nullable();
            $table->timestamp('dipadam_pada')->nullable();
            $table->timestamp('dieskalasi_pada')->nullable();
            $table->timestamps();
            $table->unique(['mesej_id', 'pengguna_id']);
            $table->index(['pengguna_id', 'dipadam_pada', 'dibaca_pada']);
        });

        // Per-user channel / opt-out / digest choice for non-mandatory types (SRS-NTF-09/10/20).
        Schema::create('ntf_keutamaan_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('jenis_id')->constrained('ntf_jenis')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->jsonb('saluran')->nullable();
            $table->boolean('ringkasan')->default(false);
            $table->timestamps();
            $table->unique(['pengguna_id', 'jenis_id']);
        });

        // Escalations of unread Kritikal notifications to supervisors (SRS-NTF-18).
        Schema::create('ntf_eskalasi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_pengguna_id')->nullable()->constrained('ntf_status_pengguna')->nullOnDelete();
            $table->foreignId('mesej_id')->constrained('ntf_mesej');
            $table->foreignId('penerima_asal_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('penyelia_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mesej_eskalasi_id')->nullable()->constrained('ntf_mesej')->nullOnDelete();
            $table->timestamp('tarikh_eskalasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ntf_eskalasi_log');
        Schema::dropIfExists('ntf_keutamaan_pengguna');
        Schema::dropIfExists('ntf_status_pengguna');
        Schema::dropIfExists('ntf_penghantaran');
        Schema::dropIfExists('ntf_mesej');
        Schema::dropIfExists('ntf_penerima_peraturan');
        Schema::dropIfExists('ntf_templat');
        Schema::dropIfExists('ntf_jenis');
    }
};
