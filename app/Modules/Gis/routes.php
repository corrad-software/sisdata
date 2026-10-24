<?php

use App\Modules\Gis\Http\Controllers\AnalisisController;
use App\Modules\Gis\Http\Controllers\AsetController;
use App\Modules\Gis\Http\Controllers\CetakController;
use App\Modules\Gis\Http\Controllers\ImportController;
use App\Modules\Gis\Http\Controllers\KualitiController;
use App\Modules\Gis\Http\Controllers\LapisanController;
use App\Modules\Gis\Http\Controllers\OgcController;
use App\Modules\Gis\Http\Controllers\PetaController;
use Illuminate\Support\Facades\Route;

/*
| MOD-GIS · /api/v1/gis. Every route authorises through MOD-RBC (`can:`);
| layer-level access (permission + classification, SRS-GIS-32) is checked
| again per request by LayerService for every feature / tile / OGC call.
*/

$uuid = '[0-9a-fA-F-]{36}';
$key = '[a-z][a-z0-9_]*\.[a-z0-9_.]+';

Route::middleware(['auth:sanctum', 'can:gis.peta.lihat'])->group(function () use ($uuid, $key) {
    // ---- Map workspace -----------------------------------------------------------
    Route::get('lapisan', [PetaController::class, 'index'])->name('lapisan');
    Route::get('lapisan/{key}/ciri', [PetaController::class, 'features'])->where('key', $key)->name('ciri');
    Route::get('tiles/{key}/{z}/{x}/{y}.pbf', [PetaController::class, 'tile'])->where(['key' => $key, 'z' => '[0-9]+', 'x' => '[0-9]+', 'y' => '[0-9]+'])->middleware('throttle:gis-tiles')->name('tile');
    Route::get('cari', [PetaController::class, 'search'])->name('cari');
    Route::get('statistik', [PetaController::class, 'statistics'])->name('statistik');
    Route::get('lot/{id}/versi', [PetaController::class, 'versions'])->where('id', $uuid)->name('versi');
    Route::get('lot/{id}/versi/banding', [PetaController::class, 'compare'])->where('id', $uuid)->name('versi.banding');
    Route::get('lapisan/{key}/eksport', [PetaController::class, 'export'])->where('key', $key)->middleware('can:gis.peta.eksport')->name('eksport');

    // ---- Print / export map (SRS-GIS-33) ---------------------------------------
    Route::middleware('can:gis.peta.eksport')->group(function () {
        Route::post('cetak/pdf', [CetakController::class, 'pdf'])->name('cetak.pdf');
        Route::post('cetak/log', [CetakController::class, 'log'])->name('cetak.log');
    });

    // ---- Saved views -------------------------------------------------------------
    Route::get('paparan', [AnalisisController::class, 'views'])->name('paparan.index');
    Route::post('paparan', [AnalisisController::class, 'storeView'])->name('paparan.store');
    Route::delete('paparan/{id}', [AnalisisController::class, 'destroyView'])->whereNumber('id')->name('paparan.destroy');

    // ---- Spatial analysis + scenarios (SRS-GIS-15..21) ---------------------------
    Route::middleware('can:gis.analisis.cipta')->group(function () {
        Route::post('analisis', [AnalisisController::class, 'run'])->name('analisis');
        Route::get('senario', [AnalisisController::class, 'scenarios'])->name('senario.index');
        Route::post('senario', [AnalisisController::class, 'storeScenario'])->name('senario.store');
        Route::get('senario/{id}', [AnalisisController::class, 'showScenario'])->whereNumber('id')->name('senario.show');
        Route::delete('senario/{id}', [AnalisisController::class, 'destroyScenario'])->whereNumber('id')->name('senario.destroy');
    });
    Route::post('analisis/eksport', [AnalisisController::class, 'export'])->middleware('can:gis.analisis.eksport')->name('analisis.eksport');

    // ---- Import (SRS-GIS-22) -----------------------------------------------------
    Route::middleware('can:gis.import.lihat')->group(function () use ($uuid) {
        Route::get('import', [ImportController::class, 'index'])->name('import.index');
        Route::get('import/{id}', [ImportController::class, 'show'])->where('id', $uuid)->name('import.show');
        Route::get('import/{id}/ciri', [ImportController::class, 'features'])->where('id', $uuid)->name('import.ciri');
        Route::get('import/{id}/laporan', [ImportController::class, 'report'])->where('id', $uuid)->name('import.laporan');
    });
    Route::middleware('can:gis.import.cipta')->group(function () use ($uuid) {
        Route::post('import', [ImportController::class, 'store'])->middleware('throttle:gis-import')->name('import.store');
        Route::post('import/{id}/terima', [ImportController::class, 'accept'])->where('id', $uuid)->name('import.terima');
        Route::post('import/{id}/batal', [ImportController::class, 'cancel'])->where('id', $uuid)->name('import.batal');
    });

    // ---- Spatial QA (SRS-GIS-27/28) ---------------------------------------------
    Route::middleware('can:gis.kualiti.lihat')->group(function () {
        Route::get('kualiti', [KualitiController::class, 'index'])->name('kualiti.index');
        Route::get('kualiti/ciri', [KualitiController::class, 'features'])->name('kualiti.ciri');
        Route::get('kualiti/eksport', [KualitiController::class, 'export'])->name('kualiti.eksport');
        Route::get('kualiti/{id}/cadangan', [KualitiController::class, 'suggestion'])->whereNumber('id')->name('kualiti.cadangan');
    });
    Route::middleware('can:gis.kualiti.kemas_kini')->group(function () {
        Route::post('kualiti/jalankan', [KualitiController::class, 'run'])->name('kualiti.jalankan');
        Route::put('kualiti/{id}', [KualitiController::class, 'update'])->whereNumber('id')->name('kualiti.update');
    });

    // ---- Layer administration + metadata (SRS-GIS-12/29/32) ----------------------
    Route::get('metadata', [LapisanController::class, 'metadataReport'])->name('metadata');
    Route::middleware('can:gis.lapisan.kemas_kini')->group(function () use ($key) {
        Route::get('pentadbiran/lapisan', [LapisanController::class, 'index'])->name('pentadbiran.lapisan');
        Route::put('pentadbiran/lapisan/{key}', [LapisanController::class, 'update'])->where('key', $key)->name('pentadbiran.lapisan.update');
        Route::put('pentadbiran/lapisan/{key}/metadata', [LapisanController::class, 'metadata'])->where('key', $key)->name('pentadbiran.lapisan.metadata');
    });

    // ---- Physical asset points (layer 10) ----------------------------------------
    Route::post('aset', [AsetController::class, 'store'])->middleware('can:gis.aset.cipta')->name('aset.store');
    Route::put('aset/{id}', [AsetController::class, 'update'])->whereNumber('id')->middleware('can:gis.aset.kemas_kini')->name('aset.update');
    Route::delete('aset/{id}', [AsetController::class, 'destroy'])->whereNumber('id')->middleware('can:gis.aset.kemas_kini')->name('aset.destroy');

    // ---- OGC proxy (SRS-GIS-25) --------------------------------------------------
    Route::middleware('can:gis.ogc.lihat')->group(function () {
        Route::get('ogc', [OgcController::class, 'info'])->name('ogc.info');
        Route::get('ogc/{service}', [OgcController::class, 'proxy'])->where('service', 'wms|wfs|WMS|WFS')->middleware('throttle:gis-ogc')->name('ogc');
    });
});
