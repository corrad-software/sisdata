<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PWS — SISDATA side of Portal Hartanah Wakaf Selangor: the public API
 * gateway's clients and tokens, integration log, idempotency store, the
 * submission outbox (reference numbers are issued here, SRS-PWS-08), payment
 * sessions, governed CMS content (AI drafts need approval, SRS-PWS-04),
 * published statistics and portal analytics (SRS-PWS-27).
 *
 * No master record lives here (SRS-PWS-20): applications become PER cases /
 * PJK applications, payments are PJK ledger transactions. Personal data in a
 * submission is encrypted and cleared once it has been delivered.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SRS-PWS-23 / SRS-INT-05/13: scoped API clients, rotation, IP allowlist.
        Schema::create('pws_klien_api', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('id_klien', 64)->unique();
            $table->string('cincangan_rahsia', 128);
            $table->string('cincangan_rahsia_lama', 128)->nullable();
            $table->timestamp('rahsia_lama_luput')->nullable();
            $table->json('skop');
            $table->json('ip_dibenarkan')->nullable(); // null/empty = any (dev only)
            $table->unsignedInteger('had_kadar_minit')->default(120);
            $table->date('tarikh_luput')->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | dibatalkan
            $table->timestamp('tarikh_putaran')->nullable();
            $table->timestamp('terakhir_digunakan')->nullable();
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pws_token_akses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klien_id')->constrained('pws_klien_api')->cascadeOnDelete();
            $table->string('cincangan_token', 64)->unique();
            $table->timestamp('tamat_pada')->index();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // SRS-PWS-22 entity pws_log_integrasi: inbound / outbound with payload summary.
        Schema::create('pws_log_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('arah', 10); // masuk | keluar
            $table->string('endpoint', 200);
            $table->string('kaedah', 10);
            $table->foreignId('klien_id')->nullable()->constrained('pws_klien_api')->nullOnDelete();
            $table->string('nombor_rujukan', 80)->nullable()->index();
            $table->json('ringkasan_payload')->nullable();
            $table->unsignedSmallInteger('kod_status')->nullable();
            $table->unsignedSmallInteger('bilangan_cuba_semula')->default(0);
            $table->string('mesej_ralat', 500)->nullable();
            $table->unsignedInteger('tempoh_ms')->nullable();
            $table->unsignedInteger('saiz_bait')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('id_permintaan', 64)->nullable();
            $table->timestamp('cap_masa')->useCurrent()->index();
        });

        Schema::create('pws_idempotensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klien_id')->constrained('pws_klien_api')->cascadeOnDelete();
            $table->string('kunci', 100);
            $table->string('endpoint', 120);
            $table->string('cincangan_permintaan', 64);
            $table->unsignedSmallInteger('kod_status')->nullable(); // null = in progress
            $table->json('respons')->nullable();
            $table->timestamp('tamat_pada')->index();
            $table->timestamps();
            $table->unique(['klien_id', 'kunci']);
        });

        // Outbox + status mirror of every application sent from the portal.
        Schema::create('pws_permohonan', function (Blueprint $table) {
            $table->id();
            $table->string('no_rujukan', 30)->unique(); // PHWS-YYYY-XXXXXXX, issued by SISDATA
            $table->string('jenis', 20); // wakaf | sewaan
            $table->foreignId('klien_id')->nullable()->constrained('pws_klien_api')->nullOnDelete();
            $table->text('kandungan')->nullable(); // encrypted JSON, cleared after delivery
            $table->string('cincangan_emel', 64)->nullable(); // status lookup check, no plain e-mail
            $table->string('kod_daerah', 4)->nullable();
            $table->string('kategori', 40)->nullable(); // premis category / jenis wakaf (reports)
            $table->string('status_penghantaran', 20)->default('menunggu'); // menunggu | dihantar | diterima | gagal
            $table->unsignedSmallInteger('bilangan_cuba')->default(0);
            $table->string('ralat_terakhir', 500)->nullable();
            $table->string('rujukan_sisdata', 40)->nullable(); // PER no_kes / PJK no_permohonan
            $table->string('status', 40)->default('dihantar');
            $table->string('status_nama', 120)->nullable();
            $table->json('sejarah_status')->nullable();
            $table->timestamp('tarikh_status')->nullable()->index();
            $table->timestamps();
            $table->index(['jenis', 'status']);
        });

        // Entity pws_sesi_bayaran (SRS-PWS-10..16): only references, never card data.
        Schema::create('pws_sesi_bayaran', function (Blueprint $table) {
            $table->id();
            $table->string('no_rujukan', 40)->unique(); // PJK gateway transaction reference
            $table->string('no_kontrak', 40)->index();
            $table->foreignId('klien_id')->nullable()->constrained('pws_klien_api')->nullOnDelete();
            $table->decimal('jumlah', 14, 2);
            $table->string('mata_wang', 3)->default('MYR');
            $table->string('saluran_bayaran', 20);
            $table->string('rujukan_gateway', 80)->nullable();
            $table->string('url_bayaran', 500)->nullable();
            $table->string('status', 20)->default('dimulakan'); // dimulakan | menunggu | berjaya | gagal | dibatalkan | tamat_tempoh
            $table->timestamp('cap_masa_mula')->nullable();
            $table->timestamp('cap_masa_pengesahan')->nullable();
            $table->string('disahkan_melalui', 30)->nullable(); // pertanyaan_status (server-side only)
            $table->timestamps();
        });

        // Entity pws_kandungan_cms (SRS-PWS-04): governed portal content.
        Schema::create('pws_kandungan_cms', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20); // artikel | hebahan | soal_jawab | promosi | projek | halaman
            $table->string('slug', 160)->unique();
            $table->string('tajuk_bm', 250);
            $table->string('tajuk_bi', 250)->nullable();
            $table->text('kandungan_bm');
            $table->text('kandungan_bi')->nullable();
            $table->string('ringkasan', 500)->nullable();
            $table->unsignedTinyInteger('kemajuan_peratus')->nullable(); // projek
            $table->string('lokasi', 200)->nullable(); // projek
            $table->string('status_terbitan', 20)->default('draf'); // draf | dalam_semakan | diterbitkan | ditolak | ditarik
            $table->boolean('dijana_ai')->default(false);
            $table->text('prompt_ai')->nullable();
            $table->string('model_ai', 80)->nullable();
            $table->foreignId('id_pengarang')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_penyemak')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aliran', 64)->nullable();
            $table->string('catatan_semakan', 1000)->nullable();
            $table->timestamp('tarikh_terbit')->nullable();
            $table->timestamps();
            $table->index(['jenis', 'status_terbitan']);
        });

        // Aggregate public statistics, published only after approval.
        Schema::create('pws_statistik_terbitan', function (Blueprint $table) {
            $table->id();
            $table->string('tajuk', 200);
            $table->string('tempoh', 20); // e.g. 2026, 2026-10
            $table->json('data');
            $table->string('status', 20)->default('draf'); // draf | dalam_semakan | diterbitkan | ditolak | ditarik
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diluluskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_aliran', 64)->nullable();
            $table->timestamp('tarikh_terbit')->nullable();
            $table->timestamps();
        });

        // Entity pws_analitik_lawatan (SRS-PWS-27), pushed by the portal daily.
        Schema::create('pws_analitik_lawatan', function (Blueprint $table) {
            $table->id();
            $table->string('halaman', 200);
            $table->string('sumber_rujukan', 120)->default('terus');
            $table->unsignedInteger('bilangan_lawatan')->default(0);
            $table->unsignedInteger('tempoh_purata')->nullable(); // seconds
            $table->unsignedInteger('borang_dimulakan')->default(0);
            $table->unsignedInteger('borang_dihantar')->default(0);
            $table->date('tarikh');
            $table->timestamps();
            $table->unique(['tarikh', 'halaman', 'sumber_rujukan']);
        });
    }

    public function down(): void
    {
        foreach (['pws_analitik_lawatan', 'pws_statistik_terbitan', 'pws_kandungan_cms', 'pws_sesi_bayaran', 'pws_permohonan', 'pws_idempotensi', 'pws_log_integrasi', 'pws_token_akses', 'pws_klien_api'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
