<?php

use App\Modules\Dsh\Http\Controllers\AiController;
use App\Modules\Dsh\Http\Controllers\DashboardController;
use App\Modules\Dsh\Http\Controllers\PeribadiController;
use App\Modules\Dsh\Http\Controllers\TetapanController;
use Illuminate\Support\Facades\Route;

/*
| MOD-DSH · /api/v1/dsh. Every route authorises through MOD-RBC (`can:`);
| each KPI is checked again against its own permission and computed under
| the user's scope. Personal records (layout, views, drafts, snapshots,
| subscriptions) are owner-only.
*/

$uuid = '[0-9a-fA-F-]{36}';

Route::middleware('auth:sanctum')->group(function () use ($uuid) {
    // Internal portal + online help: every signed-in user (SRS-DSH-19/25).
    Route::get('portal', [DashboardController::class, 'portal'])->name('portal');
    Route::get('bantuan', [PeribadiController::class, 'help'])->name('bantuan');

    Route::middleware('can:dsh.dashboard.lihat')->group(function () use ($uuid) {
        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('kpi', [DashboardController::class, 'kpi'])->name('kpi');
        Route::get('kpi/{key}/senarai', [DashboardController::class, 'drill'])->where('key', '[a-z0-9_]+\.[a-z0-9_.]+')->name('kpi.senarai');
        Route::get('penapis/pilihan', [DashboardController::class, 'options'])->name('penapis');
        Route::get('eksport', [DashboardController::class, 'export'])->middleware(['can:dsh.dashboard.eksport', 'throttle:20,1'])->name('eksport');

        Route::get('susun-atur', [PeribadiController::class, 'layout'])->name('susun_atur');
        Route::put('susun-atur', [PeribadiController::class, 'saveLayout'])->name('susun_atur.simpan');
        Route::delete('susun-atur', [PeribadiController::class, 'resetLayout'])->name('susun_atur.set_semula');

        Route::get('kegemaran', [PeribadiController::class, 'views'])->name('kegemaran');
        Route::post('kegemaran', [PeribadiController::class, 'saveView'])->name('kegemaran.simpan');
        Route::delete('kegemaran/{id}', [PeribadiController::class, 'deleteView'])->whereNumber('id')->name('kegemaran.padam');

        Route::get('draf', [PeribadiController::class, 'drafts'])->name('draf');
        Route::post('draf', [PeribadiController::class, 'saveDraft'])->name('draf.simpan');
        Route::get('draf/{id}', [PeribadiController::class, 'draft'])->where('id', $uuid)->name('draf.lihat');
        Route::put('draf/{id}', [PeribadiController::class, 'saveDraft'])->where('id', $uuid)->name('draf.kemas_kini');
        Route::delete('draf/{id}', [PeribadiController::class, 'deleteDraft'])->where('id', $uuid)->name('draf.padam');

        Route::get('inbox', [PeribadiController::class, 'inbox'])->name('inbox');

        Route::get('snapshot', [PeribadiController::class, 'snapshots'])->name('snapshot');
        Route::post('snapshot', [PeribadiController::class, 'takeSnapshot'])->middleware('can:dsh.dashboard.eksport')->name('snapshot.cipta');
        Route::get('snapshot/{id}', [PeribadiController::class, 'snapshot'])->whereNumber('id')->name('snapshot.lihat');
        Route::get('snapshot/{id}/eksport', [PeribadiController::class, 'exportSnapshot'])->whereNumber('id')->middleware(['can:dsh.dashboard.eksport', 'throttle:20,1'])->name('snapshot.eksport');

        Route::middleware('can:dsh.langganan.cipta')->group(function () {
            Route::get('langganan', [PeribadiController::class, 'subscriptions'])->name('langganan');
            Route::put('langganan', [PeribadiController::class, 'subscribe'])->name('langganan.simpan');
            Route::delete('langganan/{id}', [PeribadiController::class, 'unsubscribe'])->whereNumber('id')->name('langganan.padam');
        });

        Route::middleware('can:dsh.ai.lihat')->group(function () {
            Route::post('ai/prompt', [AiController::class, 'prompt'])->middleware('throttle:30,1')->name('ai.prompt');
            Route::get('ai/insight', [AiController::class, 'insights'])->name('ai.insight');
            Route::get('ai/log', [AiController::class, 'log'])->name('ai.log');
        });

        Route::middleware('can:dsh.widget.kemas_kini')->prefix('tetapan')->name('tetapan.')->group(function () {
            Route::get('/', [TetapanController::class, 'index'])->name('index');
            Route::put('widget/{kod}', [TetapanController::class, 'saveWidget'])->where('kod', '[a-z]+:[a-z0-9_.]+')->name('widget');
            Route::delete('widget/{kod}', [TetapanController::class, 'deleteWidget'])->where('kod', '[a-z]+:[a-z0-9_.]+')->name('widget.padam');
            Route::put('ambang/{kod}', [TetapanController::class, 'saveThreshold'])->where('kod', '[a-z0-9_]+\.[a-z0-9_.]+')->name('ambang');
            Route::delete('ambang/{kod}', [TetapanController::class, 'deleteThreshold'])->where('kod', '[a-z0-9_]+\.[a-z0-9_.]+')->name('ambang.padam');
        });
    });
});
