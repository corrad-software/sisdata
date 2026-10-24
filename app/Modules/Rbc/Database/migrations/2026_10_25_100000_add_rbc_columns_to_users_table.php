<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PENGGUNA (SRS Modul 18 entity) — extends Laravel's users table rather
     * than a parallel table. The legacy `role` / `is_active` columns stay for
     * the transition; authorisation comes from pengguna_peranan only.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SRS-RBC-15: aktif | gantung | nyahaktif | tamat_tempoh
            $table->string('status_akaun', 20)->default('aktif')->index();
            // SRS-RBC-11: dalaman (password in SISDATA) | sso (SSO MAIS only)
            $table->string('jenis_pengesahan', 10)->default('dalaman');
            $table->string('no_pekerja', 50)->nullable();
            $table->string('bahagian', 100)->nullable()->index();
            $table->string('unit', 100)->nullable();
            $table->string('jawatan', 150)->nullable();
            $table->date('tarikh_mula_capaian')->nullable();
            $table->date('tarikh_tamat_capaian')->nullable();
            $table->timestamp('tarikh_log_masuk_akhir')->nullable();
            $table->timestamp('tarikh_diaktifkan_semula')->nullable();
            $table->boolean('mfa_didaftar')->default(false);
            // SRS-RBC-12
            $table->timestamp('kata_laluan_ditukar_pada')->nullable();
            $table->boolean('wajib_tukar_kata_laluan')->default(false);
            // SRS-RBC-13
            $table->unsignedSmallInteger('cubaan_gagal')->default(0);
            $table->timestamp('cubaan_gagal_pertama')->nullable();
            $table->timestamp('dikunci_hingga')->nullable();
        });

        // SRS-RBC-11: SSO-only accounts keep no password at all.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        DB::table('users')->where('is_active', false)->update(['status_akaun' => 'nyahaktif']);

        // Rollout grace: the 90-day password expiry and the inactivity
        // suspension count from today for existing accounts, not from their
        // creation date (otherwise every older account would be locked out).
        DB::table('users')->update(['tarikh_diaktifkan_semula' => now()]);
        DB::table('users')->whereNotNull('password')->update(['kata_laluan_ditukar_pada' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status_akaun']);
            $table->dropIndex(['bahagian']);
            $table->dropColumn([
                'status_akaun', 'jenis_pengesahan', 'no_pekerja', 'bahagian', 'unit', 'jawatan',
                'tarikh_mula_capaian', 'tarikh_tamat_capaian', 'tarikh_log_masuk_akhir', 'tarikh_diaktifkan_semula',
                'mfa_didaftar', 'kata_laluan_ditukar_pada', 'wajib_tukar_kata_laluan',
                'cubaan_gagal', 'cubaan_gagal_pertama', 'dikunci_hingga',
            ]);
        });
    }
};
