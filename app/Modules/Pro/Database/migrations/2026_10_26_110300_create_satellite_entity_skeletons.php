<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-PRO-06 / PLAN-SISDATA §6 PRO-a: the ten core RFP entities exist as
 * separate tables from G1 so foreign keys are stable. hak_milik (MOD-HKM),
 * pemerolehan (MOD-PER), dokumen (MOD-DMS) and data_spatial are created by
 * their owners; the remaining satellites are created here as SKELETONS with
 * the §4.1 critical fields only. Their owning modules (PJK, CKI, ADU, KES,
 * and the valuation work) extend them with their own migrations later —
 * they must not re-create them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('penilaian')) {
            Schema::create('penilaian', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lot_id')->constrained('lot_tanah');
                $table->date('tarikh_penilaian');
                $table->decimal('nilai_pasaran', 16, 2)->nullable();
                $table->decimal('nilai_setara', 16, 2)->nullable();
                $table->string('kaedah', 50)->nullable();
                $table->string('penilai', 150)->nullable();
                $table->unsignedInteger('versi')->default(1);
                $table->string('klasifikasi', 10)->default('rahsia');
                $table->timestamps();
                $table->softDeletes();
                $table->index('lot_id');
            });
        }

        if (! Schema::hasTable('kontrak_pajakan')) {
            Schema::create('kontrak_pajakan', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lot_id')->constrained('lot_tanah');
                $table->string('penyewa', 200)->nullable();
                $table->date('tarikh_mula')->nullable();
                $table->date('tarikh_tamat')->nullable();
                $table->decimal('kadar', 14, 2)->nullable();
                $table->decimal('deposit', 14, 2)->nullable();
                $table->text('terma')->nullable();
                $table->string('status', 30)->default('draf');
                $table->string('klasifikasi', 10)->default('sulit');
                $table->timestamps();
                $table->softDeletes();
                $table->index('lot_id');
            });
        }

        if (! Schema::hasTable('cukai')) {
            Schema::create('cukai', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lot_id')->constrained('lot_tanah');
                $table->string('jenis', 30)->comment('tanah | petak | taksiran_pbt');
                $table->string('no_bil', 50)->nullable();
                $table->decimal('jumlah', 14, 2)->nullable();
                $table->date('tarikh_akhir')->nullable();
                $table->string('status_bayaran', 30)->default('belum_bayar');
                $table->string('kaedah_bayaran', 30)->nullable();
                $table->string('klasifikasi', 10)->default('dalaman');
                $table->timestamps();
                $table->softDeletes();
                $table->index('lot_id');
            });
        }

        if (! Schema::hasTable('aduan')) {
            Schema::create('aduan', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lot_id')->nullable()->constrained('lot_tanah');
                $table->string('kategori', 30)->nullable();
                $table->date('tarikh_aduan')->nullable();
                $table->string('status', 30)->default('baharu');
                $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
                $table->text('tindakan_susulan')->nullable();
                $table->string('klasifikasi', 10)->default('dalaman');
                $table->timestamps();
                $table->softDeletes();
                $table->index('lot_id');
            });
            DB::statement('ALTER TABLE aduan ADD COLUMN koordinat geometry(Point, 4326)');
        }

        if (! Schema::hasTable('kes_mahkamah')) {
            Schema::create('kes_mahkamah', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('no_kes', 80);
                $table->string('jenis', 50)->nullable();
                $table->string('mahkamah', 150)->nullable();
                $table->string('status', 30)->default('aktif');
                $table->decimal('kos_guaman', 14, 2)->nullable();
                $table->string('klasifikasi', 10)->default('rahsia');
                $table->timestamps();
                $table->softDeletes();
            });
            // N : N lot (SRS §4.1)
            Schema::create('kes_mahkamah_lot', function (Blueprint $table) {
                $table->foreignUuid('kes_mahkamah_id')->constrained('kes_mahkamah')->cascadeOnDelete();
                $table->foreignUuid('lot_id')->constrained('lot_tanah');
                $table->primary(['kes_mahkamah_id', 'lot_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['kes_mahkamah_lot', 'kes_mahkamah', 'aduan', 'cukai', 'kontrak_pajakan', 'penilaian'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
