<?php

use App\Modules\Hkm\Http\Controllers\CadanganController;
use App\Modules\Hkm\Http\Controllers\HakMilikController;
use App\Modules\Hkm\Http\Controllers\LaporanController;
use App\Modules\Hkm\Http\Controllers\PengeluaranController;
use App\Modules\Hkm\Http\Controllers\PermohonanController;
use App\Modules\Hkm\Http\Controllers\PerubahanController;
use App\Modules\Hkm\Http\Controllers\PetiBesiController;
use App\Modules\Hkm\Http\Controllers\RujukanController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/hkm — MOD-HKM. Every route authorises through MOD-RBC (`can:` →
| AccessControl); records are further scoped by daerah/mukim/klasifikasi
| (ScopedByAccess on hak_milik), so an out-of-scope title is a 404.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', [RujukanController::class, 'index'])->name('rujukan');

    // Title register, search, export (SRS-HKM-01/02/14/21).
    Route::middleware('can:hkm.hak_milik.lihat')->group(function () {
        Route::get('hak-milik', [HakMilikController::class, 'index'])->name('hak_milik.index');
        Route::get('hak-milik/{hakMilik}', [HakMilikController::class, 'show'])->name('hak_milik.show');
        Route::get('hak-milik/{hakMilik}/sejarah', [HakMilikController::class, 'history'])->name('hak_milik.sejarah');
        Route::get('lot/{lot}/hak-milik', [HakMilikController::class, 'forLot'])->whereUuid('lot')->name('lot.hak_milik');
        Route::get('lot/{lot}/kronologi', [HakMilikController::class, 'lotChronology'])->whereUuid('lot')->name('lot.kronologi');
        Route::get('perubahan', [PerubahanController::class, 'index'])->name('perubahan.index');
        Route::get('perubahan/{perubahan}', [PerubahanController::class, 'show'])->name('perubahan.show');
    });
    Route::get('eksport/hak-milik', [HakMilikController::class, 'export'])->middleware('can:hkm.hak_milik.eksport')->name('hak_milik.eksport');
    Route::post('hak-milik', [HakMilikController::class, 'store'])->middleware('can:hkm.hak_milik.cipta')->name('hak_milik.store');
    Route::post('hak-milik/{hakMilik}/sahkan', [HakMilikController::class, 'verify'])->middleware('can:hkm.pengesahan.lulus')->name('hak_milik.sahkan');

    // Change requests → WFE two-stage approval with MFA (SRS-HKM-19); Land Office transactions (SRS-HKM-21).
    Route::middleware('can:hkm.hak_milik.kemas_kini')->group(function () {
        Route::post('hak-milik/{hakMilik}/perubahan', [PerubahanController::class, 'store'])->name('perubahan.store');
        Route::post('hak-milik/{hakMilik}/urusan', [HakMilikController::class, 'storeTransaction'])->name('urusan.store');
        Route::put('urusan/{urusan}', [HakMilikController::class, 'updateTransaction'])->whereNumber('urusan')->name('urusan.update');
    });

    // Vault (SRS-HKM-03..07).
    Route::middleware('can:hkm.peti_besi.lihat')->group(function () {
        Route::get('peti-besi', [PetiBesiController::class, 'index'])->name('peti_besi.index');
        Route::get('daftar-peti-besi', [PetiBesiController::class, 'entries'])->name('daftar.index');
        Route::get('daftar-peti-besi/{daftar}', [PetiBesiController::class, 'showEntry'])->whereNumber('daftar')->name('daftar.show');
    });
    Route::get('pengeluaran', [PengeluaranController::class, 'index'])->name('pengeluaran.index');
    Route::get('eksport/inventori-peti-besi', [PetiBesiController::class, 'inventory'])->middleware('can:hkm.peti_besi.eksport')->name('peti_besi.inventori');
    Route::post('peti-besi', [PetiBesiController::class, 'store'])->middleware('can:hkm.peti_besi.cipta')->name('peti_besi.store');
    Route::put('peti-besi/{petiBesi}', [PetiBesiController::class, 'update'])->middleware('can:hkm.peti_besi.kemas_kini')->whereNumber('petiBesi')->name('peti_besi.update');
    Route::post('hak-milik/{hakMilik}/daftar-peti-besi', [PetiBesiController::class, 'deposit'])->middleware('can:hkm.peti_besi.cipta')->name('daftar.store');
    Route::put('daftar-peti-besi/{daftar}', [PetiBesiController::class, 'updateEntry'])->middleware('can:hkm.peti_besi.kemas_kini')->whereNumber('daftar')->name('daftar.update');
    Route::post('daftar-peti-besi/{daftar}/keluar', [PengeluaranController::class, 'issue'])->middleware('can:hkm.pengeluaran.cipta')->whereNumber('daftar')->name('pengeluaran.keluar');
    Route::post('pengeluaran/{pengeluaran}/pulang', [PengeluaranController::class, 'return'])->middleware('can:hkm.pengeluaran.kemas_kini')->whereNumber('pengeluaran')->name('pengeluaran.pulang');

    // Permanent-title applications (SRS-HKM-16).
    Route::middleware('can:hkm.permohonan.lihat')->group(function () {
        Route::get('permohonan-hmk', [PermohonanController::class, 'index'])->name('permohonan.index');
        Route::get('permohonan-hmk/{permohonan}', [PermohonanController::class, 'show'])->name('permohonan.show');
    });
    Route::post('hak-milik/{hakMilik}/permohonan-hmk', [PermohonanController::class, 'store'])->middleware('can:hkm.permohonan.cipta')->name('permohonan.store');
    Route::middleware('can:hkm.permohonan.kemas_kini')->group(function () {
        Route::put('permohonan-hmk/{permohonan}/senarai-semak', [PermohonanController::class, 'checklist'])->name('permohonan.semak');
        Route::post('permohonan-hmk/{permohonan}/peringkat', [PermohonanController::class, 'advance'])->name('permohonan.peringkat');
        Route::post('permohonan-hmk/{permohonan}/bayaran', [PermohonanController::class, 'payment'])->name('permohonan.bayaran');
        Route::post('permohonan-hmk/{permohonan}/terima', [PermohonanController::class, 'receive'])->name('permohonan.terima');
    });

    // External change proposals — drafts only (SRS-HKM-18).
    Route::post('cadangan', [CadanganController::class, 'store'])->middleware('can:hkm.cadangan.cipta')->name('cadangan.store');
    Route::middleware('can:hkm.cadangan.lihat')->group(function () {
        Route::get('cadangan', [CadanganController::class, 'index'])->name('cadangan.index');
        Route::get('cadangan/{cadangan}', [CadanganController::class, 'show'])->name('cadangan.show');
    });
    Route::middleware('can:hkm.cadangan.kemas_kini')->group(function () {
        Route::post('cadangan/{cadangan}/sahkan', [CadanganController::class, 'confirm'])->name('cadangan.sahkan');
        Route::post('cadangan/{cadangan}/tolak', [CadanganController::class, 'reject'])->name('cadangan.tolak');
    });

    // Reports.
    Route::get('laporan/{jenis}', [LaporanController::class, 'show'])->middleware('can:hkm.hak_milik.lihat')->name('laporan.show');
});
