<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PJK · Pajakan dan Kontrak (SRS §8 Modul 5 entities). `kontrak_pajakan`
 * was created by MOD-PRO as a skeleton (SRS-PRO-06) — it is EXTENDED here,
 * never re-created. Money is DECIMAL(14,2) everywhere (no floats).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Gapless running numbers (contracts, receipts, letters, applications…).
        Schema::create('pjk_penomboran', function (Blueprint $table) {
            $table->string('jenis', 30);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nombor_akhir')->default(0);
            $table->primary(['jenis', 'tahun']);
        });

        // SRS-PJK-01: tenant (Sulit — personal / company data).
        Schema::create('penyewa', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_entiti', 20)->comment('individu | syarikat | pertubuhan | agensi');
            $table->string('nama', 200);
            $table->string('no_pengenalan', 20)->nullable();
            $table->string('no_pendaftaran_syarikat', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('poskod', 10)->nullable();
            $table->string('telefon', 30)->nullable();
            $table->string('emel', 150)->nullable();
            $table->string('klasifikasi', 10)->default('sulit');
            $table->boolean('sintetik')->default(false);
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('nama');
        });
        DB::statement('CREATE UNIQUE INDEX penyewa_no_pengenalan_unik ON penyewa (no_pengenalan) WHERE deleted_at IS NULL AND no_pengenalan IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX penyewa_no_syarikat_unik ON penyewa (no_pendaftaran_syarikat) WHERE deleted_at IS NULL AND no_pendaftaran_syarikat IS NOT NULL');

        // SRS-PJK-03/04: premises / land offered for rent, one lot → N premises.
        Schema::create('premis_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lot_id')->constrained('lot_tanah');
            $table->string('kod_premis', 40)->unique();
            $table->string('nama_premis', 200);
            $table->string('jenis_aset', 20)->comment('tanah | bangunan | unit_kedai | gerai | rumah');
            $table->string('kategori_hartanah', 30)->comment('premis | premis_perniagaan | premis_kediaman | tanah | bazar');
            $table->string('saluran_pemerolehan', 20)->nullable()->comment('wakaf | belian — auto from MOD-PER when null (SRS-PJK-23)');
            $table->decimal('keluasan', 14, 2)->nullable()->comment('m²');
            $table->text('alamat')->nullable();
            $table->string('status_premis', 20)->default('kosong')->comment('kosong | disewa | penyelenggaraan | penguatkuasaan | tidak_tersedia');
            $table->date('tarikh_status')->nullable();
            $table->decimal('kadar_tawaran', 14, 2)->nullable()->comment('RM sebulan');
            $table->string('kod_daerah', 4);
            $table->string('kod_mukim', 6);
            $table->string('bahagian', 20)->nullable()->comment('hartanah | wakaf | baitulmal (from the lot)');
            $table->boolean('papar_portal')->default(true);
            $table->text('keterangan_awam')->nullable();
            $table->string('klasifikasi', 10)->default('dalaman');
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['kod_daerah', 'kod_mukim']);
            $table->index(['status_premis', 'kategori_hartanah']);
            $table->index('lot_id');
        });
        DB::statement('ALTER TABLE premis_aset ADD COLUMN koordinat geometry(Point, 4326)');

        Schema::create('sejarah_status_premis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('premis_id')->constrained('premis_aset');
            $table->string('status_sebelum', 20)->nullable();
            $table->string('status_selepas', 20);
            $table->date('tarikh');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sebab', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('premis_id');
        });

        // SRS-PJK-01/02 — extend MOD-PRO's skeleton.
        Schema::table('kontrak_pajakan', function (Blueprint $table) {
            $table->string('no_kontrak', 40)->nullable()->unique();
            $table->foreignId('premis_id')->nullable()->constrained('premis_aset');
            $table->foreignId('penyewa_id')->nullable()->constrained('penyewa');
            $table->string('jenis_perjanjian', 20)->default('sewaan')->comment('sewaan | pajakan | lesen');
            $table->string('kategori_hartanah', 30)->nullable();
            $table->string('kitaran_caj', 20)->default('bulanan')->comment('bulanan | suku_tahunan | tahunan');
            $table->unsignedSmallInteger('hari_tempoh_bayar')->default(14);
            $table->decimal('nilai_kontrak', 16, 2)->nullable()->comment('kadar × bilangan kitaran (untuk aras kelulusan)');
            $table->string('kod_daerah', 4)->nullable();
            $table->string('kod_mukim', 6)->nullable();
            $table->string('bahagian', 20)->nullable();
            $table->date('tarikh_status')->nullable();
            $table->unsignedInteger('no_versi')->default(1);
            $table->unsignedBigInteger('id_versi_semasa')->nullable();
            $table->uuid('id_kontrak_asal')->nullable()->comment('pembaharuan: kontrak sebelumnya');
            $table->string('id_dokumen_perjanjian', 64)->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->string('sumber', 20)->default('kaunter')->comment('kaunter | portal');
            $table->unsignedBigInteger('permohonan_id')->nullable();
            $table->date('tarikh_penamatan')->nullable();
            $table->text('sebab_penamatan')->nullable();
            $table->foreignId('id_pendaftar')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->index(['kod_daerah', 'status']);
            $table->index('tarikh_tamat');
            $table->index('penyewa_id');
            $table->index('premis_id');
        });

        Schema::create('sejarah_status_kontrak', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('status_sebelum', 30)->nullable();
            $table->string('status_selepas', 30);
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('kontrak_id');
        });

        // SRS-PJK-05/07/10: amendment requests (renewal, rate review, terms, termination) → WFE.
        Schema::create('pindaan_kontrak', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('jenis_pindaan', 20)->comment('pembaharuan | semakan_kadar | pindaan_terma | penamatan');
            $table->json('data_cadangan');
            $table->json('data_semasa');
            $table->text('justifikasi');
            $table->json('semakan_kadar')->nullable()->comment('SRS-PJK-07 snapshot shown to approvers');
            $table->string('id_dokumen_selepas', 64)->nullable();
            $table->string('status', 20)->default('menunggu')->comment('menunggu | lulus | tolak | ditarik_balik');
            $table->foreignId('id_pemohon')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->text('ulasan')->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->uuid('id_kontrak_baharu')->nullable();
            $table->timestamps();
            $table->index(['kontrak_id', 'status']);
        });

        // SRS-PJK-10: contract versions (append-only).
        Schema::create('versi_kontrak', function (Blueprint $table) {
            $table->id('id_versi');
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('no_kontrak', 40);
            $table->unsignedInteger('no_versi');
            $table->string('jenis_pindaan', 20)->comment('asal | pembaharuan | semakan_kadar | pindaan_terma | penamatan');
            $table->date('tarikh_pindaan');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justifikasi')->nullable();
            $table->json('data_sebelum')->nullable();
            $table->json('data_selepas');
            $table->string('id_dokumen_sebelum', 64)->nullable();
            $table->string('id_dokumen_selepas', 64)->nullable();
            $table->unsignedBigInteger('pindaan_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['kontrak_id', 'no_versi']);
        });

        // SRS-PJK-12: charge schedule.
        Schema::create('jadual_caj', function (Blueprint $table) {
            $table->id('id');
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('no_caj', 40)->unique();
            $table->string('kod_caj', 20)->comment('sewa | deposit | penalti | lain');
            $table->string('tempoh', 20)->comment('YYYY-MM, YYYY-Qn, YYYY or "deposit"');
            $table->date('tarikh_caj');
            $table->date('tarikh_akhir');
            $table->decimal('amaun', 14, 2);
            $table->decimal('amaun_dibayar', 14, 2)->default(0);
            $table->decimal('amaun_dikecualikan', 14, 2)->default(0);
            $table->string('status_caj', 20)->default('dijadualkan')->comment('dijadualkan | belum_bayar | separa | dibayar | dikecualikan | dibatalkan');
            $table->boolean('dikeluarkan')->default(false)->comment('posted to the ledger');
            $table->string('keterangan', 300)->nullable();
            $table->timestamps();
            $table->unique(['kontrak_id', 'kod_caj', 'tempoh']);
            $table->index(['status_caj', 'tarikh_akhir']);
        });

        // SRS-PJK-15/16: online payment transactions.
        Schema::create('transaksi_gateway', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rujukan', 40)->unique();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->json('caj_ids')->nullable();
            $table->string('saluran', 10)->comment('fpx | kad');
            $table->decimal('amaun', 14, 2);
            $table->string('status', 20)->default('menunggu')->comment('menunggu | berjaya | gagal | dibatalkan | tamat_masa');
            $table->timestamp('masa_mula');
            $table->timestamp('masa_pengesahan')->nullable();
            $table->string('kod_respons', 20)->nullable();
            $table->string('rujukan_gateway', 80)->nullable();
            $table->unsignedBigInteger('id_fail_penyelesaian')->nullable();
            $table->string('url_bayaran', 500)->nullable();
            $table->string('kunci_idempotensi', 100)->nullable()->unique();
            $table->unsignedSmallInteger('bil_semakan_status')->default(0);
            $table->timestamp('semakan_terakhir')->nullable();
            $table->string('disahkan_melalui', 20)->nullable()->comment('panggilan_balik | semakan_status');
            $table->string('nama_pembayar', 200)->nullable();
            $table->string('emel_pembayar', 150)->nullable();
            $table->string('sumber', 20)->default('pegawai')->comment('pegawai | portal');
            $table->foreignId('id_pemula')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'masa_mula']);
        });

        Schema::create('panggilan_balik_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('id_acara', 100)->nullable()->unique()->comment('idempotency: same event processed once');
            $table->uuid('transaksi_gateway_id')->nullable();
            $table->json('muatan');
            $table->boolean('tandatangan_sah');
            $table->string('keputusan', 30)->comment('diproses | pendua | ditolak | tidak_dikenali');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // SRS-PJK-14/19: every payment (online and offline).
        Schema::create('bayaran', function (Blueprint $table) {
            $table->id();
            $table->string('no_bayaran', 40)->unique();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('saluran', 20)->comment('fpx | kad | kaunter | eft | pindahan_bank | cek');
            $table->decimal('amaun', 14, 2);
            $table->date('tarikh_bayaran');
            $table->string('rujukan_luar', 80)->nullable()->comment('no. cek / rujukan bank / rujukan gateway');
            $table->string('id_dokumen_bukti', 64)->nullable();
            $table->json('agihan_manual')->nullable();
            $table->string('status', 30)->default('menunggu_pengesahan')->comment('menunggu_pengesahan | disahkan | ditolak | dibatalkan');
            $table->uuid('transaksi_gateway_id')->nullable()->unique();
            $table->string('nama_pembayar', 200)->nullable();
            $table->string('emel_pembayar', 150)->nullable();
            $table->foreignId('id_perekod')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pengesahan')->nullable();
            $table->string('catatan', 1000)->nullable();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['status', 'saluran']);
            $table->index('kontrak_id');
        });

        Schema::create('agihan_bayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bayaran_id')->constrained('bayaran');
            $table->foreignId('caj_id')->constrained('jadual_caj');
            $table->decimal('amaun', 14, 2);
            $table->boolean('dibalikkan')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index('caj_id');
        });

        // SRS-PJK-11: ledger (append-only; running balance; reversal by new rows only).
        Schema::create('lejar_transaksi', function (Blueprint $table) {
            $table->id('id');
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('no_transaksi', 40)->unique();
            $table->date('tarikh_transaksi');
            $table->string('jenis', 30)->comment('caj | bayaran | pelarasan_debit | pelarasan_kredit | pembatalan | pengecualian | tolakan_deposit');
            $table->string('kod_caj', 20)->nullable();
            $table->unsignedBigInteger('caj_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('kredit', 14, 2)->default(0);
            $table->decimal('baki_berjalan', 14, 2);
            $table->string('kaedah_bayaran', 20)->nullable();
            $table->string('no_resit', 40)->nullable();
            $table->unsignedBigInteger('bayaran_id')->nullable();
            $table->uuid('transaksi_gateway_id')->nullable();
            $table->unsignedBigInteger('id_transaksi_asal')->nullable()->comment('the row a reversal undoes');
            $table->unsignedBigInteger('pelarasan_id')->nullable();
            $table->string('status_pelarasan', 20)->default('tiada')->comment('tiada | diluluskan');
            $table->string('keterangan', 500)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kontrak_id', 'id']);
            $table->index('tarikh_transaksi');
        });
        // A charge is posted once; a payment / gateway transaction is credited once (SRS-PJK-16 no duplicate credit).
        DB::statement("CREATE UNIQUE INDEX lejar_caj_sekali ON lejar_transaksi (caj_id) WHERE jenis = 'caj'");
        DB::statement("CREATE UNIQUE INDEX lejar_bayaran_sekali ON lejar_transaksi (bayaran_id) WHERE jenis = 'bayaran'");
        DB::statement("CREATE UNIQUE INDEX lejar_gateway_sekali ON lejar_transaksi (transaksi_gateway_id) WHERE jenis = 'bayaran' AND transaksi_gateway_id IS NOT NULL");

        // SRS-PJK-28: adjustment / reversal / waiver requests → second officer via WFE.
        Schema::create('pelarasan_lejar', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('jenis', 20)->comment('pelarasan_debit | pelarasan_kredit | pembatalan | pengecualian');
            $table->unsignedBigInteger('lejar_id_sasaran')->nullable();
            $table->unsignedBigInteger('caj_id')->nullable();
            $table->decimal('amaun', 14, 2);
            $table->text('justifikasi');
            $table->string('status', 20)->default('menunggu')->comment('menunggu | lulus | tolak');
            $table->foreignId('id_pemohon')->constrained('users');
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_keputusan')->nullable();
            $table->text('ulasan')->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->timestamps();
            $table->index(['kontrak_id', 'status']);
        });

        // SRS-PJK-17: official receipts (serial, immutable; cancellation = credit receipt).
        Schema::create('resit', function (Blueprint $table) {
            $table->id();
            $table->string('no_resit', 40)->unique();
            $table->string('jenis', 20)->default('resit')->comment('resit | resit_kredit');
            $table->foreignId('bayaran_id')->constrained('bayaran');
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->decimal('amaun', 14, 2);
            $table->date('tarikh');
            $table->unsignedBigInteger('id_resit_asal')->nullable();
            $table->string('id_dokumen', 64)->nullable();
            $table->string('emel_penerima', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        // Delivery state lives outside the immutable receipt row.
        Schema::create('penghantaran_resit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resit_id')->constrained('resit');
            $table->string('saluran', 20)->default('emel');
            $table->string('status', 20)->comment('dihantar | gagal | tiada_emel');
            $table->string('mesej', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // SRS-PJK-13: deposits and securities, separate from rent.
        Schema::create('deposit_cagaran', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->string('jenis', 20)->default('deposit_sewa')->comment('deposit_sewa | deposit_utiliti | cagaran_bank');
            $table->decimal('amaun', 14, 2);
            $table->unsignedBigInteger('caj_id')->nullable();
            $table->date('tarikh_terima')->nullable();
            $table->string('status', 30)->default('belum_diterima')->comment('belum_diterima | dipegang | dalam_penyelarasan | dipulangkan | dilucut_hak');
            $table->decimal('amaun_tolakan', 14, 2)->default(0);
            $table->text('justifikasi_tolakan')->nullable();
            $table->decimal('amaun_dipulangkan', 14, 2)->nullable();
            $table->date('tarikh_pulangan')->nullable();
            $table->string('id_instans_aliran', 64)->nullable();
            $table->foreignId('id_pemohon_penyelarasan')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pelulus')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kontrak_id', 'status']);
        });

        // SRS-PJK-18/21: settlement files and reconciliation.
        Schema::create('fail_penyelesaian', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 20)->comment('gateway | grp | esewa');
            $table->date('tarikh_penyelesaian');
            $table->string('nama_fail', 200)->nullable();
            $table->unsignedInteger('bil_rekod')->default(0);
            $table->decimal('jumlah', 16, 2)->default(0);
            $table->json('rekod');
            $table->string('id_dokumen', 64)->nullable();
            $table->foreignId('id_pemuat_naik')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('rekonsiliasi_larian', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 20);
            $table->date('tarikh');
            $table->foreignId('fail_penyelesaian_id')->nullable()->constrained('fail_penyelesaian');
            $table->unsignedInteger('bil_padan')->default(0);
            $table->unsignedInteger('bil_sisihan')->default(0);
            $table->string('status', 20)->default('selesai')->comment('selesai | gagal');
            $table->string('mesej', 500)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sumber', 'tarikh']);
        });
        Schema::create('sisihan_rekonsiliasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('larian_id')->constrained('rekonsiliasi_larian');
            $table->string('jenis', 30)->comment('tiada_lejar | tiada_penyelesaian | beza_amaun | beza_status');
            $table->string('rujukan', 80);
            $table->uuid('kontrak_id')->nullable();
            $table->decimal('amaun_sistem', 14, 2)->nullable();
            $table->decimal('amaun_luar', 14, 2)->nullable();
            $table->string('status_tindakan', 20)->default('baharu')->comment('baharu | dalam_tindakan | selesai');
            $table->text('catatan')->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status_tindakan', 'jenis']);
        });

        // SRS-PJK-22..25: Dasar Agihan Manfaat (versioned) and its audited calculations.
        Schema::create('dasar_agihan_manfaat', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedInteger('versi_dasar');
            $table->string('kategori_hartanah', 30);
            $table->string('status_perolehan', 10)->comment('wakaf | belian');
            $table->decimal('kadar_agihan', 7, 4)->comment('% of collected revenue distributed');
            $table->json('penerima_manfaat')->comment('[{kod, nama, peratus}] — peratus of the distributed amount, sums to 100');
            $table->date('tarikh_mula_kuasa');
            $table->date('tarikh_tamat')->nullable();
            $table->string('rujukan_kelulusan', 120)->nullable();
            $table->string('status', 20)->default('draf')->comment('draf | dalam_kelulusan | aktif | ditolak | digantikan');
            $table->string('id_instans_aliran', 64)->nullable();
            $table->foreignId('id_pencipta')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kategori_hartanah', 'status_perolehan', 'status']);
        });
        Schema::create('kiraan_agihan_manfaat', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('premis_id')->constrained('premis_aset');
            $table->uuid('kontrak_id')->nullable();
            $table->string('tempoh_perakaunan', 7)->comment('YYYY-MM');
            $table->decimal('hasil_kutipan', 14, 2);
            $table->foreignId('id_dasar_digunakan')->constrained('dasar_agihan_manfaat');
            $table->unsignedInteger('versi_dasar');
            $table->string('kategori_hartanah', 30);
            $table->string('status_perolehan', 10);
            $table->decimal('kadar_agihan', 7, 4);
            $table->decimal('amaun_agihan', 14, 2);
            $table->json('pecahan_penerima');
            $table->string('kod_daerah', 4);
            $table->string('status_pengesahan', 20)->default('draf')->comment('draf | disahkan');
            $table->foreignId('id_pengira')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_pengesah')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tarikh_pengesahan')->nullable();
            $table->timestamps();
            $table->unique(['premis_id', 'tempoh_perakaunan']);
        });

        // SRS-PJK-09: letter templates (versioned) and generated letters.
        Schema::create('templat_surat', function (Blueprint $table) {
            $table->id('id');
            $table->string('kod', 40);
            $table->string('jenis_surat', 30)->comment('tawaran | peringatan_bayaran | tamat_tempoh | teguran | penamatan');
            $table->string('tajuk', 200);
            $table->text('kandungan');
            $table->json('medan_gabungan');
            $table->unsignedInteger('versi')->default(1);
            $table->boolean('status_aktif')->default(true);
            $table->foreignId('id_pengemas_kini')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kod', 'versi']);
        });
        Schema::create('surat_dijana', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('kontrak_id')->constrained('kontrak_pajakan');
            $table->foreignId('templat_id')->constrained('templat_surat');
            $table->string('no_rujukan_surat', 60)->unique();
            $table->text('kandungan');
            $table->string('id_dokumen', 64)->nullable();
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // SRS-PJK-06: rental applications from the public portal or the counter.
        Schema::create('permohonan_sewaan', function (Blueprint $table) {
            $table->id();
            $table->string('no_permohonan', 40)->unique();
            $table->string('sumber', 20)->comment('portal | kaunter');
            $table->string('rujukan_portal', 80)->nullable()->unique();
            $table->foreignId('premis_id')->constrained('premis_aset');
            $table->foreignId('penyewa_id')->nullable()->constrained('penyewa');
            $table->json('maklumat_pemohon')->nullable()->comment('as submitted (Sulit) until a tenant record is made');
            $table->date('tarikh_mula_dicadang')->nullable();
            $table->unsignedSmallInteger('tempoh_bulan')->default(24);
            $table->decimal('kadar_dicadang', 14, 2)->nullable();
            $table->string('tujuan', 500)->nullable();
            $table->string('status', 30)->default('diterima')->comment('diterima | dalam_semakan | kontrak_disediakan | lulus | tolak | dibatalkan');
            $table->uuid('kontrak_id')->nullable();
            $table->text('catatan')->nullable();
            $table->string('kod_daerah', 4);
            $table->string('klasifikasi', 10)->default('sulit');
            $table->foreignId('id_pegawai')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sintetik')->default(false);
            $table->timestamps();
            $table->index(['status', 'kod_daerah']);
        });

        $this->seedTemplates();
    }

    private function seedTemplates(): void
    {
        $fields = ['no_rujukan_surat', 'tarikh_surat', 'nama_penyewa', 'alamat_penyewa', 'no_kontrak', 'nama_premis', 'no_lot', 'tarikh_mula', 'tarikh_tamat', 'kadar', 'deposit', 'baki_tunggakan', 'tarikh_akhir_bayaran'];
        $head = "Rujukan: {{no_rujukan_surat}}\nTarikh: {{tarikh_surat}}\n\n{{nama_penyewa}}\n{{alamat_penyewa}}\n\n";
        $rows = [
            ['PJK-TAWARAN', 'tawaran', 'Surat Tawaran Sewaan', $head."Tuan/Puan,\n\nTAWARAN SEWAAN {{nama_premis}} (LOT {{no_lot}})\n\nDengan hormatnya kami merujuk kepada permohonan tuan/puan. Majlis Agama Islam Selangor dengan sukacitanya menawarkan sewaan premis tersebut bagi tempoh {{tarikh_mula}} hingga {{tarikh_tamat}} pada kadar {{kadar}} sebulan dengan deposit {{deposit}}.\n\nSila kembalikan surat setuju terima dalam tempoh 14 hari.\n\nSekian, terima kasih."],
            ['PJK-PERINGATAN', 'peringatan_bayaran', 'Peringatan Bayaran Sewa', $head."Tuan/Puan,\n\nPERINGATAN BAYARAN SEWA — KONTRAK {{no_kontrak}}\n\nRekod kami menunjukkan baki tertunggak sebanyak {{baki_tunggakan}} bagi premis {{nama_premis}}. Sila jelaskan bayaran sebelum {{tarikh_akhir_bayaran}}.\n\nSekian, terima kasih."],
            ['PJK-TAMAT', 'tamat_tempoh', 'Notis Tamat Tempoh Perjanjian', $head."Tuan/Puan,\n\nNOTIS TAMAT TEMPOH PERJANJIAN {{no_kontrak}}\n\nPerjanjian sewaan bagi premis {{nama_premis}} akan tamat pada {{tarikh_tamat}}. Sila maklumkan hasrat pembaharuan sekurang-kurangnya 30 hari sebelum tarikh tersebut.\n\nSekian, terima kasih."],
            ['PJK-TEGURAN', 'teguran', 'Surat Teguran', $head."Tuan/Puan,\n\nSURAT TEGURAN — KONTRAK {{no_kontrak}}\n\nTuan/puan didapati tidak mematuhi terma perjanjian sewaan premis {{nama_premis}}. Baki tertunggak semasa ialah {{baki_tunggakan}}. Sila ambil tindakan pembetulan dengan segera.\n\nSekian, terima kasih."],
            ['PJK-PENAMATAN', 'penamatan', 'Notis Penamatan Perjanjian', $head."Tuan/Puan,\n\nNOTIS PENAMATAN PERJANJIAN {{no_kontrak}}\n\nDimaklumkan bahawa perjanjian sewaan premis {{nama_premis}} (lot {{no_lot}}) ditamatkan. Baki tertunggak {{baki_tunggakan}} perlu dijelaskan dan premis hendaklah dikosongkan.\n\nSekian, terima kasih."],
        ];
        $now = now();
        foreach ($rows as [$kod, $jenis, $tajuk, $kandungan]) {
            DB::table('templat_surat')->insert([
                'kod' => $kod, 'jenis_surat' => $jenis, 'tajuk' => $tajuk, 'kandungan' => $kandungan,
                'medan_gabungan' => json_encode($fields), 'versi' => 1, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['permohonan_sewaan', 'surat_dijana', 'templat_surat', 'kiraan_agihan_manfaat', 'dasar_agihan_manfaat', 'sisihan_rekonsiliasi', 'rekonsiliasi_larian', 'fail_penyelesaian',
            'deposit_cagaran', 'penghantaran_resit', 'resit', 'pelarasan_lejar', 'lejar_transaksi', 'agihan_bayaran', 'bayaran', 'panggilan_balik_gateway', 'transaksi_gateway',
            'jadual_caj', 'versi_kontrak', 'pindaan_kontrak', 'sejarah_status_kontrak'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('kontrak_pajakan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('premis_id');
            $table->dropConstrainedForeignId('penyewa_id');
            $table->dropConstrainedForeignId('id_pendaftar');
            $table->dropColumn(['no_kontrak', 'jenis_perjanjian', 'kategori_hartanah', 'kitaran_caj', 'hari_tempoh_bayar', 'nilai_kontrak', 'kod_daerah', 'kod_mukim', 'bahagian',
                'tarikh_status', 'no_versi', 'id_versi_semasa', 'id_kontrak_asal', 'id_dokumen_perjanjian', 'id_instans_aliran', 'sumber', 'permohonan_id', 'tarikh_penamatan', 'sebab_penamatan', 'sintetik']);
        });
        Schema::dropIfExists('sejarah_status_premis');
        Schema::dropIfExists('premis_aset');
        Schema::dropIfExists('penyewa');
        Schema::dropIfExists('pjk_penomboran');
    }
};
