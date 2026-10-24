<?php

use App\Modules\Pro\Http\Controllers\ArkibApiController;
use App\Modules\Pro\Http\Controllers\ImportController;
use App\Modules\Pro\Http\Controllers\KamusController;
use App\Modules\Pro\Http\Controllers\LotController;
use App\Modules\Pro\Http\Controllers\SpatialController;
use App\Modules\Pro\Http\Controllers\TadbirUrusController;
use Illuminate\Support\Facades\Route;

/*
| MOD-PRO · /api/v1/pro. Every route authorises through MOD-RBC
| (`pro.boleh:<kebenaran>`); routes also reachable with an API key carry
| `pro.api:<skop>` (SRS-PRO-29 — no-op for browser sessions).
| OpenAPI: docs/api/pro-openapi.yaml (also served at /api/v1/pro/openapi.yaml).
*/

$uuid = '[0-9a-fA-F-]{36}';

Route::middleware('auth:sanctum')->group(function () use ($uuid) {
    Route::get('openapi.yaml', [ArkibApiController::class, 'openapi'])->name('openapi');

    // ---- Lot master + 360 profile ------------------------------------------------
    Route::middleware('pro.boleh:pro.lot.lihat')->group(function () use ($uuid) {
        Route::get('rujukan', [LotController::class, 'references'])->name('rujukan');
        Route::get('lot', [LotController::class, 'index'])->middleware('pro.api:pro:lot')->name('lot.index');
        Route::get('lot/ringkasan', [LotController::class, 'summary'])->name('lot.ringkasan');
        Route::get('geojson', [LotController::class, 'collection'])->middleware('pro.api:pro:geojson')->name('geojson');
        Route::get('lot/{id}', [LotController::class, 'show'])->where('id', $uuid)->middleware('pro.api:pro:lot')->name('lot.show');
        Route::get('lot/{id}/geojson', [LotController::class, 'geojson'])->where('id', $uuid)->middleware('pro.api:pro:geojson')->name('lot.geojson');
        Route::get('lot/{id}/kewangan', [LotController::class, 'financial'])->where('id', $uuid)->middleware('pro.api:pro:kewangan')->name('lot.kewangan');
        Route::get('lot/{id}/profil', [LotController::class, 'profile'])->where('id', $uuid)->name('lot.profil');
        Route::post('lot/{id}/cetak', [LotController::class, 'printed'])->where('id', $uuid)->name('lot.cetak');
        Route::get('lot/{id}/versi', [LotController::class, 'versions'])->where('id', $uuid)->name('lot.versi');
        Route::get('lot/{id}/versi/{versi}', [LotController::class, 'compare'])->where(['id' => $uuid, 'versi' => $uuid])->name('lot.versi.banding');
        Route::get('lot/{id}/spatial', [SpatialController::class, 'index'])->where('id', $uuid)->name('spatial.index');
    });
    Route::get('lot/eksport', [LotController::class, 'export'])->middleware('pro.boleh:pro.lot.eksport')->name('lot.eksport');
    Route::get('lot/{id}/profil/eksport', [LotController::class, 'exportProfile'])->where('id', $uuid)->middleware('pro.boleh:pro.lot.eksport')->name('lot.profil.eksport');
    Route::post('lot', [LotController::class, 'store'])->middleware('pro.boleh:pro.lot.cipta')->name('lot.store');
    Route::put('lot/{id}', [LotController::class, 'update'])->where('id', $uuid)->middleware('pro.boleh:pro.lot.kemas_kini')->name('lot.update');
    Route::delete('lot/{id}', [LotController::class, 'destroy'])->where('id', $uuid)->middleware('pro.boleh:pro.lot.padam')->name('lot.destroy');
    Route::post('lot/{id}/semak-kualiti', [LotController::class, 'checkQuality'])->where('id', $uuid)->middleware('pro.boleh:pro.kualiti.kemas_kini|pro.lot.kemas_kini')->name('lot.semak');
    Route::post('lot/{id}/arkib', [LotController::class, 'archive'])->where('id', $uuid)->middleware('pro.boleh:pro.arkib.cipta')->name('lot.arkib');
    Route::post('lot/{id}/pulih', [LotController::class, 'restore'])->where('id', $uuid)->middleware('pro.boleh:pro.arkib.kemas_kini')->name('lot.pulih');

    // ---- Data spatial ------------------------------------------------------------
    Route::post('lot/{id}/spatial', [SpatialController::class, 'store'])->where('id', $uuid)->middleware('pro.boleh:pro.spatial.cipta')->name('spatial.store');
    Route::post('spatial/{spatial}/sahkan', [SpatialController::class, 'verify'])->whereNumber('spatial')->middleware('pro.boleh:pro.spatial.lulus')->name('spatial.sahkan');

    // ---- Stewardship (SRS-PRO-11) ------------------------------------------------
    Route::get('stewardship', [TadbirUrusController::class, 'stewardship'])->middleware('pro.boleh:pro.stewardship.lihat')->name('stewardship.index');
    Route::get('stewardship/eksport', [TadbirUrusController::class, 'exportStewardship'])->middleware('pro.boleh:pro.stewardship.lihat')->name('stewardship.eksport');
    Route::post('stewardship', [TadbirUrusController::class, 'storeStewardship'])->middleware('pro.boleh:pro.stewardship.kemas_kini')->name('stewardship.store');
    Route::put('stewardship/{id}', [TadbirUrusController::class, 'updateStewardship'])->whereNumber('id')->middleware('pro.boleh:pro.stewardship.kemas_kini')->name('stewardship.update');
    Route::get('pengguna', [TadbirUrusController::class, 'users'])->middleware('pro.boleh:pro.stewardship.kemas_kini|pro.lot.kemas_kini|pro.lot.cipta')->name('pengguna');

    // ---- Data quality (SRS-PRO-12, 13, 24) ---------------------------------------
    Route::middleware('pro.boleh:pro.kualiti.lihat')->group(function () {
        Route::get('kualiti/laporan', [TadbirUrusController::class, 'report'])->name('kualiti.laporan');
        Route::get('kualiti/laporan/eksport', [TadbirUrusController::class, 'exportReport'])->name('kualiti.laporan.eksport');
        Route::get('kualiti/peraturan', [TadbirUrusController::class, 'rules'])->name('kualiti.peraturan');
    });
    Route::get('kualiti/tugasan', [TadbirUrusController::class, 'tasks'])->middleware('pro.boleh:pro.kualiti.lihat|pro.stewardship.lihat')->name('kualiti.tugasan');
    Route::middleware('pro.boleh:pro.kualiti.kemas_kini')->group(function () {
        Route::post('kualiti/peraturan', [TadbirUrusController::class, 'storeRule'])->name('kualiti.peraturan.store');
        Route::put('kualiti/peraturan/{id}', [TadbirUrusController::class, 'updateRule'])->whereNumber('id')->name('kualiti.peraturan.update');
        Route::post('kualiti/tugasan/{id}/selesai', [TadbirUrusController::class, 'resolveTask'])->whereNumber('id')->name('kualiti.tugasan.selesai');
        Route::post('kualiti/jalankan', [TadbirUrusController::class, 'runReport'])->name('kualiti.jalankan');
    });

    // ---- Duplicates (SRS-PRO-20) -------------------------------------------------
    Route::get('duplikasi', [TadbirUrusController::class, 'duplicates'])->middleware('pro.boleh:pro.duplikasi.lihat')->name('duplikasi.index');
    Route::get('duplikasi/kriteria', [TadbirUrusController::class, 'criteria'])->middleware('pro.boleh:pro.duplikasi.lihat')->name('duplikasi.kriteria');
    Route::post('duplikasi/kesan', [TadbirUrusController::class, 'detectDuplicates'])->middleware('pro.boleh:pro.duplikasi.kemas_kini')->name('duplikasi.kesan');
    Route::post('duplikasi/{id}/keputusan', [TadbirUrusController::class, 'decideDuplicate'])->whereNumber('id')->middleware('pro.boleh:pro.duplikasi.kemas_kini')->name('duplikasi.keputusan');

    // ---- Import (SRS-PRO-15..17) -------------------------------------------------
    Route::middleware('pro.boleh:pro.import.cipta')->group(function () use ($uuid) {
        Route::get('import/medan/{entiti}', [ImportController::class, 'fields'])->name('import.medan');
        Route::get('import/templat/{entiti}', [ImportController::class, 'template'])->name('import.templat');
        Route::get('import/pemetaan', [ImportController::class, 'mappings'])->name('import.pemetaan');
        Route::post('import/pemetaan', [ImportController::class, 'storeMapping'])->name('import.pemetaan.store');
        Route::post('import', [ImportController::class, 'store'])->name('import.store');
        Route::post('import/{id}/pemetaan', [ImportController::class, 'remap'])->where('id', $uuid)->name('import.remap');
        Route::post('import/{id}/komit', [ImportController::class, 'commit'])->where('id', $uuid)->name('import.komit');
        Route::post('import/{id}/batal', [ImportController::class, 'cancel'])->where('id', $uuid)->name('import.batal');
    });
    Route::middleware('pro.boleh:pro.import.cipta|pro.kualiti.lihat')->group(function () use ($uuid) {
        Route::get('import', [ImportController::class, 'index'])->name('import.index');
        Route::get('import/{id}', [ImportController::class, 'show'])->where('id', $uuid)->name('import.show');
        Route::get('import/{id}/laporan', [ImportController::class, 'report'])->where('id', $uuid)->name('import.laporan');
    });

    // ---- Data dictionary / physical model / classification (SRS-PRO-03..05, 14) ---
    Route::middleware('pro.boleh:pro.kamus.lihat')->group(function () {
        Route::get('kamus', [KamusController::class, 'index'])->name('kamus.index');
        Route::get('kamus/jadual', [KamusController::class, 'tables'])->name('kamus.jadual');
        Route::get('kamus/eksport', [KamusController::class, 'export'])->name('kamus.eksport');
        Route::get('kamus/model-fizikal', [KamusController::class, 'physical'])->name('kamus.fizikal');
        Route::get('klasifikasi', [KamusController::class, 'classification'])->name('klasifikasi');
    });
    Route::put('kamus/metadata', [KamusController::class, 'upsert'])->middleware('pro.boleh:pro.kamus.kemas_kini')->name('kamus.metadata');

    // ---- Retention / archive (SRS-PRO-23) ----------------------------------------
    Route::get('arkib', [ArkibApiController::class, 'archive'])->middleware('pro.boleh:pro.arkib.lihat')->name('arkib.index');
    Route::get('arkib/jadual', [ArkibApiController::class, 'schedules'])->middleware('pro.boleh:pro.arkib.lihat')->name('arkib.jadual');
    Route::put('arkib/jadual/{id}', [ArkibApiController::class, 'updateSchedule'])->whereNumber('id')->middleware('pro.boleh:pro.arkib.kemas_kini')->name('arkib.jadual.update');
    Route::post('arkib/jana', [ArkibApiController::class, 'generate'])->middleware('pro.boleh:pro.arkib.cipta')->name('arkib.jana');
    Route::post('arkib/{id}/hantar', [ArkibApiController::class, 'submit'])->where('id', $uuid)->middleware('pro.boleh:pro.arkib.cipta')->name('arkib.hantar');

    // ---- API keys (SRS-PRO-29) ---------------------------------------------------
    Route::middleware('pro.boleh:pro.api.cipta')->group(function () {
        Route::get('kunci-api', [ArkibApiController::class, 'keys'])->name('kunci.index');
        Route::post('kunci-api', [ArkibApiController::class, 'createKey'])->name('kunci.store');
        Route::delete('kunci-api/{id}', [ArkibApiController::class, 'revokeKey'])->whereNumber('id')->name('kunci.destroy');
        Route::get('kunci-api/penggunaan', [ArkibApiController::class, 'usage'])->name('kunci.penggunaan');
    });
});
