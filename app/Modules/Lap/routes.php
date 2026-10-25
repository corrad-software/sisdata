<?php

use App\Modules\Lap\Http\Controllers\AiController;
use App\Modules\Lap\Http\Controllers\AnalitikController;
use App\Modules\Lap\Http\Controllers\BiController;
use App\Modules\Lap\Http\Controllers\DefinisiController;
use App\Modules\Lap\Http\Controllers\JadualController;
use App\Modules\Lap\Http\Controllers\KatalogController;
use App\Modules\Lap\Http\Controllers\LaporanController;
use App\Modules\Lap\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

/*
| MOD-LAP · /api/v1/lap. Every route authorises through MOD-RBC (`can:`);
| each report is checked again for the reader (dataset permission, report
| permission / roles) and executed under the reader's row scope by the
| database (laporan.* views).
*/

$uuid = '[0-9a-fA-F-]{36}';

Route::middleware(['auth:sanctum', 'can:lap.laporan.lihat'])->group(function () use ($uuid) {
    Route::get('katalog', [KatalogController::class, 'index'])->name('katalog');
    Route::get('katalog/{kod}', [KatalogController::class, 'show'])->where('kod', '[A-Za-z0-9_\-]+')->name('katalog.show');
    Route::put('katalog/{kod}', [KatalogController::class, 'update'])->where('kod', '[A-Za-z0-9_\-]+')->middleware('can:lap.katalog.kemas_kini')->name('katalog.update');
    Route::get('set-data', [KatalogController::class, 'sets'])->name('set_data');
    Route::get('kamus', [KatalogController::class, 'kamus'])->name('kamus');

    Route::post('jana', [LaporanController::class, 'run'])->middleware('throttle:120,1')->name('jana');
    Route::middleware(['can:lap.laporan.eksport', 'throttle:20,1'])->group(function () {
        Route::post('eksport', [LaporanController::class, 'export'])->name('eksport');
        Route::post('eksport/imej', [LaporanController::class, 'image'])->name('eksport.imej');
    });

    Route::get('definisi', [DefinisiController::class, 'index'])->name('definisi');
    Route::get('definisi/{id}', [DefinisiController::class, 'show'])->where('id', $uuid)->name('definisi.show');
    Route::middleware('can:lap.adhoc.cipta')->group(function () use ($uuid) {
        Route::post('definisi', [DefinisiController::class, 'store'])->name('definisi.store');
        Route::put('definisi/{id}', [DefinisiController::class, 'update'])->where('id', $uuid)->name('definisi.update');
        Route::delete('definisi/{id}', [DefinisiController::class, 'destroy'])->where('id', $uuid)->name('definisi.destroy');
        Route::post('ai/prompt', [AiController::class, 'prompt'])->middleware('throttle:30,1')->name('ai.prompt');
    });
    Route::get('carian', [AiController::class, 'search'])->middleware('throttle:60,1')->name('carian');

    Route::middleware('can:lap.jadual.cipta')->prefix('jadual')->name('jadual.')->group(function () {
        Route::get('/', [JadualController::class, 'index'])->name('index');
        Route::get('penerima', [JadualController::class, 'recipients'])->name('penerima');
        Route::post('/', [JadualController::class, 'store'])->name('store');
        Route::put('{id}', [JadualController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{id}', [JadualController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('{id}/jalan', [JadualController::class, 'runNow'])->whereNumber('id')->middleware('throttle:10,1')->name('jalan');
    });

    Route::get('log', [LogController::class, 'index'])->name('log');
    Route::get('log/{id}', [LogController::class, 'show'])->where('id', $uuid)->name('log.show');
    Route::get('log/{id}/muat-turun', [LogController::class, 'download'])->where('id', $uuid)->middleware('throttle:30,1')->name('log.muat_turun');

    Route::middleware('can:lap.analitik.lihat')->group(function () {
        Route::get('analitik', [AnalitikController::class, 'index'])->name('analitik');
        Route::get('analitik/ringkasan', [AnalitikController::class, 'summary'])->name('analitik.ringkasan');
        Route::post('analitik/jalankan', [AnalitikController::class, 'run'])->middleware(['can:lap.analitik.kemas_kini', 'throttle:5,1'])->name('analitik.jalankan');
        Route::get('cadangan', [AnalitikController::class, 'recommendations'])->name('cadangan');
        Route::put('cadangan/{id}/keputusan', [AnalitikController::class, 'decide'])->whereNumber('id')->middleware('can:lap.analitik.lulus')->name('cadangan.keputusan');
    });

    Route::middleware('can:lap.insight.lihat')->group(function () {
        Route::get('insight', [AnalitikController::class, 'insights'])->name('insight');
        Route::post('insight', [AnalitikController::class, 'generateInsight'])->middleware('throttle:10,1')->name('insight.jana');
    });

    Route::middleware('can:lap.bi.lihat')->prefix('bi')->name('bi.')->group(function () {
        Route::get('/', [BiController::class, 'index'])->name('index');
        Route::get('{kod}/embed', [BiController::class, 'embed'])->where('kod', '[a-z0-9_\-]+')->middleware('throttle:60,1')->name('embed');
        Route::middleware('can:lap.bi.kemas_kini')->group(function () {
            Route::post('/', [BiController::class, 'store'])->name('store');
            Route::put('{id}', [BiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [BiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });
});
