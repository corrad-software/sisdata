<?php

use App\Modules\Per\Http\Controllers\ButiranKesController;
use App\Modules\Per\Http\Controllers\KesController;
use App\Modules\Per\Http\Controllers\LaporanController;
use App\Modules\Per\Http\Controllers\PortalWakafController;
use App\Modules\Per\Http\Controllers\SaluranController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/per — MOD-PER Rekod Pemerolehan. Every route authorises through
| MOD-RBC (`can:` → AccessControl); record scope (daerah / bahagian /
| klasifikasi) applies to every case query via ScopedByAccess.
*/

Route::middleware('auth:sanctum')->group(function () {
    $lihat = 'can:per.kes.lihat';
    $ubah = 'can:per.kes.kemas_kini';

    Route::get('saluran', [SaluranController::class, 'index'])->middleware($lihat)->name('saluran.index');
    Route::get('saluran/{kod}', [SaluranController::class, 'show'])->middleware($lihat)->name('saluran.show');
    Route::put('saluran/{kod}', [SaluranController::class, 'update'])->middleware('can:per.saluran.kemas_kini')->name('saluran.update');

    Route::get('pegawai', [KesController::class, 'officers'])->middleware($ubah)->name('pegawai');

    Route::get('kes', [KesController::class, 'index'])->middleware($lihat)->name('kes.index');
    Route::post('kes', [KesController::class, 'store'])->middleware('can:per.kes.cipta')->name('kes.store');
    Route::get('kes/{kes}', [KesController::class, 'show'])->middleware($lihat)->name('kes.show');
    Route::put('kes/{kes}', [KesController::class, 'update'])->middleware($ubah)->name('kes.update');
    Route::get('kes/{kes}/garis-masa', [KesController::class, 'timeline'])->middleware($lihat)->name('kes.garis_masa');
    Route::post('kes/{kes}/maju', [KesController::class, 'advance'])->middleware($ubah)->name('kes.maju');
    Route::put('kes/{kes}/pegawai', [KesController::class, 'assign'])->middleware($ubah)->name('kes.pegawai');
    Route::post('kes/{kes}/pautkan-lot', [KesController::class, 'relink'])->middleware($ubah)->name('kes.pautkan_lot');
    Route::post('kes/{kes}/semakan-awam', [KesController::class, 'review'])->middleware($ubah)->name('kes.semakan_awam');
    Route::post('kes/{kes}/batal', [KesController::class, 'cancel'])->middleware('can:per.kes.padam')->name('kes.batal');
    Route::post('kes/{kes}/tutup', [KesController::class, 'close'])->middleware('can:per.hak_milik.cipta')->name('kes.tutup');

    Route::post('kes/{kes}/pihak', [ButiranKesController::class, 'addParty'])->middleware($ubah)->name('pihak.store');
    Route::put('kes/{kes}/pihak/{pihak}', [ButiranKesController::class, 'updateParty'])->whereNumber('pihak')->middleware($ubah)->name('pihak.update');
    Route::delete('kes/{kes}/pihak/{pihak}', [ButiranKesController::class, 'deleteParty'])->whereNumber('pihak')->middleware($ubah)->name('pihak.destroy');
    Route::get('kes/{kes}/pihak/{pihak}/penuh', [ButiranKesController::class, 'revealParty'])->whereNumber('pihak')->middleware(['can:per.kes.lihat', 'can:per.sulit.lihat'])->name('pihak.penuh');
    Route::post('kes/{kes}/e-faraid', [ButiranKesController::class, 'verifyFaraid'])->middleware($ubah)->name('kes.e_faraid');

    Route::post('kes/{kes}/dokumen', [ButiranKesController::class, 'storeDocument'])->middleware('can:per.dokumen.cipta')->name('dokumen.store');
    Route::put('kes/{kes}/dokumen/{dokumen}', [ButiranKesController::class, 'updateDocument'])->whereNumber('dokumen')->middleware('can:per.dokumen.cipta')->name('dokumen.update');
    Route::post('kes/{kes}/dokumen/{dokumen}/pergerakan', [ButiranKesController::class, 'moveDocument'])->whereNumber('dokumen')->middleware('can:per.dokumen.cipta')->name('dokumen.pergerakan');

    Route::post('kes/{kes}/bayaran', [ButiranKesController::class, 'storePayment'])->middleware('can:per.bayaran.cipta')->name('bayaran.store');
    Route::post('kes/{kes}/bayaran/{bayaran}/pengesahan', [ButiranKesController::class, 'confirmPayment'])->whereNumber('bayaran')->middleware('can:per.bayaran.lulus')->name('bayaran.pengesahan');

    Route::get('laporan', [LaporanController::class, 'summary'])->middleware('can:per.laporan.lihat')->name('laporan');
    Route::get('laporan/khas/{jenis}', [LaporanController::class, 'special'])->middleware('can:per.laporan.lihat')->name('laporan.khas');
    Route::get('laporan/eksport', [LaporanController::class, 'export'])->middleware('can:per.laporan.eksport')->name('laporan.eksport');

    Route::post('portal-wakaf/permohonan', [PortalWakafController::class, 'store'])->middleware('can:per.portal_wakaf.cipta')->name('portal_wakaf.store');
});
