<?php

use App\Modules\Wfe\Http\Controllers\ContohController;
use App\Modules\Wfe\Http\Controllers\DefinisiController;
use App\Modules\Wfe\Http\Controllers\InstansController;
use App\Modules\Wfe\Http\Controllers\KalendarController;
use App\Modules\Wfe\Http\Controllers\KumpulanController;
use App\Modules\Wfe\Http\Controllers\PemangkuController;
use App\Modules\Wfe\Http\Controllers\PemantauanController;
use App\Modules\Wfe\Http\Controllers\StepUpController;
use App\Modules\Wfe\Http\Controllers\TugasanController;
use Illuminate\Support\Facades\Route;

// Mounted at /api/v1/wfe. Every route authorises through RBC's AccessControl
// via the wfe.boleh middleware (any of the listed permissions).
Route::middleware('auth:sanctum')->group(function () {
    // SRS-WFE-14: task board
    Route::middleware('wfe.boleh:wfe.tugasan.lihat')->group(function () {
        Route::get('tugasan', [TugasanController::class, 'index'])->name('tugasan.index');
        Route::get('tugasan/{tugasan}', [TugasanController::class, 'show'])->whereNumber('tugasan')->name('tugasan.show');
        Route::post('tugasan/{tugasan}/selesai', [TugasanController::class, 'complete'])->whereNumber('tugasan')->name('tugasan.selesai');
    });
    Route::post('tugasan/{tugasan}/tindakan', [TugasanController::class, 'act'])->whereNumber('tugasan')
        ->middleware('wfe.boleh:wfe.tugasan.lulus')->name('tugasan.tindakan');

    // SRS-WFE-19: MFA step-up status
    Route::get('mfa', [StepUpController::class, 'status'])->middleware('wfe.boleh:wfe.tugasan.lulus,wfe.definisi.lulus')->name('mfa');

    // SRS-WFE-22: applicant's own requests
    Route::middleware('wfe.boleh:wfe.permohonan.lihat')->get('permohonan', [InstansController::class, 'mine'])->name('permohonan');
    Route::middleware('wfe.boleh:wfe.permohonan.kemas_kini')->group(function () {
        Route::post('instans/{instans}/tarik-balik', [InstansController::class, 'withdraw'])->name('instans.tarik_balik');
        Route::post('instans/{instans}/hantar-semula', [InstansController::class, 'resubmit'])->name('instans.hantar_semula');
    });

    // SRS-WFE-13/15/16: monitoring + history (show also allowed to applicant / assignees, checked in controller)
    Route::get('instans/{instans}', [InstansController::class, 'show'])
        ->middleware('wfe.boleh:wfe.pemantauan.lihat,wfe.permohonan.lihat,wfe.tugasan.lihat')->name('instans.show');
    Route::middleware('wfe.boleh:wfe.pemantauan.lihat')->group(function () {
        Route::get('pemantauan', [PemantauanController::class, 'dashboard'])->name('pemantauan');
        Route::get('instans', [InstansController::class, 'index'])->name('instans.index');
    });
    Route::get('laporan', [PemantauanController::class, 'report'])->middleware('wfe.boleh:wfe.laporan.lihat')->name('laporan');

    // SRS-WFE-01..05, 09, 12: definitions
    Route::middleware('wfe.boleh:wfe.definisi.lihat')->group(function () {
        Route::get('aliran', [DefinisiController::class, 'flows'])->name('aliran');
        Route::get('rujukan', [DefinisiController::class, 'reference'])->name('rujukan');
        Route::get('definisi', [DefinisiController::class, 'index'])->name('definisi.index');
        Route::get('definisi/{definisi}', [DefinisiController::class, 'show'])->whereNumber('definisi')->name('definisi.show');
        Route::post('definisi/simulasi', [DefinisiController::class, 'simulate'])->name('definisi.simulasi');
    });
    Route::middleware('wfe.boleh:wfe.definisi.cipta')->group(function () {
        Route::post('definisi', [DefinisiController::class, 'store'])->name('definisi.store');
        Route::post('definisi/{definisi}/salin', [DefinisiController::class, 'copy'])->whereNumber('definisi')->name('definisi.salin');
    });
    Route::middleware('wfe.boleh:wfe.definisi.kemas_kini')->group(function () {
        Route::put('definisi/{definisi}', [DefinisiController::class, 'update'])->whereNumber('definisi')->name('definisi.update');
        Route::post('definisi/{definisi}/versi-baharu', [DefinisiController::class, 'newVersion'])->whereNumber('definisi')->name('definisi.versi');
        Route::post('definisi/{definisi}/mohon-aktif', [DefinisiController::class, 'requestActivation'])->whereNumber('definisi')->name('definisi.mohon_aktif');
        Route::post('instans/{instans}/pindah-versi', [InstansController::class, 'requestMigration'])->name('instans.pindah_versi');
    });
    Route::middleware('wfe.boleh:wfe.definisi.lulus')->group(function () {
        Route::post('definisi/{definisi}/lulus-aktif', [DefinisiController::class, 'approveActivation'])->whereNumber('definisi')->name('definisi.lulus_aktif');
        Route::post('definisi/{definisi}/tolak-aktif', [DefinisiController::class, 'rejectActivation'])->whereNumber('definisi')->name('definisi.tolak_aktif');
        Route::get('pindah-versi', [InstansController::class, 'migrations'])->name('pindah_versi.index');
        Route::post('pindah-versi/{pindahVersi}/keputusan', [InstansController::class, 'decideMigration'])->whereNumber('pindahVersi')->name('pindah_versi.keputusan');
    });

    // SRS-WFE-06: holiday calendar
    Route::middleware('wfe.boleh:wfe.kalendar.lihat,wfe.kalendar.kemas_kini')->group(function () {
        Route::get('kalendar', [KalendarController::class, 'index'])->name('kalendar.index');
        Route::get('kalendar/kira', [KalendarController::class, 'compute'])->name('kalendar.kira');
    });
    Route::middleware('wfe.boleh:wfe.kalendar.kemas_kini')->group(function () {
        Route::post('kalendar', [KalendarController::class, 'store'])->name('kalendar.store');
        Route::put('kalendar/{cuti}', [KalendarController::class, 'update'])->whereNumber('cuti')->name('kalendar.update');
        Route::delete('kalendar/{cuti}', [KalendarController::class, 'destroy'])->whereNumber('cuti')->name('kalendar.destroy');
    });

    // SRS-WFE-21: pemangku
    Route::middleware('wfe.boleh:wfe.pemangku.cipta,wfe.pemangku.kemas_kini')->group(function () {
        Route::get('pemangku', [PemangkuController::class, 'index'])->name('pemangku.index');
        Route::post('pemangku', [PemangkuController::class, 'store'])->name('pemangku.store');
        Route::delete('pemangku/{pemangku}', [PemangkuController::class, 'destroy'])->whereNumber('pemangku')->name('pemangku.destroy');
    });
    Route::get('pengguna', [PemangkuController::class, 'users'])
        ->middleware('wfe.boleh:wfe.pemangku.cipta,wfe.pemangku.kemas_kini,wfe.definisi.kemas_kini,wfe.kumpulan.kemas_kini')->name('pengguna');

    // SRS-WFE-05: approver groups
    Route::get('kumpulan', [KumpulanController::class, 'index'])->middleware('wfe.boleh:wfe.kumpulan.lihat,wfe.kumpulan.kemas_kini')->name('kumpulan.index');
    Route::middleware('wfe.boleh:wfe.kumpulan.kemas_kini')->group(function () {
        Route::post('kumpulan', [KumpulanController::class, 'store'])->name('kumpulan.store');
        Route::put('kumpulan/{kumpulan}', [KumpulanController::class, 'update'])->whereNumber('kumpulan')->name('kumpulan.update');
    });

    // Demo: start contoh.kelulusan
    Route::post('contoh', ContohController::class)->middleware('wfe.boleh:wfe.contoh.cipta')->name('contoh');
});
