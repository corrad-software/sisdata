<?php

use App\Modules\Aud\Http\Controllers\AuditExportController;
use App\Modules\Aud\Http\Controllers\AuditIntegrityController;
use App\Modules\Aud\Http\Controllers\AuditReportController;
use App\Modules\Aud\Http\Controllers\AuditRetentionController;
use App\Modules\Aud\Http\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/aud — read-only (SRS-AUD-08/23). There are intentionally no routes
| that update or delete an audit record, for any role.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:aud.rekod.lihat')->group(function () {
        Route::get('rekod', [AuditTrailController::class, 'index'])->name('rekod.index');
        Route::get('rekod/{id}', [AuditTrailController::class, 'show'])->whereNumber('id')->name('rekod.show');
        Route::get('kronologi', [AuditTrailController::class, 'chronology'])->name('kronologi');
        Route::get('pilihan', [AuditTrailController::class, 'options'])->name('pilihan');
    });

    Route::middleware('can:aud.rekod.eksport')->group(function () {
        Route::get('eksport', [AuditExportController::class, 'download'])->name('eksport');
        Route::get('eksport/sejarah', [AuditExportController::class, 'history'])->name('eksport.sejarah');
    });

    Route::get('integriti', [AuditIntegrityController::class, 'index'])->middleware('can:aud.integriti.lihat')->name('integriti.index');
    Route::post('integriti/semak', [AuditIntegrityController::class, 'run'])->middleware('can:aud.integriti.cipta')->name('integriti.semak');

    Route::get('laporan/{jenis}', [AuditReportController::class, 'show'])->middleware('can:aud.laporan.lihat')->name('laporan');

    Route::get('retensi', [AuditRetentionController::class, 'show'])->middleware('can:aud.retensi.lihat')->name('retensi.show');
    Route::put('retensi', [AuditRetentionController::class, 'update'])->middleware('can:aud.retensi.kemas_kini')->name('retensi.update');
});
