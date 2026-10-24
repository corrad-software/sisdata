<?php

use App\Modules\Kes\Http\Controllers\DokumenController;
use App\Modules\Kes\Http\Controllers\KeputusanController;
use App\Modules\Kes\Http\Controllers\KesController;
use App\Modules\Kes\Http\Controllers\KonfigurasiController;
use App\Modules\Kes\Http\Controllers\KosController;
use App\Modules\Kes\Http\Controllers\LaporanController;
use App\Modules\Kes\Http\Controllers\ProsidingController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/kes — MOD-KES. Every case-record route goes through `kes.akses`
| (SRS-KES-13/14): kes.kes.lihat + the explicit Rahsia grant
| kes.rahsia.lihat + MFA (SSO MAIS), refusals logged. Records are scoped by
| daerah/mukim/klasifikasi and the panel-counsel rule (Support\Akses), so an
| out-of-scope case is a 404. Writes additionally need kes.kes.kemas_kini
| (checked per record — archived cases are read-only).
*/

Route::middleware('auth:sanctum')->group(function () {
    // Form code lists (no case data).
    Route::get('rujukan', [KonfigurasiController::class, 'rujukan'])->middleware('can:kes.kes.lihat')->name('rujukan');
    Route::get('cari-lot', [KonfigurasiController::class, 'cariLot'])->middleware(['can:kes.kes.kemas_kini', 'throttle:60,1'])->name('cari_lot');

    Route::middleware(['can:kes.konfigurasi.lihat'])->get('konfigurasi', [KonfigurasiController::class, 'senaraiKonfigurasi'])->name('konfigurasi');
    Route::middleware(['can:kes.konfigurasi.kemas_kini'])->group(function () {
        Route::post('konfigurasi/jenis', [KonfigurasiController::class, 'simpanJenis'])->name('konfigurasi.jenis.store');
        Route::put('konfigurasi/jenis/{id}', [KonfigurasiController::class, 'simpanJenis'])->whereNumber('id')->name('konfigurasi.jenis.update');
        Route::post('konfigurasi/status', [KonfigurasiController::class, 'simpanStatus'])->name('konfigurasi.status.store');
        Route::put('konfigurasi/status/{id}', [KonfigurasiController::class, 'simpanStatus'])->whereNumber('id')->name('konfigurasi.status.update');
        Route::post('konfigurasi/panel', [KonfigurasiController::class, 'simpanPanel'])->name('konfigurasi.panel.store');
        Route::put('konfigurasi/panel/{id}', [KonfigurasiController::class, 'simpanPanel'])->whereNumber('id')->name('konfigurasi.panel.update');
    });

    Route::middleware(['can:kes.log_capaian.lihat'])->group(function () {
        Route::get('log-capaian', [LaporanController::class, 'logCapaian'])->name('log');
        Route::get('log-capaian/eksport', [LaporanController::class, 'eksportLog'])->middleware('can:kes.laporan.eksport')->name('log.eksport');
    });

    Route::middleware(['kes.akses'])->group(function () {
        Route::middleware('can:kes.laporan.lihat')->group(function () {
            Route::get('dashboard', [LaporanController::class, 'dashboard'])->name('dashboard');
            Route::get('laporan/{jenis}', [LaporanController::class, 'laporan'])->name('laporan');
            Route::get('laporan/{jenis}/eksport', [LaporanController::class, 'eksport'])->middleware('throttle:20,1')->name('laporan.eksport');
        });
        Route::get('kalendar', [LaporanController::class, 'kalendar'])->name('kalendar');

        Route::get('kes', [KesController::class, 'index'])->name('kes.index');
        Route::post('kes', [KesController::class, 'store'])->middleware('can:kes.kes.cipta')->name('kes.store');

        Route::prefix('kes/{kes}')->name('kes.')->group(function () {
            Route::get('/', [KesController::class, 'show'])->name('show');
            Route::get('cetak', [KesController::class, 'cetak'])->middleware('throttle:20,1')->name('cetak');
            Route::get('dokumen', [DokumenController::class, 'index'])->name('dokumen.index');
            Route::get('dokumen/{dokumen}/muat-turun', [DokumenController::class, 'muatTurun'])->whereNumber('dokumen')->middleware('throttle:60,1')->name('dokumen.muat_turun');
            Route::get('pihak/{pihak}/dedah', [KesController::class, 'dedahPihak'])->whereNumber('pihak')->middleware('throttle:30,1')->name('pihak.dedah');
            Route::get('rekod-dikemas-kini', [KeputusanController::class, 'rekodDikemasKini'])->name('rekod');

            Route::middleware('can:kes.kes.kemas_kini')->group(function () {
                Route::put('/', [KesController::class, 'update'])->name('update');
                Route::post('status', [KesController::class, 'status'])->name('status');
                Route::post('pemfailan', [KesController::class, 'pemfailan'])->name('pemfailan');
                Route::post('lot', [KesController::class, 'pautLot'])->name('lot.store');
                Route::delete('lot/{lot}', [KesController::class, 'nyahpautLot'])->whereUuid('lot')->name('lot.destroy');
                Route::post('pihak', [KesController::class, 'tambahPihak'])->name('pihak.store');
                Route::put('pihak/{pihak}', [KesController::class, 'kemasKiniPihak'])->whereNumber('pihak')->name('pihak.update');
                Route::post('peguam', [KesController::class, 'tambahPeguam'])->name('peguam.store');
                Route::post('peguam/panel', [KesController::class, 'lantikPanel'])->name('peguam.panel');
                Route::post('peguam/{peguam}/tamat', [KesController::class, 'tamatPeguam'])->whereNumber('peguam')->name('peguam.tamat');
                Route::post('segerak-mahkamah', [KesController::class, 'segerakMahkamah'])->middleware('throttle:10,1')->name('segerak');
                Route::post('rujuk-agc', [KesController::class, 'rujukAgc'])->name('agc');

                Route::post('prosiding', [ProsidingController::class, 'store'])->name('prosiding.store');
                Route::put('prosiding/{prosiding}', [ProsidingController::class, 'update'])->whereNumber('prosiding')->name('prosiding.update');
                Route::post('tarikh', [ProsidingController::class, 'tambahTarikh'])->name('tarikh.store');
                Route::put('tarikh/{tarikh}', [ProsidingController::class, 'kemasKiniTarikh'])->whereNumber('tarikh')->name('tarikh.update');

                Route::post('kos', [KosController::class, 'store'])->name('kos.store');
                Route::put('kos/{kos}', [KosController::class, 'update'])->whereNumber('kos')->name('kos.update');
                Route::post('kos/{kos}/mohon-bayaran', [KosController::class, 'mohonBayaran'])->whereNumber('kos')->name('kos.bayaran');
                Route::post('kos/{kos}/semak-grp', [KosController::class, 'semakGrp'])->whereNumber('kos')->name('kos.grp');

                Route::post('keputusan', [KeputusanController::class, 'store'])->name('keputusan.store');
                Route::post('tindakan', [KeputusanController::class, 'tambahTindakan'])->name('tindakan.store');
                Route::post('tindakan/{tindakan}/kemas-kini-rekod', [KeputusanController::class, 'kemasKiniRekod'])->whereNumber('tindakan')->name('tindakan.rekod');
                Route::post('rayuan', [KeputusanController::class, 'rayuan'])->name('rayuan.store');
                Route::post('rayuan/{rayuan}/keputusan', [KeputusanController::class, 'putusRayuan'])->whereNumber('rayuan')->name('rayuan.putus');
                Route::post('penyelesaian', [KeputusanController::class, 'penyelesaian'])->name('penyelesaian.store');
                Route::post('penyelesaian/{penyelesaian}/meterai', [KeputusanController::class, 'meterai'])->whereNumber('penyelesaian')->name('penyelesaian.meterai');

                Route::post('dokumen', [DokumenController::class, 'store'])->middleware('throttle:30,1')->name('dokumen.store');
            });

            Route::middleware('can:kes.kes.lulus')->group(function () {
                Route::post('tugaskan', [KesController::class, 'tugaskan'])->name('tugaskan');
                Route::post('arkib', [KesController::class, 'arkib'])->name('arkib');
            });
        });
    });
});
