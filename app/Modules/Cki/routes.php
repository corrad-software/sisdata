<?php

use App\Modules\Cki\Http\Controllers\BayaranController;
use App\Modules\Cki\Http\Controllers\BilController;
use App\Modules\Cki\Http\Controllers\IntegrasiController;
use App\Modules\Cki\Http\Controllers\LaporanController;
use App\Modules\Cki\Http\Controllers\ObligasiController;
use App\Modules\Cki\Http\Controllers\OperasiController;
use App\Modules\Cki\Http\Controllers\RekonsiliasiController;
use App\Modules\Cki\Http\Controllers\RujukanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/cki — MOD-CKI. Every route authorises through MOD-RBC (`can:` →
| AccessControl); bills, obligations, transactions and conflicts are scoped
| by daerah/mukim (ScopedByAccess), so out-of-scope records are 404.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', [RujukanController::class, 'index'])->middleware('can:cki.bil.lihat')->name('rujukan');
    Route::get('lot', [RujukanController::class, 'lots'])->middleware('can:cki.obligasi.cipta')->name('lot.carian');

    // Tax register (SRS-CKI-01/06).
    Route::get('obligasi', [ObligasiController::class, 'index'])->middleware('can:cki.obligasi.lihat')->name('obligasi.index');
    Route::post('obligasi', [ObligasiController::class, 'store'])->middleware('can:cki.obligasi.cipta')->name('obligasi.store');
    Route::put('obligasi/{obligasi}', [ObligasiController::class, 'update'])->whereUuid('obligasi')->middleware('can:cki.obligasi.kemas_kini')->name('obligasi.update');

    // Bills (SRS-CKI-02/07/16/19).
    Route::middleware('can:cki.bil.lihat')->group(function () {
        Route::get('bil', [BilController::class, 'index'])->name('bil.index');
        Route::get('bil/{cukai}', [BilController::class, 'show'])->whereUuid('cukai')->name('bil.show');
        Route::get('lot/{lotId}/garis-masa', [BilController::class, 'timeline'])->whereUuid('lotId')->name('lot.garis_masa');
        Route::get('jadual', [OperasiController::class, 'schedule'])->name('jadual');
        Route::get('transaksi', [BayaranController::class, 'index'])->name('transaksi.index');
        Route::get('transaksi/{transaksi}', [BayaranController::class, 'show'])->whereUuid('transaksi')->name('transaksi.show');
    });
    Route::middleware('can:cki.bil.eksport')->group(function () {
        Route::get('bil-eksport', [BilController::class, 'export'])->name('bil.eksport');
        Route::get('lot/{lotId}/garis-masa/eksport', [BilController::class, 'timelineExport'])->whereUuid('lotId')->name('lot.garis_masa.eksport');
    });
    Route::middleware('can:cki.bil.cipta')->group(function () {
        Route::post('bil', [BilController::class, 'store'])->name('bil.store');
        Route::get('tugasan', [OperasiController::class, 'tasks'])->name('tugasan');
        Route::post('import', [LaporanController::class, 'import'])->name('import');
        Route::get('import/templat', [LaporanController::class, 'template'])->name('import.templat');
    });
    Route::middleware('can:cki.bil.kemas_kini')->group(function () {
        Route::post('bil/{cukai}/pelarasan', [BilController::class, 'adjust'])->whereUuid('cukai')->name('bil.pelarasan');
        Route::post('bil/{cukai}/dokumen', [BilController::class, 'document'])->whereUuid('cukai')->name('bil.dokumen');
    });

    // Payments (SRS-CKI-11..15).
    Route::middleware('can:cki.bayaran.cipta')->group(function () {
        Route::post('bil/{cukai}/bayaran', [BayaranController::class, 'store'])->whereUuid('cukai')->name('bayaran.store');
        Route::post('bil/{cukai}/resit', [BayaranController::class, 'billReceipt'])->whereUuid('cukai')->name('bil.resit');
        Route::post('resit', [BilController::class, 'orphanReceipt'])->name('resit.store');
        Route::post('transaksi/{transaksi}/laksana', [BayaranController::class, 'execute'])->whereUuid('transaksi')->name('transaksi.laksana');
        Route::post('transaksi/{transaksi}/rekod', [BayaranController::class, 'record'])->whereUuid('transaksi')->name('transaksi.rekod');
        Route::post('transaksi/{transaksi}/resit', [BayaranController::class, 'receipt'])->whereUuid('transaksi')->name('transaksi.resit');
        Route::post('transaksi/{transaksi}/selesai', [BayaranController::class, 'complete'])->whereUuid('transaksi')->name('transaksi.selesai');
        Route::post('transaksi/{transaksi}/semak-grp', [BayaranController::class, 'grpStatus'])->whereUuid('transaksi')->name('transaksi.semak_grp');
    });

    // Sync conflicts (SRS-CKI-09).
    Route::get('konflik', [OperasiController::class, 'conflicts'])->middleware('can:cki.konflik.kemas_kini')->name('konflik.index');
    Route::post('konflik/{konflik}/selesai', [OperasiController::class, 'resolve'])->whereNumber('konflik')->middleware('can:cki.konflik.kemas_kini')->name('konflik.selesai');

    // Integration (SRS-CKI-04/06/08/10/24).
    Route::middleware('can:cki.integrasi.lihat')->group(function () {
        Route::get('integrasi', [IntegrasiController::class, 'index'])->name('integrasi.index');
        Route::get('integrasi/log', [IntegrasiController::class, 'log'])->name('integrasi.log');
    });
    Route::middleware('can:cki.integrasi.kemas_kini')->group(function () {
        Route::put('integrasi/{liputan}', [IntegrasiController::class, 'update'])->whereNumber('liputan')->name('integrasi.update');
        Route::post('integrasi/lalai', [IntegrasiController::class, 'seed'])->name('integrasi.lalai');
        Route::post('integrasi/semak', [IntegrasiController::class, 'check'])->name('integrasi.semak');
        Route::post('integrasi/simulasi', [IntegrasiController::class, 'simulate'])->name('integrasi.simulasi');
    });

    // Reconciliation (SRS-CKI-20).
    Route::get('rekonsiliasi', [RekonsiliasiController::class, 'index'])->middleware('can:cki.rekonsiliasi.lihat')->name('rekonsiliasi.index');
    Route::post('rekonsiliasi', [RekonsiliasiController::class, 'store'])->middleware('can:cki.rekonsiliasi.cipta')->name('rekonsiliasi.store');

    // Dashboard & reports (SRS-CKI-21/22/23).
    Route::middleware('can:cki.laporan.lihat')->group(function () {
        Route::get('dashboard', [LaporanController::class, 'dashboard'])->name('dashboard');
        Route::get('laporan/{jenis}', [LaporanController::class, 'show'])->name('laporan.show');
    });
    Route::get('laporan/{jenis}/eksport', [LaporanController::class, 'export'])->middleware('can:cki.laporan.eksport')->name('laporan.eksport');
});
