<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-WFE (SRS Modul 14). Definitions are versioned and never deleted
 * (SRS-WFE-12): a definition row is one version of one flow; its stages,
 * approvers, transitions/conditions, escalations and follow-ups hang off it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_definisi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_aliran', 100)->index();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->string('modul', 20);
            $table->string('jenis_transaksi', 100);
            $table->unsignedInteger('no_versi');
            // draf | menunggu_pengaktifan | aktif | arkib
            $table->string('status', 30)->default('draf');
            $table->boolean('templat')->default(false);
            $table->timestamp('tarikh_berkuat_kuasa')->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            // manual | ai (SRS-WFE-10/24, deferred to G5)
            $table->string('kaedah_cipta', 20)->default('manual');
            $table->foreignId('id_asal')->nullable()->constrained('wf_definisi')->nullOnDelete();
            $table->foreignId('diminta_aktif_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diminta_aktif_pada')->nullable();
            $table->foreignId('diluluskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diluluskan_pada')->nullable();
            // Node positions etc. for the visual editor — presentation only.
            $table->jsonb('susun_atur')->nullable();
            $table->timestamps();

            $table->unique(['kod_aliran', 'no_versi']);
        });

        Schema::create('wf_peringkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_definisi')->constrained('wf_definisi')->cascadeOnDelete();
            $table->string('kod', 50); // stable key inside the definition (graph node id)
            $table->unsignedInteger('no_urutan');
            $table->string('nama_peringkat');
            // semua | mana_mana | kuorum (parallel approval inside a stage)
            $table->string('mod_kelulusan', 20)->default('mana_mana');
            $table->unsignedInteger('kuorum')->nullable();
            $table->unsignedInteger('sla_nilai')->nullable();
            $table->string('sla_unit', 20)->default('hari_bekerja'); // hari_bekerja | jam
            $table->boolean('perlu_mfa')->default(false);
            $table->boolean('perlu_justifikasi')->default(false); // also for "lulus"
            $table->timestamps();

            $table->unique(['id_definisi', 'kod']);
        });

        Schema::create('wf_pelulus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_peringkat')->constrained('wf_peringkat')->cascadeOnDelete();
            $table->string('jenis', 20); // peranan | jawatan | individu | kumpulan | penyelia_pemohon
            $table->string('nilai', 100)->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('wf_peralihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_definisi')->constrained('wf_definisi')->cascadeOnDelete();
            $table->string('dari', 50); // "mula" or a stage kod
            $table->string('ke', 50);   // "tamat" or a stage kod
            $table->unsignedInteger('keutamaan')->default(0);
            $table->string('logik', 10)->default('semua'); // semua (AND) | mana_mana (OR)
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('wf_syarat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_peralihan')->constrained('wf_peralihan')->cascadeOnDelete();
            $table->string('medan', 100);
            $table->string('operator', 20);
            $table->jsonb('nilai')->nullable();
            $table->timestamps();
        });

        Schema::create('wf_eskalasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_peringkat')->constrained('wf_peringkat')->cascadeOnDelete();
            $table->unsignedInteger('aras');
            // Time after the SLA due date at which this level fires.
            $table->unsignedInteger('tempoh_nilai')->default(0);
            $table->string('tempoh_unit', 20)->default('hari_bekerja');
            $table->string('jenis_penerima', 20); // peranan | jawatan | individu | kumpulan | penyelia
            $table->string('nilai_penerima', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wf_susulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_definisi')->constrained('wf_definisi')->cascadeOnDelete();
            $table->string('bila', 20)->default('lulus'); // lulus | tolak | semua
            $table->string('tajuk');
            $table->string('jenis_penerima', 20); // peranan | jawatan | individu | kumpulan | pemohon
            $table->string('nilai_penerima', 100)->nullable();
            $table->unsignedInteger('sla_nilai')->nullable();
            $table->string('sla_unit', 20)->default('hari_bekerja');
            $table->timestamps();
        });

        Schema::create('wf_definisi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_definisi')->constrained('wf_definisi')->cascadeOnDelete();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tindakan', 40); // cipta | kemas_kini | salin | versi_baharu | mohon_aktif | aktif | tolak_aktif | arkib
            $table->string('kaedah', 30);   // borang | editor_visual | api | salin | ai | sistem
            $table->text('arahan')->nullable(); // AI prompt (G5) or free-text note
            $table->jsonb('butiran')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('wf_kumpulan', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 50)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('wf_kumpulan_ahli', function (Blueprint $table) {
            $table->foreignId('id_kumpulan')->constrained('wf_kumpulan')->cascadeOnDelete();
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->primary(['id_kumpulan', 'id_pengguna']);
        });

        Schema::create('wf_instans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('id_definisi')->constrained('wf_definisi');
            $table->unsignedInteger('no_versi_definisi');
            $table->string('kod_aliran', 100)->index();
            $table->string('modul', 20);
            $table->string('jenis_subjek', 100);
            $table->string('id_rekod', 100);
            $table->string('tajuk')->nullable();
            $table->jsonb('konteks')->nullable();
            $table->string('kod_daerah', 20)->nullable()->index();
            $table->string('pautan')->nullable();
            $table->foreignId('id_pemohon')->constrained('users');
            // berjalan | lulus | tolak | ditarik_balik | dibatalkan
            $table->string('status', 20)->default('berjalan')->index();
            $table->string('keadaan', 30)->nullable(); // menunggu_pemohon after a return at the first stage
            $table->foreignId('peringkat_semasa')->nullable()->constrained('wf_peringkat')->nullOnDelete();
            $table->unsignedInteger('kitaran')->default(0);
            $table->jsonb('laluan')->nullable(); // stack of stage ids entered (for "kembali" and SoD)
            $table->timestamp('tarikh_mula');
            $table->timestamp('tarikh_tamat')->nullable();
            $table->text('ulasan_keputusan')->nullable();
            $table->timestamps();

            $table->index(['jenis_subjek', 'id_rekod']);
        });

        Schema::create('wf_tugasan', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_instans');
            $table->foreign('id_instans')->references('id')->on('wf_instans')->cascadeOnDelete();
            $table->foreignId('id_peringkat')->nullable()->constrained('wf_peringkat');
            $table->string('jenis', 20)->default('kelulusan'); // kelulusan | susulan
            $table->string('tajuk')->nullable();
            $table->unsignedInteger('kitaran')->default(0);
            $table->foreignId('id_pelulus')->nullable()->constrained('wf_pelulus')->nullOnDelete();
            $table->string('jenis_penerima', 20);
            $table->string('nilai_penerima', 100)->nullable();
            $table->string('penerima_kunci', 130)->index(); // e.g. "peranan:pegawai_hq", "individu:12"
            $table->foreignId('id_pelulus_asal')->nullable()->constrained('users')->nullOnDelete(); // delegated from
            // menunggu | ditangguh | selesai | dibatalkan
            $table->string('status', 20)->default('menunggu')->index();
            $table->string('keputusan', 20)->nullable();
            $table->timestamp('tarikh_akhir_sla')->nullable();
            $table->timestamp('ditangguh_hingga')->nullable();
            $table->unsignedInteger('aras_eskalasi')->default(0);
            $table->timestamp('amaran_sla_pada')->nullable();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->timestamps();
        });

        // SRS-WFE-16: full history; holds the WF_TINDAKAN columns (actor,
        // role at the time, action, justification, IP) plus engine events.
        Schema::create('wf_sejarah', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_instans');
            $table->foreign('id_instans')->references('id')->on('wf_instans')->cascadeOnDelete();
            $table->foreignId('id_tugasan')->nullable()->constrained('wf_tugasan')->nullOnDelete();
            $table->foreignId('id_peringkat')->nullable()->constrained('wf_peringkat')->nullOnDelete();
            $table->string('nama_peringkat')->nullable();
            $table->string('peristiwa', 30); // mula | tugasan | lulus | tolak | kembali | tangguh | sambung | eskalasi | tarik_balik | hantar_semula | pindah_versi | selesai | susulan
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_bagi_pihak')->nullable()->constrained('users')->nullOnDelete(); // delegated: the original approver
            $table->jsonb('peranan_semasa')->nullable();
            $table->text('justifikasi')->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->timestamp('mfa_pada')->nullable();
            $table->jsonb('butiran')->nullable();
            $table->timestamp('tarikh_masa')->useCurrent();

            $table->index(['id_instans', 'id']);
        });

        Schema::create('wf_eskalasi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugasan')->constrained('wf_tugasan')->cascadeOnDelete();
            $table->unsignedInteger('aras');
            $table->jsonb('penerima');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('wf_pemangku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_pemangku')->constrained('users')->cascadeOnDelete();
            $table->date('tarikh_mula');
            $table->date('tarikh_tamat');
            $table->string('sebab')->nullable();
            $table->foreignId('dicipta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('wf_pindah_versi', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_instans');
            $table->foreign('id_instans')->references('id')->on('wf_instans')->cascadeOnDelete();
            $table->foreignId('id_definisi_asal')->constrained('wf_definisi');
            $table->foreignId('id_definisi_baru')->constrained('wf_definisi');
            $table->string('sebab');
            $table->string('status', 20)->default('menunggu'); // menunggu | lulus | tolak
            $table->foreignId('diminta_oleh')->constrained('users');
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();
        });

        // SRS-WFE-06: admin-maintained public-holiday calendar.
        Schema::create('wf_cuti_umum', function (Blueprint $table) {
            $table->id();
            $table->date('tarikh');
            $table->string('nama');
            $table->string('negeri', 30)->default('selangor');
            $table->string('jenis', 20)->default('persekutuan'); // persekutuan | negeri | ganti | khas
            $table->string('sumber')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tarikh', 'negeri', 'nama']);
        });
    }

    public function down(): void
    {
        foreach (['wf_cuti_umum', 'wf_pindah_versi', 'wf_pemangku', 'wf_eskalasi_log', 'wf_sejarah', 'wf_tugasan', 'wf_instans',
            'wf_kumpulan_ahli', 'wf_kumpulan', 'wf_definisi_log', 'wf_susulan', 'wf_eskalasi', 'wf_syarat', 'wf_peralihan',
            'wf_pelulus', 'wf_peringkat', 'wf_definisi'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
