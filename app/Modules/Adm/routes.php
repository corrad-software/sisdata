<?php

use App\Modules\Adm\Http\Controllers\CarianController;
use App\Modules\Adm\Http\Controllers\IntegrasiController;
use App\Modules\Adm\Http\Controllers\KelulusanController;
use App\Modules\Adm\Http\Controllers\KesihatanController;
use App\Modules\Adm\Http\Controllers\KodRujukanController;
use App\Modules\Adm\Http\Controllers\LogController;
use App\Modules\Adm\Http\Controllers\ParameterController;
use App\Modules\Adm\Http\Controllers\PenyelenggaraanController;
use App\Modules\Adm\Http\Controllers\RahsiaController;
use App\Modules\Adm\Http\Controllers\TemplatController;
use App\Modules\Adm\Http\Controllers\TokenApiController;
use App\Modules\Adm\Http\Controllers\TugasanController;
use Illuminate\Support\Facades\Route;

/*
| MOD-ADM — /api/v1/adm/*. Every admin endpoint: Sanctum (SPA session or a
| token with the adm:pentadbir ability, SRS-ADM-05) + a permission asked of
| MOD-RBC's AccessControl.
*/

// SRS-ADM-29: maintenance notice for every visitor (banner), no auth.
Route::get('penyelenggaraan', [PenyelenggaraanController::class, 'show'])->name('penyelenggaraan.show');

Route::middleware('auth:sanctum')->group(function () {
    // SRS-ADM-21/22: global search + deep links — any signed-in user; providers apply access.
    Route::get('carian', [CarianController::class, 'search'])->name('carian');
    Route::get('pautan/{entiti}/{id}', [CarianController::class, 'resolve'])->name('pautan');

    Route::middleware('adm.token')->group(function () {
        // SRS-ADM-01
        Route::get('parameter', [ParameterController::class, 'index'])->middleware('adm.boleh:adm.parameter.lihat')->name('parameter.index');
        Route::put('parameter/{kunci}', [ParameterController::class, 'update'])->middleware('adm.boleh:adm.parameter.kemas_kini')->name('parameter.update');
        Route::delete('parameter/{kunci}', [ParameterController::class, 'destroy'])->middleware('adm.boleh:adm.parameter.kemas_kini')->name('parameter.destroy');

        // SRS-ADM-02 (+05 bulk import)
        Route::get('kod-rujukan', [KodRujukanController::class, 'index'])->middleware('adm.boleh:adm.kod_rujukan.lihat')->name('kod.index');
        Route::get('kod-rujukan/jenis', [KodRujukanController::class, 'types'])->middleware('adm.boleh:adm.kod_rujukan.lihat')->name('kod.jenis');
        Route::post('kod-rujukan', [KodRujukanController::class, 'store'])->middleware('adm.boleh:adm.kod_rujukan.cipta')->name('kod.store');
        Route::post('kod-rujukan/import', [KodRujukanController::class, 'import'])->middleware('adm.boleh:adm.kod_rujukan.cipta')->name('kod.import');
        Route::put('kod-rujukan/{kod}', [KodRujukanController::class, 'update'])->middleware('adm.boleh:adm.kod_rujukan.kemas_kini')->name('kod.update');
        Route::post('kod-rujukan/{kod}/nyahaktif', [KodRujukanController::class, 'deactivate'])->middleware('adm.boleh:adm.kod_rujukan.padam')->name('kod.nyahaktif');
        Route::post('kod-rujukan/{kod}/aktif', [KodRujukanController::class, 'activate'])->middleware('adm.boleh:adm.kod_rujukan.kemas_kini')->name('kod.aktif');
        Route::get('kod-rujukan/{kod}/sejarah', [KodRujukanController::class, 'history'])->middleware('adm.boleh:adm.kod_rujukan.lihat')->name('kod.sejarah');

        // SRS-ADM-03
        Route::get('templat', [TemplatController::class, 'index'])->middleware('adm.boleh:adm.templat.lihat')->name('templat.index');
        Route::post('templat', [TemplatController::class, 'store'])->middleware('adm.boleh:adm.templat.cipta')->name('templat.store');
        Route::get('templat/{templat}', [TemplatController::class, 'show'])->middleware('adm.boleh:adm.templat.lihat')->name('templat.show');
        Route::put('templat/{templat}', [TemplatController::class, 'update'])->middleware('adm.boleh:adm.templat.kemas_kini')->name('templat.update');
        Route::post('templat/{templat}/pratonton', [TemplatController::class, 'preview'])->middleware('adm.boleh:adm.templat.lihat')->name('templat.pratonton');
        Route::post('templat/{templat}/versi/{versi}/pulih', [TemplatController::class, 'restore'])->whereNumber('versi')->middleware('adm.boleh:adm.templat.kemas_kini')->name('templat.pulih');

        // SRS-ADM-18/19
        Route::get('kelulusan', [KelulusanController::class, 'index'])->middleware('adm.boleh:adm.konfigurasi.lihat,adm.konfigurasi.lulus')->name('kelulusan.index');
        Route::post('kelulusan/{kelulusan}/lulus', [KelulusanController::class, 'approve'])->middleware('adm.boleh:adm.konfigurasi.lulus')->name('kelulusan.lulus');
        Route::post('kelulusan/{kelulusan}/tolak', [KelulusanController::class, 'reject'])->middleware('adm.boleh:adm.konfigurasi.lulus')->name('kelulusan.tolak');
        Route::post('kelulusan/{kelulusan}/batal', [KelulusanController::class, 'cancel'])->middleware('adm.boleh:adm.konfigurasi.lihat')->name('kelulusan.batal');

        // SRS-ADM-06..10
        Route::get('tugasan', [TugasanController::class, 'index'])->middleware('adm.boleh:adm.tugasan.lihat')->name('tugasan.index');
        Route::get('tugasan/sistem', [TugasanController::class, 'system'])->middleware('adm.boleh:adm.tugasan.lihat')->name('tugasan.sistem');
        Route::post('tugasan/sistem/ambil', [TugasanController::class, 'adopt'])->middleware('adm.boleh:adm.tugasan.cipta')->name('tugasan.ambil');
        Route::post('tugasan', [TugasanController::class, 'store'])->middleware('adm.boleh:adm.tugasan.cipta')->name('tugasan.store');
        Route::get('tugasan/{tugasan}', [TugasanController::class, 'show'])->middleware('adm.boleh:adm.tugasan.lihat')->name('tugasan.show');
        Route::put('tugasan/{tugasan}', [TugasanController::class, 'update'])->middleware('adm.boleh:adm.tugasan.kemas_kini')->name('tugasan.update');
        Route::delete('tugasan/{tugasan}', [TugasanController::class, 'destroy'])->middleware('adm.boleh:adm.tugasan.padam')->name('tugasan.destroy');
        Route::post('tugasan/{tugasan}/jalankan', [TugasanController::class, 'run'])->middleware('adm.boleh:adm.tugasan.kemas_kini')->name('tugasan.jalankan');
        Route::get('tugasan/{tugasan}/larian', [TugasanController::class, 'runs'])->middleware('adm.boleh:adm.tugasan.lihat')->name('tugasan.larian');

        // SRS-ADM-11/14
        Route::get('log/ralat', [LogController::class, 'errors'])->middleware('adm.boleh:adm.log.lihat')->name('log.ralat');
        Route::get('log/ralat/eksport', [LogController::class, 'exportErrors'])->middleware('adm.boleh:adm.log.eksport')->name('log.ralat.eksport');
        Route::get('log/ralat/{log}', [LogController::class, 'error'])->middleware('adm.boleh:adm.log.lihat')->name('log.ralat.show');
        Route::get('log/integrasi', [LogController::class, 'integrations'])->middleware('adm.boleh:adm.log.lihat')->name('log.integrasi');
        Route::get('log/integrasi/eksport', [LogController::class, 'exportIntegrations'])->middleware('adm.boleh:adm.log.eksport')->name('log.integrasi.eksport');

        // SRS-ADM-16
        Route::get('kesihatan', KesihatanController::class)->middleware('adm.boleh:adm.kesihatan.lihat')->name('kesihatan');
        Route::get('kesihatan/sejarah', [KesihatanController::class, 'history'])->middleware('adm.boleh:adm.kesihatan.lihat')->name('kesihatan.sejarah');

        // SRS-ADM-04/28
        Route::get('integrasi', [IntegrasiController::class, 'index'])->middleware('adm.boleh:adm.integrasi.lihat')->name('integrasi.index');
        Route::post('integrasi', [IntegrasiController::class, 'store'])->middleware('adm.boleh:adm.integrasi.cipta')->name('integrasi.store');
        Route::put('integrasi/{integrasi}', [IntegrasiController::class, 'update'])->middleware('adm.boleh:adm.integrasi.kemas_kini')->name('integrasi.update');
        Route::post('integrasi/{integrasi}/uji', [IntegrasiController::class, 'test'])->middleware('adm.boleh:adm.integrasi.kemas_kini')->name('integrasi.uji');
        Route::get('rahsia', [RahsiaController::class, 'index'])->middleware('adm.boleh:adm.rahsia.lihat')->name('rahsia.index');
        Route::put('rahsia/{kunci}', [RahsiaController::class, 'update'])->middleware('adm.boleh:adm.rahsia.kemas_kini')->name('rahsia.update');

        // SRS-ADM-20
        Route::get('carian/tetapan', [CarianController::class, 'settings'])->middleware('adm.boleh:adm.carian.lihat')->name('carian.tetapan');
        Route::put('carian/tetapan/{indeks}', [CarianController::class, 'updateSetting'])->middleware('adm.boleh:adm.carian.kemas_kini')->name('carian.tetapan.update');
        Route::post('carian/tetapan/{indeks}/indeks-semula', [CarianController::class, 'reindex'])->middleware('adm.boleh:adm.carian.kemas_kini')->name('carian.indeks');
        Route::post('carian/sinonim', [CarianController::class, 'storeSynonym'])->middleware('adm.boleh:adm.carian.kemas_kini')->name('carian.sinonim.store');
        Route::delete('carian/sinonim/{sinonim}', [CarianController::class, 'destroySynonym'])->middleware('adm.boleh:adm.carian.kemas_kini')->name('carian.sinonim.destroy');

        // SRS-ADM-29
        Route::put('penyelenggaraan', [PenyelenggaraanController::class, 'update'])->middleware('adm.boleh:adm.penyelenggaraan.kemas_kini')->name('penyelenggaraan.update');

        // SRS-ADM-05
        Route::get('token-api', [TokenApiController::class, 'index'])->middleware('adm.boleh:adm.api.cipta')->name('token.index');
        Route::post('token-api', [TokenApiController::class, 'store'])->middleware('adm.boleh:adm.api.cipta')->name('token.store');
        Route::delete('token-api/{id}', [TokenApiController::class, 'destroy'])->whereNumber('id')->middleware('adm.boleh:adm.api.cipta')->name('token.destroy');
    });
});
