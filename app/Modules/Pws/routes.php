<?php

use App\Modules\Pws\Http\Controllers\Admin\KandunganController;
use App\Modules\Pws\Http\Controllers\Admin\KlienController;
use App\Modules\Pws\Http\Controllers\Admin\PemantauanController;
use App\Modules\Pws\Http\Controllers\Admin\StatistikController;
use App\Modules\Pws\Http\Controllers\Awam\AduanController;
use App\Modules\Pws\Http\Controllers\Awam\AnalitikController;
use App\Modules\Pws\Http\Controllers\Awam\BayaranController;
use App\Modules\Pws\Http\Controllers\Awam\MaklumatController;
use App\Modules\Pws\Http\Controllers\Awam\PermohonanController;
use App\Modules\Pws\Http\Controllers\Awam\SoalanLazimController;
use App\Modules\Pws\Http\Controllers\Awam\SokonganController;
use App\Modules\Pws\Http\Controllers\Awam\TokenController;
use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/pws — MOD-PWS.
|
| awam/*  Public API v1 for Portal Hartanah Wakaf Selangor (a SEPARATE
|         WordPress system, SRS-PWS-02). Machine-to-machine only: client
|         credentials → short-lived bearer token, scope per route, IP
|         allowlist, per-client + per-end-user rate limits, CORS for the
|         portal origin only, integration log. Only Awam data leaves here.
| others  SISDATA admin (Sanctum session) — authorised through MOD-RBC.
*/

Route::prefix('awam')->name('awam.')->group(function () {
    Route::post('token', TokenController::class)->middleware('throttle:30,1')->name('token');

    $k = AuthenticateKlien::class;
    Route::middleware($k.':baca')->group(function () {
        Route::get('hartanah', [MaklumatController::class, 'hartanah'])->name('hartanah');
        Route::get('agihan', [MaklumatController::class, 'agihan'])->name('agihan');
        Route::get('kandungan', [MaklumatController::class, 'kandungan'])->name('kandungan');
        Route::get('kandungan/{slug}', [MaklumatController::class, 'kandunganSatu'])->where('slug', '[a-z0-9\-]+')->name('kandungan.satu');
        Route::get('statistik', [MaklumatController::class, 'statistik'])->name('statistik');
        Route::get('soalan-lazim', [SoalanLazimController::class, 'carian'])->name('soalan_lazim');
        Route::post('soalan-lazim/tanya', [SoalanLazimController::class, 'tanya'])->name('soalan_lazim.tanya');
    });
    Route::middleware($k.':permohonan')->group(function () {
        Route::post('permohonan/wakaf', [PermohonanController::class, 'wakaf'])->name('permohonan.wakaf');
        Route::post('permohonan/sewaan', [PermohonanController::class, 'sewaan'])->name('permohonan.sewaan');
        Route::post('permohonan/status', [PermohonanController::class, 'status'])->name('permohonan.status');
        Route::get('permohonan/perubahan', [PermohonanController::class, 'perubahan'])->name('permohonan.perubahan');
    });
    Route::middleware($k.':bayaran')->group(function () {
        Route::get('bayaran/saluran', [BayaranController::class, 'saluran'])->name('bayaran.saluran');
        Route::post('bayaran', [BayaranController::class, 'store'])->name('bayaran.store');
        Route::get('bayaran/{rujukan}', [BayaranController::class, 'show'])->where('rujukan', '[A-Za-z0-9\-]+')->name('bayaran.show');
        Route::post('bayaran/{rujukan}/simulasi', [BayaranController::class, 'simulasi'])->where('rujukan', '[A-Za-z0-9\-]+')->name('bayaran.simulasi');
    });
    Route::middleware($k.':aduan')->group(function () {
        Route::get('aduan/rujukan', [AduanController::class, 'rujukan'])->name('aduan.rujukan');
        Route::post('aduan', [AduanController::class, 'store'])->name('aduan.store');
        Route::post('aduan/semak', [AduanController::class, 'semak'])->name('aduan.semak');
    });
    Route::post('sokongan', SokonganController::class)->middleware($k.':sokongan')->name('sokongan');
    Route::post('analitik', AnalitikController::class)->middleware($k.':analitik')->name('analitik');
});

Route::middleware('auth:sanctum')->group(function () {
    // API clients of the gateway (SRS-PWS-23).
    Route::get('klien', [KlienController::class, 'index'])->middleware('can:pws.klien.lihat')->name('klien.index');
    Route::middleware('can:pws.klien.kemas_kini')->group(function () {
        Route::post('klien', [KlienController::class, 'store'])->name('klien.store');
        Route::put('klien/{klien}', [KlienController::class, 'update'])->whereNumber('klien')->name('klien.update');
        Route::post('klien/{klien}/putar', [KlienController::class, 'rotate'])->whereNumber('klien')->name('klien.putar');
        Route::post('klien/{klien}/batal', [KlienController::class, 'revoke'])->whereNumber('klien')->name('klien.batal');
    });

    // Content governance + AI drafting (SRS-PWS-04).
    Route::middleware('can:pws.kandungan.lihat')->group(function () {
        Route::get('kandungan', [KandunganController::class, 'index'])->name('kandungan.index');
        Route::get('kandungan/{kandungan}', [KandunganController::class, 'show'])->whereNumber('kandungan')->name('kandungan.show');
        Route::get('status-ai', [KandunganController::class, 'statusAi'])->name('status_ai');
    });
    Route::middleware('can:pws.kandungan.cipta')->group(function () {
        Route::post('kandungan', [KandunganController::class, 'store'])->name('kandungan.store');
        Route::put('kandungan/{kandungan}', [KandunganController::class, 'update'])->whereNumber('kandungan')->name('kandungan.update');
        Route::post('kandungan/jana-ai', [KandunganController::class, 'janaAi'])->name('kandungan.jana_ai');
        Route::post('kandungan/{kandungan}/hantar', [KandunganController::class, 'submit'])->whereNumber('kandungan')->name('kandungan.hantar');
    });
    Route::post('kandungan/{kandungan}/tarik', [KandunganController::class, 'withdraw'])->whereNumber('kandungan')->middleware('can:pws.kandungan.kemas_kini')->name('kandungan.tarik');

    // Public statistics publication.
    Route::middleware('can:pws.statistik.lihat')->group(function () {
        Route::get('statistik', [StatistikController::class, 'index'])->name('statistik.index');
        Route::get('statistik/pratonton', [StatistikController::class, 'preview'])->name('statistik.pratonton');
    });
    Route::middleware('can:pws.statistik.cipta')->group(function () {
        Route::post('statistik', [StatistikController::class, 'store'])->name('statistik.store');
        Route::post('statistik/{statistik}/hantar', [StatistikController::class, 'submit'])->whereNumber('statistik')->name('statistik.hantar');
        Route::post('statistik/{statistik}/tarik', [StatistikController::class, 'withdraw'])->whereNumber('statistik')->name('statistik.tarik');
    });

    // Monitoring (SRS-PWS-22/27).
    Route::middleware('can:pws.pemantauan.lihat')->group(function () {
        Route::get('ringkasan', [PemantauanController::class, 'ringkasan'])->name('ringkasan');
        Route::get('permohonan', [PemantauanController::class, 'permohonan'])->name('permohonan.index');
        Route::get('log-integrasi', [PemantauanController::class, 'log'])->name('log');
        Route::get('bayaran', [PemantauanController::class, 'bayaran'])->name('bayaran.index');
        Route::get('analitik', [PemantauanController::class, 'analitik'])->name('analitik.index');
    });
    Route::post('permohonan/cuba-semula', [PemantauanController::class, 'cubaSemula'])->middleware('can:pws.pemantauan.kemas_kini')->name('permohonan.cuba_semula');
});
