<?php

use App\Modules\Kba\Http\Controllers\ArtikelController;
use App\Modules\Kba\Http\Controllers\PentadbiranController;
use App\Modules\Kba\Http\Controllers\SumberController;
use App\Modules\Kba\Http\Controllers\TanyaController;
use Illuminate\Support\Facades\Route;

/*
| MOD-KBA · /api/v1/kba. Every route authorises through MOD-RBC (`can:`);
| passages are additionally filtered per user at retrieval (Capaian). The AI
| prompt has its own per-user / per-role rate limit (HadKadar) on top of the
| route throttle.
*/

$uuid = '[0-9a-fA-F-]{36}';

Route::middleware('auth:sanctum')->group(function () use ($uuid) {
    Route::middleware('can:kba.soalan.lihat')->group(function () use ($uuid) {
        Route::post('tanya', [TanyaController::class, 'tanya'])->middleware('throttle:60,1')->name('tanya');
        Route::get('carian', [TanyaController::class, 'carian'])->middleware('throttle:120,1')->name('carian');
        Route::get('status-ai', [TanyaController::class, 'status'])->name('status_ai');
        Route::get('sumber/{id}/kandungan', [TanyaController::class, 'kandungan'])->whereNumber('id')->name('sumber.kandungan');
        Route::get('perbualan', [TanyaController::class, 'perbualan'])->name('perbualan');
        Route::get('perbualan/{id}', [TanyaController::class, 'perbualanSatu'])->where('id', $uuid)->name('perbualan.lihat');
        Route::delete('perbualan/{id}', [TanyaController::class, 'sembunyiPerbualan'])->where('id', $uuid)->name('perbualan.sembunyi');
        Route::post('log/{id}/maklum-balas', [TanyaController::class, 'maklumBalas'])->whereNumber('id')->name('maklum_balas.hantar');

        Route::get('artikel', [ArtikelController::class, 'index'])->name('artikel');
        Route::get('artikel/{id}', [ArtikelController::class, 'show'])->whereNumber('id')->name('artikel.lihat');
    });

    Route::post('artikel', [ArtikelController::class, 'store'])->middleware('can:kba.artikel.cipta')->name('artikel.cipta');
    Route::middleware('can:kba.artikel.kemas_kini')->group(function () {
        Route::put('artikel/{id}', [ArtikelController::class, 'update'])->whereNumber('id')->name('artikel.kemas_kini');
        Route::post('artikel/{id}/hantar', [ArtikelController::class, 'hantar'])->whereNumber('id')->name('artikel.hantar');
        Route::post('artikel/{id}/lapuk', [ArtikelController::class, 'lapuk'])->whereNumber('id')->name('artikel.lapuk');
        Route::get('artikel/{id}/versi', [ArtikelController::class, 'versi'])->whereNumber('id')->name('artikel.versi');
    });
    Route::get('keluaran', [ArtikelController::class, 'keluaran'])->middleware('can:kba.artikel.kemas_kini')->name('keluaran');
    Route::post('keluaran', [ArtikelController::class, 'simpanKeluaran'])->middleware('can:kba.keluaran.kemas_kini')->name('keluaran.simpan');

    Route::middleware('can:kba.sumber.lihat')->group(function () {
        Route::get('sumber', [SumberController::class, 'index'])->name('sumber');
        Route::get('status-indeks', [SumberController::class, 'status'])->name('status_indeks');
        Route::get('pemetaan', [SumberController::class, 'pemetaan'])->name('pemetaan');
    });
    Route::middleware('can:kba.sumber.kemas_kini')->group(function () {
        Route::post('sumber', [SumberController::class, 'store'])->middleware('throttle:30,1')->name('sumber.muat_naik');
        Route::post('sumber/dms', [SumberController::class, 'storeDms'])->name('sumber.dms');
        Route::put('sumber/{id}', [SumberController::class, 'update'])->whereNumber('id')->name('sumber.kemas_kini');
        Route::post('sumber/{id}/indeks-semula', [SumberController::class, 'reindex'])->whereNumber('id')->name('sumber.indeks_semula');
        Route::post('indeks-semula', [SumberController::class, 'reindexAll'])->middleware('throttle:5,1')->name('indeks_semula');
    });
    Route::put('pemetaan', [SumberController::class, 'simpanPemetaan'])->middleware('can:kba.pemetaan.kemas_kini')->name('pemetaan.simpan');

    Route::middleware('can:kba.analitik.lihat')->group(function () {
        Route::get('analitik', [PentadbiranController::class, 'analitik'])->name('analitik');
        Route::get('laporan/jurang', [PentadbiranController::class, 'jurang'])->name('laporan.jurang');
    });
    Route::middleware('can:kba.log_prompt.audit')->group(function () {
        Route::get('log-prompt', [PentadbiranController::class, 'logPrompt'])->name('log_prompt');
        Route::get('log-prompt/{id}', [PentadbiranController::class, 'logPromptSatu'])->whereNumber('id')->name('log_prompt.lihat');
    });
    Route::get('maklum-balas', [PentadbiranController::class, 'maklumBalas'])->middleware('can:kba.maklum_balas.lihat')->name('maklum_balas');
    Route::put('maklum-balas/{id}', [PentadbiranController::class, 'kemasKiniMaklumBalas'])->whereNumber('id')->middleware('can:kba.maklum_balas.kemas_kini')->name('maklum_balas.kemas_kini');
    Route::get('eksport', [PentadbiranController::class, 'eksport'])->middleware(['can:kba.kandungan.eksport', 'throttle:5,1'])->name('eksport');
    Route::middleware('can:kba.sokongan.lihat')->group(function () {
        Route::get('sokongan', [PentadbiranController::class, 'sokonganSenarai'])->name('sokongan');
        Route::get('sokongan/{modul}', [PentadbiranController::class, 'sokongan'])->name('sokongan.modul');
    });
});
