<?php

use App\Modules\Adu\Http\Controllers\AduanController;
use App\Modules\Adu\Http\Controllers\AwamController;
use App\Modules\Adu\Http\Controllers\IntegrasiController;
use App\Modules\Adu\Http\Controllers\KonfigurasiController;
use App\Modules\Adu\Http\Controllers\LaporanController;
use App\Modules\Adu\Http\Controllers\RujukanController;
use App\Modules\Adu\Http\Controllers\TindakanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/adu — MOD-ADU. Internal routes authorise through MOD-RBC (`can:` →
| AccessControl); `aduan` rows are scoped by daerah/mukim/klasifikasi
| (ScopedByAccess), so an out-of-scope complaint is a 404. Officer actions
| additionally require being the assigned officer, their supervisor or Unit
| Aduan (Support\Akses). The `awam/*` routes are the public portal API
| (no account; rate limit + CAPTCHA + file limits).
*/

Route::prefix('awam')->name('awam.')->middleware('throttle:60,1')->group(function () {
    Route::get('rujukan', [AwamController::class, 'rujukan'])->name('rujukan');
    Route::post('aduan', [AwamController::class, 'store'])->name('aduan.store');
    Route::post('semak', [AwamController::class, 'semak'])->name('semak');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', RujukanController::class)->name('rujukan');

    Route::middleware('can:adu.aduan.lihat')->group(function () {
        Route::get('aduan', [AduanController::class, 'index'])->name('aduan.index');
        Route::get('aduan/{aduan}', [AduanController::class, 'show'])->whereUuid('aduan')->name('aduan.show');
        Route::get('aduan/{aduan}/kronologi', [AduanController::class, 'kronologi'])->whereUuid('aduan')->name('aduan.kronologi');
        Route::get('aduan/{aduan}/pengadu', [AduanController::class, 'pengadu'])->whereUuid('aduan')->name('aduan.pengadu');
        Route::post('aduan/{aduan}/pengadu/dedah', [AduanController::class, 'dedahPengadu'])->whereUuid('aduan')->middleware('throttle:30,1')->name('aduan.pengadu.dedah');
        Route::get('aduan/{aduan}/dokumen', [AduanController::class, 'dokumen'])->whereUuid('aduan')->name('aduan.dokumen');
        Route::get('peta', [LaporanController::class, 'peta'])->name('peta');
    });

    Route::middleware('can:adu.aduan.cipta')->group(function () {
        Route::post('aduan', [AduanController::class, 'store'])->name('aduan.store');
        Route::post('cadang-kategori', [AduanController::class, 'cadangKategori'])->name('cadang_kategori');
        Route::get('cari-lot', [AduanController::class, 'cariLot'])->name('cari_lot');
    });

    Route::middleware('can:adu.aduan.kemas_kini')->prefix('aduan/{aduan}')->whereUuid('aduan')->name('aduan.')->group(function () {
        Route::put('/', [AduanController::class, 'update'])->name('update');
        Route::post('dokumen', [AduanController::class, 'muatNaik'])->name('dokumen.store');
        Route::post('mula-siasatan', [TindakanController::class, 'mulaSiasatan'])->name('mula_siasatan');
        Route::post('tindakan', [TindakanController::class, 'tindakan'])->name('tindakan');
        Route::post('lawatan', [TindakanController::class, 'jadualLawatan'])->name('lawatan.store');
        Route::post('lawatan/{lawatan}/selesai', [TindakanController::class, 'rekodLawatan'])->whereNumber('lawatan')->name('lawatan.selesai');
        Route::post('rujuk', [TindakanController::class, 'rujuk'])->name('rujuk');
        Route::post('eskalasi', [TindakanController::class, 'eskalasi'])->name('eskalasi');
        Route::post('penutupan', [TindakanController::class, 'penutupan'])->name('penutupan');
        Route::post('buka-semula', [TindakanController::class, 'bukaSemula'])->name('buka_semula');
    });

    Route::middleware('can:adu.penugasan.kemas_kini')->prefix('aduan/{aduan}')->whereUuid('aduan')->name('aduan.')->group(function () {
        Route::get('pegawai-layak', [AduanController::class, 'pegawaiLayak'])->name('pegawai_layak');
        Route::post('tugaskan', [TindakanController::class, 'tugaskan'])->name('tugaskan');
        Route::post('pendua', [TindakanController::class, 'pendua'])->name('pendua');
        Route::post('bukan-tanah-mais', [TindakanController::class, 'bukanMais'])->name('bukan_mais');
    });

    Route::middleware('can:adu.laporan.lihat')->prefix('laporan')->name('laporan.')->group(function () {
        Route::get('ringkasan', [LaporanController::class, 'ringkasan'])->name('ringkasan');
        Route::get('{laporan}', [LaporanController::class, 'show'])->name('show');
        Route::get('{laporan}/eksport', [LaporanController::class, 'eksport'])->middleware('can:adu.aduan.eksport')->name('eksport');
    });

    Route::get('konfigurasi', [KonfigurasiController::class, 'index'])->middleware('can:adu.konfigurasi.lihat')->name('konfigurasi');
    Route::middleware('can:adu.konfigurasi.kemas_kini')->prefix('konfigurasi')->name('konfigurasi.')->group(function () {
        Route::post('sla', [KonfigurasiController::class, 'simpanSla'])->name('sla.store');
        Route::put('sla/{sla}', [KonfigurasiController::class, 'simpanSla'])->whereNumber('sla')->name('sla.update');
        Route::post('subkategori', [KonfigurasiController::class, 'simpanSubkategori'])->name('subkategori.store');
        Route::put('subkategori/{subkategori}', [KonfigurasiController::class, 'simpanSubkategori'])->whereNumber('subkategori')->name('subkategori.update');
        Route::post('triaj', [KonfigurasiController::class, 'simpanTriaj'])->name('triaj.store');
        Route::put('triaj/{triaj}', [KonfigurasiController::class, 'simpanTriaj'])->whereNumber('triaj')->name('triaj.update');
        Route::post('penugasan', [KonfigurasiController::class, 'simpanPenugasan'])->name('penugasan.store');
        Route::put('penugasan/{penugasan}', [KonfigurasiController::class, 'simpanPenugasan'])->whereNumber('penugasan')->name('penugasan.update');
        Route::post('integrasi/pusat/import', [IntegrasiController::class, 'import'])->name('pusat.import');
        Route::post('integrasi/pusat/ulang-cuba', [IntegrasiController::class, 'ulangCuba'])->name('pusat.ulang_cuba');
        Route::get('integrasi/pusat/outbox', [IntegrasiController::class, 'outbox'])->name('pusat.outbox');
    });

    // Sistem Aduan berpusat MAIS → SISDATA (machine account).
    Route::post('integrasi/pusat/aduan', [IntegrasiController::class, 'terima'])->middleware('can:adu.integrasi.cipta')->name('pusat.terima');
});
