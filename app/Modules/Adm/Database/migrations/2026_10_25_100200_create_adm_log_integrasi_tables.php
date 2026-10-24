<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-ADM slices d + f: error log (SRS-ADM-11), integration log (SRS-ADM-14),
 * health snapshots (SRS-ADM-16/17), integration settings (SRS-ADM-04) and the
 * encrypted secret store (SRS-ADM-28).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adm_log_ralat', function (Blueprint $table) {
            $table->id();
            $table->string('id_korelasi', 64)->nullable()->index();
            $table->timestamp('tarikh_masa')->useCurrent()->index();
            $table->string('aras_keterukan', 12)->index();
            $table->string('saluran', 30)->default('aplikasi');
            $table->string('modul', 20)->nullable()->index();
            $table->string('kod_ralat', 120)->nullable()->index();
            $table->text('mesej');
            $table->text('jejak_tindanan')->nullable();
            $table->unsignedBigInteger('id_pengguna')->nullable()->index();
            $table->string('alamat_ip', 45)->nullable();
            $table->json('konteks')->nullable();
        });

        Schema::create('adm_rahsia', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 120)->unique();
            $table->text('nilai_tersulit');
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 60)->unique();
            $table->string('nama', 200);
            $table->string('jenis', 30)->default('rest'); // rest | soap | sftp | fail | ogc
            $table->string('endpoint', 500);
            $table->string('id_rahsia', 120)->nullable()->comment('adm_rahsia.kunci — never the secret itself');
            $table->unsignedInteger('tempoh_tamat_masa')->default(30)->comment('seconds');
            $table->unsignedInteger('kadar_capaian')->nullable()->comment('max calls per minute');
            $table->string('jadual', 100)->nullable()->comment('cron expression for scheduled exchange');
            $table->string('status', 20)->default('aktif'); // aktif | tidak_aktif
            $table->unsignedInteger('kegagalan_berturut')->default(0);
            $table->timestamp('terakhir_berjaya_pada')->nullable();
            $table->timestamp('terakhir_gagal_pada')->nullable();
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_log_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_integrasi', 60)->index();
            $table->string('id_korelasi', 64)->nullable()->index();
            $table->timestamp('tarikh_masa')->useCurrent()->index();
            $table->string('arah', 10)->default('keluar'); // keluar | masuk
            $table->string('operasi', 120)->nullable();
            $table->boolean('berjaya');
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->unsignedInteger('tempoh_ms')->nullable();
            $table->unsignedInteger('bilangan_rekod')->nullable();
            $table->text('mesej')->nullable();
            $table->json('konteks')->nullable();
        });

        Schema::create('adm_kesihatan', function (Blueprint $table) {
            $table->id();
            $table->string('komponen', 40);
            $table->string('status', 10); // ok | amaran | gagal
            $table->unsignedInteger('masa_respons_ms')->nullable();
            $table->string('mesej', 500)->nullable();
            $table->json('butiran')->nullable();
            $table->timestamp('dicatat_pada')->useCurrent();

            $table->index(['komponen', 'dicatat_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adm_kesihatan');
        Schema::dropIfExists('adm_log_integrasi');
        Schema::dropIfExists('adm_integrasi');
        Schema::dropIfExists('adm_rahsia');
        Schema::dropIfExists('adm_log_ralat');
    }
};
