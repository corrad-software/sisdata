<?php

use App\Modules\Ptn\Http\Controllers\IsuController;
use App\Modules\Ptn\Http\Controllers\JadualController;
use App\Modules\Ptn\Http\Controllers\KonfigurasiController;
use App\Modules\Ptn\Http\Controllers\LapanganController;
use App\Modules\Ptn\Http\Controllers\LaporanController;
use App\Modules\Ptn\Http\Controllers\LawatanController;
use App\Modules\Ptn\Http\Controllers\RisikoController;
use App\Modules\Ptn\Http\Controllers\RujukanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/ptn — MOD-PTN · Pemantauan Tanah. Every route authorises through
| MOD-RBC (`can:` → AccessControl); records are scoped by daerah/mukim/
| klasifikasi (ScopedByAccess), so an out-of-scope record is a 404. Field
| officers (ptn.lapangan.*) only see and record their own assigned visits
| (Support\Akses).
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', RujukanController::class)->name('rujukan');
    Route::get('cari-lot', [JadualController::class, 'cariLot'])->name('cari_lot');

    Route::middleware('can:ptn.jadual.lihat')->group(function () {
        Route::get('jadual', [JadualController::class, 'index'])->name('jadual.index');
        Route::get('jadual/{jadual}', [JadualController::class, 'show'])->whereUuid('jadual')->name('jadual.show');
    });
    Route::middleware('can:ptn.jadual.cipta')->group(function () {
        Route::post('jadual', [JadualController::class, 'store'])->name('jadual.store');
        Route::post('jadual/jana', [JadualController::class, 'jana'])->name('jadual.jana');
    });
    Route::middleware('can:ptn.jadual.kemas_kini')->group(function () {
        Route::put('jadual/{jadual}', [JadualController::class, 'update'])->whereUuid('jadual')->name('jadual.update');
        Route::post('jadual/{jadual}/batal', [JadualController::class, 'batal'])->whereUuid('jadual')->name('jadual.batal');
        Route::post('jadual/{jadual}/tugaskan', [JadualController::class, 'tugaskan'])->whereUuid('jadual')->name('jadual.tugaskan');
        Route::post('jadual-agih', [JadualController::class, 'agih'])->name('jadual.agih');
        Route::get('pegawai-layak', [JadualController::class, 'pegawaiLayak'])->name('pegawai_layak');
    });

    // Visits: supervisors see all in scope (ptn.lawatan.lihat); field officers their own (checked in the controller).
    Route::get('lawatan', [LawatanController::class, 'index'])->middleware('can:ptn.lawatan.lihat')->name('lawatan.index');
    Route::prefix('lawatan/{lawatan}')->whereUuid('lawatan')->name('lawatan.')->group(function () {
        Route::get('/', [LawatanController::class, 'show'])->name('show');
        Route::get('perbandingan', [LawatanController::class, 'perbandingan'])->name('perbandingan');
        Route::get('gambar/{gambar}/url', [LawatanController::class, 'urlGambar'])->whereNumber('gambar')->name('gambar.url');
        Route::post('gambar/{gambar}/semak', [LawatanController::class, 'semakGambar'])->whereNumber('gambar')->middleware('can:ptn.lawatan.lihat')->name('gambar.semak');
        Route::post('konflik', [LawatanController::class, 'konflik'])->middleware('can:ptn.lawatan.lihat')->name('konflik');
    });
    Route::prefix('laporan-lawatan/{laporan}')->whereUuid('laporan')->name('laporan_lawatan.')->group(function () {
        Route::get('pdf', [LawatanController::class, 'pdf'])->name('pdf');
        Route::post('tambahan', [LawatanController::class, 'tambahan'])->middleware('can:ptn.lawatan.kemas_kini')->name('tambahan');
        Route::post('mula-pengesahan', [LawatanController::class, 'mulaPengesahan'])->name('mula_pengesahan');
    });

    // SRS-PTN-05 field app (PWA) API.
    Route::prefix('lapangan')->name('lapangan.')->group(function () {
        Route::get('tugasan', [LapanganController::class, 'tugasan'])->middleware('can:ptn.lapangan.lihat')->name('tugasan');
        Route::get('lawatan', [LapanganController::class, 'lawatanSaya'])->middleware('can:ptn.lapangan.lihat')->name('lawatan_saya');
        Route::get('templat/{templat}', [LapanganController::class, 'templat'])->whereNumber('templat')->middleware('can:ptn.lapangan.lihat')->name('templat');
        Route::middleware('throttle:240,1')->group(function () {
            Route::post('lawatan', [LapanganController::class, 'cipta'])->name('lawatan.store');
            Route::put('lawatan/{lawatan}', [LapanganController::class, 'kemaskini'])->whereUuid('lawatan')->name('lawatan.update');
            Route::post('lawatan/{lawatan}/gambar', [LapanganController::class, 'gambar'])->whereUuid('lawatan')->name('lawatan.gambar');
            Route::post('lawatan/{lawatan}/hantar', [LapanganController::class, 'hantar'])->whereUuid('lawatan')->name('lawatan.hantar');
        });
    });

    Route::middleware('can:ptn.isu.lihat')->group(function () {
        Route::get('isu', [IsuController::class, 'index'])->name('isu.index');
        Route::get('isu/{isu}', [IsuController::class, 'show'])->whereUuid('isu')->name('isu.show');
    });
    Route::post('isu', [IsuController::class, 'store'])->middleware('can:ptn.isu.cipta')->name('isu.store');
    Route::middleware('can:ptn.isu.kemas_kini')->prefix('isu/{isu}')->whereUuid('isu')->name('isu.')->group(function () {
        Route::post('tindakan', [IsuController::class, 'tambahTindakan'])->name('tindakan.store');
        Route::post('eskalasi-aduan', [IsuController::class, 'eskalasiAduan'])->name('eskalasi_aduan');
        Route::post('eskalasi-kes', [IsuController::class, 'eskalasiKes'])->name('eskalasi_kes');
    });
    Route::get('pegawai', [IsuController::class, 'pegawai'])->middleware('can:ptn.isu.kemas_kini')->name('pegawai');
    Route::put('tindakan/{tindakan}', [IsuController::class, 'kemaskiniTindakan'])->whereNumber('tindakan')->name('tindakan.update');
    Route::post('tindakan/{tindakan}/selesai', [IsuController::class, 'selesaiTindakan'])->whereNumber('tindakan')->name('tindakan.selesai');

    Route::middleware('can:ptn.lawatan.lihat')->group(function () {
        Route::get('lot', [RisikoController::class, 'lot'])->name('lot.index');
        Route::get('lot/{lot}', [RisikoController::class, 'lotButiran'])->whereUuid('lot')->name('lot.show');
    });
    Route::get('risiko/dashboard', [RisikoController::class, 'dashboard'])->middleware('can:ptn.risiko.lihat')->name('risiko.dashboard');
    Route::post('risiko/kira', [RisikoController::class, 'kira'])->middleware('can:ptn.konfigurasi.kemas_kini')->name('risiko.kira');

    Route::middleware('can:ptn.laporan.lihat')->prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/', [LaporanController::class, 'index'])->name('index');
        Route::get('{laporan}', [LaporanController::class, 'show'])->name('show');
        Route::get('{laporan}/eksport', [LaporanController::class, 'eksport'])->middleware('can:ptn.laporan.eksport')->name('eksport');
    });

    Route::get('konfigurasi', [KonfigurasiController::class, 'index'])->middleware('can:ptn.konfigurasi.lihat')->name('konfigurasi');
    Route::middleware('can:ptn.konfigurasi.kemas_kini')->prefix('konfigurasi')->name('konfigurasi.')->group(function () {
        Route::post('templat', [KonfigurasiController::class, 'simpanTemplat'])->name('templat.store');
        Route::post('templat/{templat}/versi', [KonfigurasiController::class, 'versiTemplat'])->whereNumber('templat')->name('templat.versi');
        Route::post('templat/{templat}/status', [KonfigurasiController::class, 'statusTemplat'])->whereNumber('templat')->name('templat.status');
        Route::put('kekerapan', [KonfigurasiController::class, 'simpanKekerapan'])->name('kekerapan');
        Route::post('ambang', [KonfigurasiController::class, 'simpanAmbang'])->name('ambang');
    });
});
