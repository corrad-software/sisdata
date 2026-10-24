<?php

use App\Modules\Dms\Http\Controllers\DokumenController;
use App\Modules\Dms\Http\Controllers\FailController;
use App\Modules\Dms\Http\Controllers\KategoriController;
use App\Modules\Dms\Http\Controllers\KebenaranController;
use App\Modules\Dms\Http\Controllers\KitaranHayatController;
use App\Modules\Dms\Http\Controllers\KonfigurasiController;
use App\Modules\Dms\Http\Controllers\KuarantinController;
use App\Modules\Dms\Http\Controllers\VersiController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/dms — SRS-DMS-18: every document function is exposed here.
| Authorisation: `dms.boleh:<permission>` (MOD-RBC AccessControl) on every
| route, plus record-level access (classification / scope / grants) inside.
*/

Route::middleware('auth:sanctum')->group(function () {
    $lihat = 'dms.boleh:dms.dokumen.lihat';
    $cipta = 'dms.boleh:dms.dokumen.cipta';

    // Library, search, detail, metadata.
    Route::get('dokumen', [DokumenController::class, 'index'])->middleware($lihat)->name('dokumen.index');
    Route::post('dokumen', [DokumenController::class, 'store'])->middleware($cipta)->name('dokumen.store');
    Route::get('dokumen/{dokumen}', [DokumenController::class, 'show'])->middleware($lihat)->name('dokumen.show');
    Route::put('dokumen/{dokumen}', [DokumenController::class, 'update'])->middleware('dms.boleh:dms.dokumen.kemas_kini')->name('dokumen.update');
    Route::delete('dokumen/{dokumen}', [DokumenController::class, 'destroy'])->middleware('dms.boleh:dms.dokumen.padam')->name('dokumen.destroy');
    Route::get('dokumen/{dokumen}/teks', [DokumenController::class, 'text'])->middleware($lihat)->name('dokumen.teks');
    Route::post('dokumen/{dokumen}/indeks-semula', [DokumenController::class, 'reindex'])->middleware('dms.boleh:dms.dokumen.kemas_kini')->name('dokumen.indeks');

    // Two-way linking with any record (SRS-DMS-16/17).
    Route::get('rekod/{jenis}/{id}/dokumen', [DokumenController::class, 'forRecord'])->middleware($lihat)->name('rekod.index');
    Route::post('rekod/{jenis}/{id}/dokumen', [DokumenController::class, 'storeForRecord'])->middleware($cipta)->name('rekod.store');

    // Versions (SRS-DMS-14).
    Route::get('dokumen/{dokumen}/versi', [VersiController::class, 'index'])->middleware($lihat)->name('versi.index');
    Route::post('dokumen/{dokumen}/versi', [VersiController::class, 'store'])->middleware($cipta)->name('versi.store');
    Route::get('dokumen/{dokumen}/versi/banding', [VersiController::class, 'compare'])->middleware($lihat)->name('versi.banding');

    // Short-lived signed links (SRS-DMS-22 security control) and what they serve.
    Route::post('dokumen/{dokumen}/pautan', [FailController::class, 'link'])->middleware($lihat)->name('pautan');
    Route::get('fail/{dokumen}/{jenis}', [FailController::class, 'serve'])->middleware(['signed:relative', $lihat])->name('fail');

    // Explicit per-user / per-role grants on Sulit/Rahsia documents (SRS-DMS-19).
    Route::get('dokumen/{dokumen}/kebenaran', [KebenaranController::class, 'index'])->middleware($lihat)->name('kebenaran.index');
    Route::post('dokumen/{dokumen}/kebenaran', [KebenaranController::class, 'store'])->middleware($lihat)->name('kebenaran.store');
    Route::delete('dokumen/{dokumen}/kebenaran/{kebenaran}', [KebenaranController::class, 'destroy'])->middleware($lihat)->name('kebenaran.destroy');

    // Lifecycle: archive (SRS-DMS-22), disposal via WFE (SRS-DMS-23).
    Route::post('dokumen/{dokumen}/arkib', [KitaranHayatController::class, 'archive'])->middleware('dms.boleh:dms.arkib.kemas_kini')->name('arkib');
    Route::post('dokumen/{dokumen}/pelupusan', [KitaranHayatController::class, 'requestDisposal'])->middleware('dms.boleh:dms.pelupusan.cipta')->name('pelupusan.store');
    Route::get('pelupusan', [KitaranHayatController::class, 'disposals'])->middleware('dms.boleh:dms.pelupusan.cipta')->name('pelupusan.index');

    // Configuration (SRS-DMS-03/05/11/21) — categories & metadata fields are DMS-owned;
    // whitelist / global limits / quota are ADM parameters (shown read-only here).
    Route::get('kategori', [KategoriController::class, 'index'])->middleware($lihat)->name('kategori.index');
    Route::post('kategori', [KategoriController::class, 'store'])->middleware('dms.boleh:dms.konfigurasi.kemas_kini')->name('kategori.store');
    Route::put('kategori/{kategori}', [KategoriController::class, 'update'])->middleware('dms.boleh:dms.konfigurasi.kemas_kini')->name('kategori.update');
    Route::get('medan', [KategoriController::class, 'fields'])->middleware($lihat)->name('medan.index');
    Route::post('medan', [KategoriController::class, 'storeField'])->middleware('dms.boleh:dms.konfigurasi.kemas_kini')->name('medan.store');
    Route::put('medan/{medan}', [KategoriController::class, 'updateField'])->middleware('dms.boleh:dms.konfigurasi.kemas_kini')->name('medan.update');
    Route::get('konfigurasi/muat-naik', [KonfigurasiController::class, 'upload'])->middleware($cipta)->name('konfigurasi.muat_naik');
    Route::get('konfigurasi', [KonfigurasiController::class, 'show'])->middleware('dms.boleh:dms.konfigurasi.lihat')->name('konfigurasi.show');

    // Quota (SRS-DMS-25) and quarantine (SRS-DMS-07/08).
    Route::get('kuota', [KonfigurasiController::class, 'quota'])->middleware('dms.boleh:dms.kuota.lihat')->name('kuota');
    Route::get('kuarantin', [KuarantinController::class, 'index'])->middleware('dms.boleh:dms.kuarantin.lihat')->name('kuarantin.index');
    Route::delete('kuarantin/{kuarantin}', [KuarantinController::class, 'destroy'])->middleware('dms.boleh:dms.kuarantin.padam')->name('kuarantin.destroy');
});
