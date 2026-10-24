<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-ADM slice c: job scheduler (SRS-ADM-06..10). ADM_JOB / ADM_JOB_LARIAN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adm_tugasan_berjadual', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 80)->unique();
            $table->string('nama', 200);
            $table->text('keterangan')->nullable();
            // SRS-ADM-07: pengemaskinian_data | notifikasi | laporan | pengarkiban | integrasi | penyelenggaraan
            $table->string('kategori', 30);
            $table->string('arahan', 200)->comment('artisan command name');
            $table->json('argumen')->nullable();
            $table->string('jenis_jadual', 10)->default('cron'); // cron | selang
            $table->string('ungkapan_cron', 100)->nullable();
            $table->unsignedInteger('selang_minit')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedTinyInteger('cubaan_semula')->default(0);
            $table->unsignedInteger('tempoh_tamat_masa_saat')->default(3600);
            $table->unsignedInteger('toleransi_lewat_minit')->nullable();
            // adm = defined here; sistem = registered in Laravel's scheduler by a module and adopted
            $table->string('sumber', 10)->default('adm');
            $table->string('kunci_sistem', 200)->nullable()->unique();
            $table->timestamp('terakhir_mula_pada')->nullable();
            $table->string('terakhir_status', 20)->nullable();
            $table->text('justifikasi')->nullable();
            $table->foreignId('dikemas_kini_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adm_tugasan_larian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugasan')->constrained('adm_tugasan_berjadual')->cascadeOnDelete();
            $table->string('pencetus', 10)->default('jadual'); // jadual | manual
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('cubaan')->default(1);
            $table->timestamp('tarikh_mula')->nullable();
            $table->timestamp('tarikh_tamat')->nullable();
            $table->decimal('tempoh_saat', 10, 3)->nullable();
            // beratur | berjalan | berjaya | gagal | dilangkau
            $table->string('status', 15);
            $table->unsignedBigInteger('bilangan_rekod')->nullable();
            $table->text('mesej_ralat')->nullable();
            $table->text('output')->nullable();
            $table->string('id_korelasi', 64)->nullable();
            $table->timestamps();

            $table->index(['id_tugasan', 'created_at']);
            $table->index(['status', 'tarikh_mula']);
        });

        // ADM's own housekeeping jobs, visible and switchable in the UI like any other.
        $now = now();
        DB::table('adm_tugasan_berjadual')->insertOrIgnore([
            [
                'kod' => 'adm_semak_kesihatan', 'nama' => 'Semakan kesihatan sistem', 'kategori' => 'penyelenggaraan',
                'arahan' => 'adm:semak-kesihatan', 'jenis_jadual' => 'selang', 'selang_minit' => 5, 'ungkapan_cron' => null,
                'aktif' => true, 'cubaan_semula' => 0, 'tempoh_tamat_masa_saat' => 300, 'sumber' => 'adm', 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'kod' => 'adm_semak_tugasan', 'nama' => 'Semakan tugasan terlepas jadual', 'kategori' => 'penyelenggaraan',
                'arahan' => 'adm:semak-tugasan', 'jenis_jadual' => 'cron', 'selang_minit' => null, 'ungkapan_cron' => '*/10 * * * *',
                'aktif' => true, 'cubaan_semula' => 0, 'tempoh_tamat_masa_saat' => 300, 'sumber' => 'adm', 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'kod' => 'adm_pangkas_log', 'nama' => 'Pengekalan log (pangkas log lama)', 'kategori' => 'pengarkiban',
                'arahan' => 'adm:pangkas-log', 'jenis_jadual' => 'cron', 'selang_minit' => null, 'ungkapan_cron' => '30 2 * * *',
                'aktif' => true, 'cubaan_semula' => 2, 'tempoh_tamat_masa_saat' => 3600, 'sumber' => 'adm', 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('adm_tugasan_larian');
        Schema::dropIfExists('adm_tugasan_berjadual');
    }
};
