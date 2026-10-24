<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-AUD store (SRS Modul 19 entities). audit_rekod + audit_perubahan_medan
 * hold the hash-chained trail; audit_arkib holds archived records (same
 * columns, field changes embedded as JSON) so the chain stays verifiable and
 * searchable after archiving. Append-only enforcement is in the next migration.
 *
 * meta / peranan_semasa are `json` (not jsonb) on purpose: json keeps the exact
 * text that was hashed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_rekod', function (Blueprint $table) {
            $table->id();
            $this->recordColumns($table);
        });

        Schema::create('audit_perubahan_medan', function (Blueprint $table) {
            $table->id();
            // No FK: Postgres' FK check takes FOR KEY SHARE on audit_rekod, which
            // needs the UPDATE privilege the app role no longer has. Integrity
            // of the link is covered by the hash chain (changes are hashed in).
            $table->unsignedBigInteger('id_audit');
            $table->string('nama_medan', 150);
            $table->text('nilai_asal')->nullable();
            $table->text('nilai_baharu')->nullable();
            $table->boolean('sensitif_ditapis')->default(false);
            $table->index('id_audit');
            $table->index('nama_medan');
        });

        Schema::create('audit_arkib', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $this->recordColumns($table);
            $table->json('perubahan')->nullable();
            $table->timestampTz('diarkib_pada', 6);
            $table->string('id_kelompok_arkib', 64);
        });

        Schema::create('audit_eksport', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_pengguna')->nullable()->index();
            $table->string('pengecam_pengguna')->nullable();
            $table->json('kriteria_carian');
            $table->string('format', 10);
            $table->unsignedInteger('bilangan_rekod');
            $table->char('checksum', 64);
            $table->char('checksum_data', 64);
            $table->string('nama_fail');
            $table->unsignedBigInteger('saiz_bait');
            $table->timestampTz('tarikh_masa_utc', 6)->index();
            $table->timestamp('tarikh_masa_tempatan', 6);
        });

        Schema::create('audit_integriti_semakan', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('dimulakan_pada', 6);
            $table->timestampTz('selesai_pada', 6);
            $table->string('status', 10); // lulus | gagal
            $table->unsignedBigInteger('bilangan_disemak');
            $table->unsignedBigInteger('no_urutan_terakhir')->nullable();
            $table->unsignedBigInteger('no_urutan_rosak')->nullable();
            $table->text('butiran')->nullable();
            $table->string('dicetuskan_oleh', 20); // jadual | manual
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->index(['status', 'dimulakan_pada']);
        });

        Schema::create('audit_retensi_polisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tempoh_dalam_talian_bulan');
            $table->unsignedSmallInteger('tempoh_arkib_tahun')->nullable(); // null = simpan selamanya
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE audit_retensi_polisi ADD CONSTRAINT audit_retensi_minimum CHECK (tempoh_dalam_talian_bulan >= 24)');

        DB::table('audit_retensi_polisi')->insert([
            'tempoh_dalam_talian_bulan' => 24,
            'tempoh_arkib_tahun' => null,
            'catatan' => 'Lalai SRS-AUD-20: sekurang-kurangnya 24 bulan dalam talian.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('no_urutan')->unique();
        $table->char('cincangan_sebelum', 64);
        $table->char('cincangan_rekod', 64)->unique();
        $table->timestampTz('tarikh_masa_utc', 6);
        $table->timestamp('tarikh_masa_tempatan', 6);
        $table->unsignedBigInteger('id_pengguna')->nullable();
        $table->string('pengecam_pengguna')->nullable();
        $table->json('peranan_semasa');
        $table->string('id_sesi', 80)->nullable();
        $table->string('alamat_ip', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->string('jenis_peranti', 20);
        $table->string('lokasi_anggaran')->nullable();
        $table->string('saluran_capaian', 20);
        $table->string('modul', 20);
        $table->string('nama_entiti', 100)->nullable();
        $table->string('id_rekod', 100)->nullable();
        $table->string('jenis_tindakan', 40);
        $table->string('status_transaksi', 20);
        $table->text('sebab_perubahan')->nullable();
        $table->string('id_korelasi', 64)->nullable();
        $table->string('klasifikasi', 20)->nullable();
        $table->json('meta')->nullable();

        $table->index('tarikh_masa_utc');
        $table->index('id_pengguna');
        $table->index(['modul', 'jenis_tindakan']);
        $table->index(['nama_entiti', 'id_rekod']);
        $table->index('alamat_ip');
        $table->index('status_transaksi');
        $table->index('id_korelasi');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_retensi_polisi');
        Schema::dropIfExists('audit_integriti_semakan');
        Schema::dropIfExists('audit_eksport');
        Schema::dropIfExists('audit_arkib');
        Schema::dropIfExists('audit_perubahan_medan');
        Schema::dropIfExists('audit_rekod');
    }
};
