<?php

use App\Modules\Tkt\Http\Controllers\IntegrasiController;
use App\Modules\Tkt\Http\Controllers\KonfigurasiController;
use App\Modules\Tkt\Http\Controllers\LaporanController;
use App\Modules\Tkt\Http\Controllers\SerahanController;
use App\Modules\Tkt\Http\Controllers\TiketController;
use App\Modules\Tkt\Http\Controllers\TindakanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/tkt — MOD-TKT. Authorised through MOD-RBC (`can:` → AccessControl).
| Ticket rows are filtered by Tiket::bolehDilihat (SRS-TKT-20): an
| out-of-scope ticket is a 404. Support actions additionally require the
| officer to cover the ticket (Support\Akses); reporter actions require
| being the reporter.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', [TiketController::class, 'rujukan'])->name('rujukan');

    Route::middleware('can:tkt.tiket.cipta')->group(function () {
        Route::post('tiket', [TiketController::class, 'store'])->middleware('throttle:20,1')->name('tiket.store');
        Route::post('cadangan', [TiketController::class, 'cadangan'])->middleware('throttle:60,1')->name('cadangan');
        Route::post('cadangan/jawapan', [TiketController::class, 'jawapan'])->middleware('throttle:20,1')->name('cadangan.jawapan');
        Route::post('cadangan/klik', [TiketController::class, 'klik'])->name('cadangan.klik');
        Route::post('cadangan/selesai', [TiketController::class, 'selesaiSendiri'])->name('cadangan.selesai');
    });

    // List / detail: own tickets (tkt.tiket.lihat) or support scope — checked in the controller.
    Route::get('tiket', [TiketController::class, 'index'])->name('tiket.index');
    Route::prefix('tiket/{tiket}')->name('tiket.')->group(function () {
        Route::get('/', [TiketController::class, 'show'])->name('show');
        Route::post('buka-semula', [TiketController::class, 'bukaSemula'])->name('buka_semula');
        Route::post('lampiran', [TiketController::class, 'lampiran'])->middleware('throttle:30,1')->name('lampiran');
        Route::get('lampiran/{lampiran}/muat-turun', [TiketController::class, 'muatTurun'])->whereNumber('lampiran')->name('lampiran.muat_turun');

        Route::middleware('can:tkt.tiket.kemas_kini')->group(function () {
            Route::post('balas', [TiketController::class, 'balas'])->name('balas');
            Route::post('sahkan', [TiketController::class, 'sahkan'])->name('sahkan');
            Route::post('kepuasan', [TiketController::class, 'kepuasan'])->name('kepuasan');
        });

        Route::middleware('can:tkt.sokongan.kemas_kini')->group(function () {
            Route::post('status', [TindakanController::class, 'status'])->name('status');
            Route::post('catatan', [TindakanController::class, 'catatan'])->name('catatan');
            Route::get('pegawai-layak', [TindakanController::class, 'pegawaiLayak'])->name('pegawai_layak');
            Route::post('tugaskan', [TindakanController::class, 'tugaskan'])->name('tugaskan');
            Route::post('serah-aras', [TindakanController::class, 'serahAras'])->name('serah_aras');
            Route::put('vendor', [TindakanController::class, 'vendor'])->name('vendor');
            Route::post('eskalasi', [TindakanController::class, 'eskalasi'])->name('eskalasi');
            Route::post('ubah', [TindakanController::class, 'ubah'])->name('ubah');
            Route::post('pendua', [TindakanController::class, 'pendua'])->name('pendua');
            Route::post('rujuk-aduan', [TindakanController::class, 'rujukAduan'])->name('rujuk_aduan');
            Route::post('mohon-kelulusan', [TindakanController::class, 'mohonKelulusan'])->name('mohon_kelulusan');
        });
    });

    Route::middleware('can:tkt.laporan.lihat')->prefix('laporan')->name('laporan.')->group(function () {
        Route::get('papan-pemuka', [LaporanController::class, 'papanPemuka'])->name('papan_pemuka');
        Route::get('bulanan', [LaporanController::class, 'bulanan'])->name('bulanan');
        Route::get('bulanan/eksport', [LaporanController::class, 'eksport'])->middleware('can:tkt.laporan.eksport')->name('bulanan.eksport');
        Route::post('bulanan/jana', [LaporanController::class, 'jana'])->middleware('can:tkt.laporan.eksport')->name('bulanan.jana');
        Route::get('arkib', [LaporanController::class, 'arkib'])->name('arkib');
        Route::get('arkib/{bulan}/muat-turun', [LaporanController::class, 'muatTurunArkib'])->where('bulan', '\d{4}-\d{2}')->middleware('can:tkt.laporan.eksport')->name('arkib.muat_turun');
        Route::get('eskalasi', [LaporanController::class, 'eskalasi'])->name('eskalasi');
        Route::get('aktiviti', [LaporanController::class, 'aktiviti'])->name('aktiviti');
    });
    Route::post('aktiviti', [LaporanController::class, 'simpanAktiviti'])->middleware('can:tkt.aktiviti.cipta')->name('aktiviti.store');

    Route::get('konfigurasi', [KonfigurasiController::class, 'index'])->middleware('can:tkt.konfigurasi.lihat')->name('konfigurasi');
    Route::middleware('can:tkt.konfigurasi.kemas_kini')->prefix('konfigurasi')->name('konfigurasi.')->group(function () {
        Route::post('sla', [KonfigurasiController::class, 'simpanSla'])->name('sla.store');
        Route::post('sla/{sla}/nyahaktif', [KonfigurasiController::class, 'nyahaktifSla'])->whereNumber('sla')->name('sla.nyahaktif');
        Route::post('pasukan', [KonfigurasiController::class, 'simpanPasukan'])->name('pasukan.store');
        Route::put('pasukan/{pasukan}', [KonfigurasiController::class, 'simpanPasukan'])->whereNumber('pasukan')->name('pasukan.update');
        Route::post('pasukan/{pasukan}/ahli', [KonfigurasiController::class, 'simpanAhli'])->whereNumber('pasukan')->name('pasukan.ahli');
        Route::post('peraturan', [KonfigurasiController::class, 'simpanPeraturan'])->name('peraturan.store');
        Route::put('peraturan/{peraturan}', [KonfigurasiController::class, 'simpanPeraturan'])->whereNumber('peraturan')->name('peraturan.update');
        Route::post('pemetaan', [KonfigurasiController::class, 'simpanPemetaan'])->name('pemetaan.store');
        Route::put('pemetaan/{pemetaan}', [KonfigurasiController::class, 'simpanPemetaan'])->whereNumber('pemetaan')->name('pemetaan.update');
        Route::get('pengguna', [KonfigurasiController::class, 'pengguna'])->name('pengguna');

        Route::get('integrasi/log', [IntegrasiController::class, 'log'])->name('integrasi.log');
        Route::get('integrasi/rekonsiliasi', [IntegrasiController::class, 'rekonsiliasi'])->name('integrasi.rekonsiliasi');
        Route::post('integrasi/ulang-cuba', [IntegrasiController::class, 'ulangCuba'])->name('integrasi.ulang_cuba');
        Route::post('integrasi/selaras', [IntegrasiController::class, 'selaras'])->name('integrasi.selaras');

        Route::post('serahan/pindah', [SerahanController::class, 'pindah'])->name('serahan.pindah');
    });
    Route::get('serahan/pakej', [SerahanController::class, 'pakej'])->middleware('can:tkt.serahan.eksport')->name('serahan.pakej');

    // Sistem Aduan berpusat MAIS → SISDATA (machine account behind the API Gateway).
    Route::middleware(['can:tkt.integrasi.sistem', 'throttle:120,1'])->prefix('integrasi/berpusat')->name('berpusat.')->group(function () {
        Route::post('tiket', [IntegrasiController::class, 'terima'])->name('terima');
        Route::post('tiket/{nombor}/kemas-kini', [IntegrasiController::class, 'kemasKini'])->where('nombor', '[A-Za-z0-9._-]+')->name('kemas_kini');
    });
});
