<?php

use App\Modules\Tsu\Http\Controllers\KosController;
use App\Modules\Tsu\Http\Controllers\LaporanController;
use App\Modules\Tsu\Http\Controllers\TemplatController;
use App\Modules\Tsu\Http\Controllers\UkurController;
use App\Modules\Tsu\Http\Controllers\UrusanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/tsu — MOD-TSU · Tukar Syarat, Pelupusan dan Ukur. Every route
| authorises through MOD-RBC (`can:` → AccessControl); urusan and all their
| child records are scoped by daerah / mukim / klasifikasi (ScopedByAccess),
| so an out-of-scope record is a 404. External surveyors (tsu.jurukur.*)
| only reach their own appointments.
*/

$uuid = '[0-9a-fA-F-]{36}';

Route::middleware('auth:sanctum')->group(function () use ($uuid) {
    Route::get('rujukan', [UrusanController::class, 'rujukan'])->name('rujukan');
    Route::get('cari-lot', [UrusanController::class, 'cariLot'])->middleware('can:tsu.urusan.cipta')->name('cari_lot');

    Route::middleware('can:tsu.urusan.lihat')->group(function () use ($uuid) {
        Route::get('urusan', [UrusanController::class, 'index'])->name('urusan.index');
        Route::get('urusan/{id}', [UrusanController::class, 'show'])->where('id', $uuid)->name('urusan.show');
        Route::get('urusan/{id}/dokumen/{dokumen}/url', [UrusanController::class, 'urlDokumen'])->where('id', $uuid)->whereNumber('dokumen')->name('urusan.dokumen.url');
        Route::get('urusan/{id}/pemindahan', [UrusanController::class, 'pemindahan'])->where('id', $uuid)->name('urusan.pemindahan');
    });
    Route::post('urusan', [UrusanController::class, 'store'])->middleware('can:tsu.urusan.cipta')->name('urusan.store');
    Route::middleware('can:tsu.urusan.kemas_kini')->group(function () use ($uuid) {
        Route::put('urusan/{id}', [UrusanController::class, 'update'])->where('id', $uuid)->name('urusan.update');
        Route::post('urusan/{id}/peringkat/selesai', [UrusanController::class, 'selesaiPeringkat'])->where('id', $uuid)->name('urusan.peringkat.selesai');
        Route::post('urusan/{id}/tutup', [UrusanController::class, 'tutup'])->where('id', $uuid)->name('urusan.tutup');
        Route::post('urusan/{id}/batal', [UrusanController::class, 'batal'])->where('id', $uuid)->name('urusan.batal');
        Route::post('urusan/{id}/dokumen', [UrusanController::class, 'muatNaik'])->where('id', $uuid)->name('urusan.dokumen.store');
    });

    // Land Office status (SRS-TSU-22)
    Route::middleware('can:tsu.ptd.kemas_kini')->group(function () use ($uuid) {
        Route::post('urusan/{id}/ptd/hantar', [UrusanController::class, 'ptdHantar'])->where('id', $uuid)->name('ptd.hantar');
        Route::post('urusan/{id}/ptd/segerak', [UrusanController::class, 'ptdSegerak'])->where('id', $uuid)->name('ptd.segerak');
        Route::post('urusan/{id}/ptd/manual', [UrusanController::class, 'ptdManual'])->where('id', $uuid)->name('ptd.manual');
    });

    // Record transfer (SRS-TSU-20)
    Route::middleware('can:tsu.pemindahan.cipta')->group(function () use ($uuid) {
        Route::post('urusan/{id}/pemindahan', [UrusanController::class, 'petakan'])->where('id', $uuid)->name('pemindahan.petakan');
        Route::post('urusan/{id}/pemindahan/hantar', [UrusanController::class, 'hantarPemindahan'])->where('id', $uuid)->name('pemindahan.hantar');
    });

    // Costs (SRS-TSU-14) — only Bahagian Kewangan changes financial data
    Route::get('urusan/{id}/kos', [KosController::class, 'index'])->where('id', $uuid)->middleware('can:tsu.kos.lihat')->name('kos.index');
    Route::post('urusan/{id}/kos', [KosController::class, 'store'])->where('id', $uuid)->middleware('can:tsu.kewangan.kemas_kini')->name('kos.store');
    Route::put('kos/{kos}', [KosController::class, 'update'])->whereNumber('kos')->middleware('can:tsu.kewangan.kemas_kini')->name('kos.update');

    // Surveyor panel, quotations, appointment (SRS-TSU-08..10)
    Route::get('panel', [UkurController::class, 'panel'])->middleware('can:tsu.panel.lihat')->name('panel.index');
    Route::post('panel', [UkurController::class, 'simpanPanel'])->middleware('can:tsu.panel.kemas_kini')->name('panel.store');
    Route::put('panel/{panel}', [UkurController::class, 'simpanPanel'])->whereNumber('panel')->middleware('can:tsu.panel.kemas_kini')->name('panel.update');
    Route::get('urusan/{id}/ukur', [UkurController::class, 'ukur'])->where('id', $uuid)->middleware('can:tsu.ukur.lihat')->name('ukur.index');
    Route::post('urusan/{id}/sebutharga', [UkurController::class, 'jemput'])->where('id', $uuid)->middleware('can:tsu.sebutharga.cipta')->name('sebutharga.store');
    Route::prefix('sebutharga/{sebutharga}')->where(['sebutharga' => $uuid])->name('sebutharga.')->group(function () {
        Route::post('tawaran/{tawaran}', [UkurController::class, 'rekodTawaran'])->whereNumber('tawaran')->middleware('can:tsu.sebutharga.cipta')->name('tawaran');
        Route::get('perbandingan', [UkurController::class, 'perbandingan'])->middleware('can:tsu.sebutharga.lihat')->name('perbandingan');
        Route::post('nilai', [UkurController::class, 'nilai'])->middleware('can:tsu.sebutharga.kemas_kini')->name('nilai');
        Route::post('lantik', [UkurController::class, 'lantik'])->middleware('can:tsu.sebutharga.cipta')->name('lantik');
        Route::get('jemputan.pdf', [UkurController::class, 'jemputanPdf'])->middleware('can:tsu.sebutharga.lihat')->name('jemputan');
    });

    // Progress (SRS-TSU-11): internal officers or the appointed external surveyor (checked in UkurService)
    Route::get('jurukur/lantikan', [UkurController::class, 'lantikanSaya'])->middleware('can:tsu.jurukur.kemas_kini')->name('jurukur.lantikan');
    Route::post('lantikan/{lantikan}/kemajuan', [UkurController::class, 'kemajuan'])->where('lantikan', $uuid)->name('lantikan.kemajuan');

    // Survey results (SRS-TSU-16..18)
    Route::post('urusan/{id}/hasil-ukur', [UkurController::class, 'importHasil'])->where('id', $uuid)->middleware('can:tsu.hasil_ukur.cipta')->name('hasil.store');
    Route::get('hasil-ukur/{hasil}/banding', [UkurController::class, 'banding'])->where('hasil', $uuid)->middleware('can:tsu.ukur.lihat')->name('hasil.banding');
    Route::post('hasil-ukur/{hasil}/sahkan', [UkurController::class, 'sahkan'])->where('hasil', $uuid)->middleware('can:tsu.hasil_ukur.lulus')->name('hasil.sahkan');

    // Templates (SRS-TSU-01/02)
    Route::get('templat', [TemplatController::class, 'index'])->middleware('can:tsu.templat.lihat')->name('templat.index');
    Route::get('templat/{jenis}', [TemplatController::class, 'show'])->middleware('can:tsu.templat.lihat')->name('templat.show');
    Route::post('templat/{jenis}', [TemplatController::class, 'store'])->middleware('can:tsu.templat.kemas_kini')->name('templat.store');

    // Reports (SRS-TSU-26/27)
    Route::get('laporan/ringkasan', [LaporanController::class, 'ringkasan'])->middleware('can:tsu.laporan.lihat')->name('laporan.ringkasan');
    Route::get('laporan/kerja', [LaporanController::class, 'kerja'])->middleware('can:tsu.laporan.lihat')->name('laporan.kerja');
    Route::get('laporan/kerja/eksport', [LaporanController::class, 'eksportKerja'])->middleware('can:tsu.laporan.eksport')->name('laporan.kerja.eksport');
});
