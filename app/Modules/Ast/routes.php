<?php

use App\Modules\Ast\Http\Controllers\AsetController;
use App\Modules\Ast\Http\Controllers\KonfigurasiController;
use App\Modules\Ast\Http\Controllers\KontraktorController;
use App\Modules\Ast\Http\Controllers\LaporanController;
use App\Modules\Ast\Http\Controllers\MediaController;
use App\Modules\Ast\Http\Controllers\PelupusanController;
use App\Modules\Ast\Http\Controllers\PemeriksaanController;
use App\Modules\Ast\Http\Controllers\PenyelenggaraanController;
use App\Modules\Ast\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/ast — MOD-AST · Inventori Aset Fizikal. Every route authorises
| through MOD-RBC (`can:` → AccessControl); asset records are scoped by the
| parent lot's daerah / mukim / klasifikasi (ScopedByAccess) so an
| out-of-scope asset is a 404. `awam/qr/*` is the public QR scan endpoint
| (no account: minimum identification only, rate-limited, every scan logged).
*/

Route::post('awam/qr/{ref}/imbas', [QrController::class, 'imbas'])->where('ref', '[0-9A-Za-z]{1,24}')->middleware('throttle:30,1')->name('awam.qr.imbas');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', [AsetController::class, 'rujukan'])->name('rujukan');

    Route::middleware('can:ast.aset.lihat')->group(function () {
        Route::get('ringkasan', [AsetController::class, 'ringkasan'])->name('ringkasan');
        Route::get('aset', [AsetController::class, 'index'])->name('aset.index');
        Route::get('tag', [AsetController::class, 'tag'])->name('tag');
        Route::prefix('aset/{aset}')->whereUuid('aset')->name('aset.')->group(function () {
            Route::get('/', [AsetController::class, 'show'])->name('show');
            Route::get('nilai', [AsetController::class, 'sejarahNilai'])->name('nilai.index');
            Route::get('sejarah', [AsetController::class, 'sejarah'])->name('sejarah');
            Route::get('qr.svg', [QrController::class, 'svg'])->name('qr.svg');
            Route::get('gambar', [MediaController::class, 'gambar'])->name('gambar.index');
            Route::get('gambar/{gambar}/url', [MediaController::class, 'urlGambar'])->whereNumber('gambar')->name('gambar.url');
            Route::get('dokumen', [MediaController::class, 'dokumen'])->name('dokumen.index');
            Route::get('dokumen/{dokumen}/url', [MediaController::class, 'urlDokumen'])->whereNumber('dokumen')->name('dokumen.url');
            Route::get('insurans', [MediaController::class, 'insurans'])->name('insurans.index');
            Route::get('pelupusan', [PelupusanController::class, 'index'])->name('pelupusan.index');
            Route::get('pemeriksaan', [PemeriksaanController::class, 'index'])->middleware('can:ast.pemeriksaan.lihat')->name('pemeriksaan.index');
            Route::get('penyelenggaraan', [PenyelenggaraanController::class, 'untukAset'])->middleware('can:ast.penyelenggaraan.lihat')->name('penyelenggaraan');
            Route::get('imbasan', [QrController::class, 'imbasan'])->middleware('can:ast.imbasan.lihat')->name('imbasan');
        });
    });

    Route::middleware('can:ast.aset.cipta')->group(function () {
        Route::post('aset', [AsetController::class, 'store'])->name('aset.store');
    });
    Route::get('cari-lot', [AsetController::class, 'cariLot'])->middleware('can:ast.aset.cipta')->name('cari_lot');
    Route::get('pegawai', [AsetController::class, 'pegawai'])->middleware('can:ast.aset.kemas_kini')->name('pegawai');
    Route::get('premis', [AsetController::class, 'premis'])->middleware('can:ast.aset.kemas_kini')->name('premis');

    Route::middleware('can:ast.aset.kemas_kini')->prefix('aset/{aset}')->whereUuid('aset')->name('aset.')->group(function () {
        Route::put('/', [AsetController::class, 'update'])->name('update');
        Route::post('kondisi', [AsetController::class, 'kondisi'])->name('kondisi');
        Route::post('gambar', [MediaController::class, 'simpanGambar'])->name('gambar.store');
        Route::post('dokumen', [MediaController::class, 'simpanDokumen'])->name('dokumen.store');
        Route::post('insurans', [MediaController::class, 'simpanInsurans'])->name('insurans.store');
        Route::post('jaminan', [MediaController::class, 'simpanJaminan'])->name('jaminan.store');
    });
    // Security control: only Bahagian Kewangan / authorised Unit Aset change values (never wildcard-granted).
    Route::post('aset/{aset}/nilai', [AsetController::class, 'nilai'])->whereUuid('aset')->middleware('can:ast.sulit.kemas_kini')->name('aset.nilai.store');
    Route::post('aset/{aset}/pelupusan', [PelupusanController::class, 'store'])->whereUuid('aset')->middleware('can:ast.aset.padam')->name('aset.pelupusan.store');
    Route::post('aset/{aset}/pemeriksaan', [PemeriksaanController::class, 'store'])->whereUuid('aset')->middleware('can:ast.pemeriksaan.cipta')->name('aset.pemeriksaan.store');
    Route::post('aset/{aset}/jadual', [PenyelenggaraanController::class, 'simpanJadual'])->whereUuid('aset')->middleware('can:ast.penyelenggaraan.cipta')->name('aset.jadual.store');

    Route::middleware('can:ast.qr.eksport')->post('label', [QrController::class, 'label'])->name('label');
    Route::middleware('can:ast.qr.kemas_kini')->group(function () {
        Route::post('aset/{aset}/qr/jana-semula', [QrController::class, 'janaSemula'])->whereUuid('aset')->name('qr.jana_semula');
        Route::post('qr/{ref}/status', [QrController::class, 'status'])->where('ref', '[0-9A-Za-z]{1,24}')->name('qr.status');
    });

    // Maintenance: officers by lot scope; contractors (ast.kerja.kemas_kini) only their own work orders.
    Route::middleware('can:ast.penyelenggaraan.lihat')->group(function () {
        Route::get('jadual', [PenyelenggaraanController::class, 'jadual'])->name('jadual.index');
        Route::get('ptn/keperluan', [PenyelenggaraanController::class, 'keperluanPtn'])->name('ptn.keperluan');
    });
    Route::put('jadual/{jadual}', [PenyelenggaraanController::class, 'kemaskiniJadual'])->whereNumber('jadual')->middleware('can:ast.penyelenggaraan.kemas_kini')->name('jadual.update');
    Route::middleware('can:ast.penyelenggaraan.cipta')->group(function () {
        Route::post('kerja', [PenyelenggaraanController::class, 'simpanKerja'])->name('kerja.store');
        Route::post('ptn/tarik', [PenyelenggaraanController::class, 'tarikPtn'])->name('ptn.tarik');
        Route::post('ptn/keperluan/{keperluan}/paut', [PenyelenggaraanController::class, 'pautPtn'])->whereNumber('keperluan')->name('ptn.paut');
    });
    // Officers (ast.penyelenggaraan.lihat) or contractors (ast.kerja.kemas_kini) — checked in the controller (Support\Akses).
    Route::group([], function () {
        Route::get('kalendar', [PenyelenggaraanController::class, 'kalendar'])->name('kalendar');
        Route::get('kerja', [PenyelenggaraanController::class, 'kerja'])->name('kerja.index');
        Route::get('kerja/{kerja}', [PenyelenggaraanController::class, 'showKerja'])->whereUuid('kerja')->name('kerja.show');
        Route::put('kerja/{kerja}', [PenyelenggaraanController::class, 'kemaskiniKerja'])->whereUuid('kerja')->name('kerja.update');
        Route::post('kerja/{kerja}/gambar', [PenyelenggaraanController::class, 'gambarKerja'])->whereUuid('kerja')->name('kerja.gambar');
    });

    Route::get('kontraktor', [KontraktorController::class, 'index'])->middleware('can:ast.kontraktor.lihat')->name('kontraktor.index');
    Route::middleware('can:ast.kontraktor.kemas_kini')->group(function () {
        Route::post('kontraktor', [KontraktorController::class, 'store'])->name('kontraktor.store');
        Route::put('kontraktor/{kontraktor}', [KontraktorController::class, 'update'])->whereNumber('kontraktor')->name('kontraktor.update');
    });

    // SRS-AST-20 field app (PWA, offline) API.
    Route::prefix('lapangan')->name('lapangan.')->group(function () {
        Route::get('bundle', [PemeriksaanController::class, 'bundle'])->middleware('can:ast.lapangan.lihat')->name('bundle');
        Route::get('pemeriksaan', [PemeriksaanController::class, 'saya'])->middleware('can:ast.lapangan.lihat')->name('saya');
        Route::middleware(['can:ast.lapangan.kemas_kini', 'throttle:240,1'])->group(function () {
            Route::post('pemeriksaan', [PemeriksaanController::class, 'segerak'])->name('pemeriksaan.store');
            Route::post('pemeriksaan/{pemeriksaan}/gambar', [PemeriksaanController::class, 'gambar'])->whereUuid('pemeriksaan')->name('pemeriksaan.gambar');
        });
    });

    Route::middleware('can:ast.laporan.lihat')->prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/', [LaporanController::class, 'index'])->name('index');
        Route::get('{laporan}', [LaporanController::class, 'show'])->name('show');
        Route::get('{laporan}/eksport', [LaporanController::class, 'eksport'])->middleware('can:ast.laporan.eksport')->name('eksport');
    });

    Route::get('kategori', [KonfigurasiController::class, 'index'])->middleware('can:ast.konfigurasi.lihat')->name('kategori.index');
    Route::middleware('can:ast.konfigurasi.kemas_kini')->group(function () {
        Route::post('kategori', [KonfigurasiController::class, 'store'])->name('kategori.store');
        Route::put('kategori/{kategori}', [KonfigurasiController::class, 'update'])->whereNumber('kategori')->name('kategori.update');
    });
});
