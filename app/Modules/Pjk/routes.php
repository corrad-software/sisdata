<?php

use App\Modules\Pjk\Http\Controllers\AgihanController;
use App\Modules\Pjk\Http\Controllers\AwamController;
use App\Modules\Pjk\Http\Controllers\GatewayWebhookController;
use App\Modules\Pjk\Http\Controllers\KewanganController;
use App\Modules\Pjk\Http\Controllers\KontrakController;
use App\Modules\Pjk\Http\Controllers\LaporanController;
use App\Modules\Pjk\Http\Controllers\PenyewaController;
use App\Modules\Pjk\Http\Controllers\PermohonanController;
use App\Modules\Pjk\Http\Controllers\PremisController;
use App\Modules\Pjk\Http\Controllers\RekonsiliasiController;
use App\Modules\Pjk\Http\Controllers\RujukanController;
use App\Modules\Pjk\Http\Controllers\SuratController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1/pjk — MOD-PJK. Every route authorises through MOD-RBC (`can:` →
| AccessControl); contracts, premises and applications are further scoped by
| daerah/mukim/klasifikasi (ScopedByAccess), so out-of-scope records are 404.
*/

// Public (Awam) — portal feeds and the gateway's server-to-server callback.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('awam/katalog', [AwamController::class, 'catalogue'])->name('awam.katalog');
    Route::get('awam/agihan', [AwamController::class, 'distribution'])->name('awam.agihan');
});
Route::post('gateway/panggilan-balik', GatewayWebhookController::class)->middleware('throttle:300,1')->name('gateway.panggilan_balik');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rujukan', [RujukanController::class, 'index'])->name('rujukan');

    // Tenants (SRS-PJK-01) — Sulit.
    Route::get('penyewa', [PenyewaController::class, 'index'])->middleware('can:pjk.penyewa.lihat')->name('penyewa.index');
    Route::get('penyewa/{id}', [PenyewaController::class, 'show'])->whereNumber('id')->middleware('can:pjk.penyewa.lihat')->name('penyewa.show');
    Route::post('penyewa', [PenyewaController::class, 'store'])->middleware('can:pjk.penyewa.cipta')->name('penyewa.store');
    Route::put('penyewa/{id}', [PenyewaController::class, 'update'])->whereNumber('id')->middleware('can:pjk.penyewa.kemas_kini')->name('penyewa.update');

    // Premises and offer catalogue (SRS-PJK-03/04).
    Route::get('premis', [PremisController::class, 'index'])->middleware('can:pjk.premis.lihat')->name('premis.index');
    Route::get('premis/{premis}', [PremisController::class, 'show'])->whereNumber('premis')->middleware('can:pjk.premis.lihat')->name('premis.show');
    Route::post('premis', [PremisController::class, 'store'])->middleware('can:pjk.premis.cipta')->name('premis.store');
    Route::put('premis/{premis}', [PremisController::class, 'update'])->whereNumber('premis')->middleware('can:pjk.premis.kemas_kini')->name('premis.update');
    Route::get('lot', [PremisController::class, 'lots'])->middleware('can:pjk.premis.cipta')->name('lot.carian');

    // Contracts (SRS-PJK-01/02/05/07/10/11/12).
    Route::middleware('can:pjk.kontrak.lihat')->group(function () {
        Route::get('kontrak', [KontrakController::class, 'index'])->name('kontrak.index');
        Route::get('kontrak/{kontrak}', [KontrakController::class, 'show'])->whereUuid('kontrak')->name('kontrak.show');
        Route::get('kontrak/{kontrak}/caj', [KontrakController::class, 'charges'])->whereUuid('kontrak')->name('kontrak.caj');
        Route::get('kontrak/{kontrak}/deposit', [KontrakController::class, 'deposits'])->whereUuid('kontrak')->name('kontrak.deposit');
        Route::get('kontrak/{kontrak}/pindaan', [KontrakController::class, 'amendments'])->whereUuid('kontrak')->name('kontrak.pindaan');
        Route::get('kontrak/{kontrak}/semakan-kadar', [KontrakController::class, 'rateReview'])->whereUuid('kontrak')->name('kontrak.semakan_kadar');
        Route::get('kontrak/{kontrak}/surat/{templat}/pratonton', [SuratController::class, 'preview'])->whereUuid('kontrak')->whereNumber('templat')->name('kontrak.surat.pratonton');
    });
    Route::middleware('can:pjk.lejar.lihat')->group(function () {
        Route::get('kontrak/{kontrak}/lejar', [KontrakController::class, 'ledger'])->whereUuid('kontrak')->name('kontrak.lejar');
        Route::get('kontrak/{kontrak}/bayaran', [KontrakController::class, 'payments'])->whereUuid('kontrak')->name('kontrak.bayaran');
        Route::get('resit/{resit}', [KewanganController::class, 'receipt'])->whereNumber('resit')->name('resit.muat_turun');
        Route::get('gateway/transaksi/{transaksi}', [KewanganController::class, 'gatewayStatus'])->whereUuid('transaksi')->name('gateway.status');
    });
    Route::get('kontrak/{kontrak}/penyata', [KontrakController::class, 'statement'])->whereUuid('kontrak')->middleware('can:pjk.lejar.eksport')->name('kontrak.penyata');
    Route::post('kontrak', [KontrakController::class, 'store'])->middleware('can:pjk.kontrak.cipta')->name('kontrak.store');
    Route::middleware('can:pjk.kontrak.kemas_kini')->group(function () {
        Route::put('kontrak/{kontrak}', [KontrakController::class, 'update'])->whereUuid('kontrak')->name('kontrak.update');
        Route::post('kontrak/{kontrak}/hantar', [KontrakController::class, 'submit'])->whereUuid('kontrak')->name('kontrak.hantar');
        Route::post('kontrak/{kontrak}/pindaan', [KontrakController::class, 'requestAmendment'])->whereUuid('kontrak')->name('kontrak.pindaan.store');
        Route::post('kontrak/{kontrak}/dokumen', [KontrakController::class, 'uploadAgreement'])->whereUuid('kontrak')->name('kontrak.dokumen');
    });

    // Payments (SRS-PJK-14..20), receipts, adjustments (SRS-PJK-28), deposits (SRS-PJK-13).
    Route::middleware('can:pjk.bayaran.cipta')->group(function () {
        Route::post('kontrak/{kontrak}/bayaran', [KewanganController::class, 'recordOffline'])->whereUuid('kontrak')->name('bayaran.store');
        Route::post('kontrak/{kontrak}/bayaran-dalam-talian', [KewanganController::class, 'initiate'])->whereUuid('kontrak')->name('bayaran.dalam_talian');
        Route::get('gateway-ujian/{noRujukan}', [KewanganController::class, 'testPage'])->name('gateway_ujian.show');
        Route::post('gateway-ujian/{noRujukan}/simulasi', [KewanganController::class, 'simulate'])->name('gateway_ujian.simulasi');
    });
    Route::get('bayaran', [KewanganController::class, 'pending'])->middleware('can:pjk.bayaran.lulus')->name('bayaran.index');
    Route::post('bayaran/{bayaran}/pengesahan', [KewanganController::class, 'confirm'])->whereNumber('bayaran')->middleware('can:pjk.bayaran.lulus')->name('bayaran.pengesahan');
    Route::post('kontrak/{kontrak}/pelarasan', [KewanganController::class, 'requestAdjustment'])->whereUuid('kontrak')->middleware('can:pjk.lejar.kemas_kini')->name('pelarasan.store');
    Route::post('deposit/{deposit}/penyelarasan', [KewanganController::class, 'settleDeposit'])->whereNumber('deposit')->middleware('can:pjk.deposit.kemas_kini')->name('deposit.penyelarasan');

    // Reconciliation (SRS-PJK-18/21).
    Route::get('rekonsiliasi', [RekonsiliasiController::class, 'index'])->middleware('can:pjk.rekonsiliasi.lihat')->name('rekonsiliasi.index');
    Route::post('rekonsiliasi', [RekonsiliasiController::class, 'run'])->middleware('can:pjk.rekonsiliasi.cipta')->name('rekonsiliasi.jalan');
    Route::post('rekonsiliasi/fail', [RekonsiliasiController::class, 'upload'])->middleware('can:pjk.rekonsiliasi.cipta')->name('rekonsiliasi.fail');
    Route::put('sisihan/{sisihan}', [RekonsiliasiController::class, 'resolve'])->whereNumber('sisihan')->middleware('can:pjk.rekonsiliasi.kemas_kini')->name('sisihan.update');

    // Dasar Agihan Manfaat (SRS-PJK-22..25).
    Route::get('dasar', [AgihanController::class, 'policies'])->middleware('can:pjk.agihan.lihat')->name('dasar.index');
    Route::post('dasar', [AgihanController::class, 'storePolicy'])->middleware('can:pjk.dasar.kemas_kini')->name('dasar.store');
    Route::post('dasar/{dasar}/hantar', [AgihanController::class, 'submitPolicy'])->whereNumber('dasar')->middleware('can:pjk.dasar.kemas_kini')->name('dasar.hantar');
    Route::get('agihan', [AgihanController::class, 'index'])->middleware('can:pjk.agihan.lihat')->name('agihan.index');
    Route::post('agihan/kira', [AgihanController::class, 'calculate'])->middleware('can:pjk.agihan.cipta')->name('agihan.kira');
    Route::post('agihan/simulasi', [AgihanController::class, 'simulate'])->middleware('can:pjk.agihan.cipta')->name('agihan.simulasi');
    Route::post('agihan/sahkan', [AgihanController::class, 'confirm'])->middleware('can:pjk.agihan.lulus')->name('agihan.sahkan');

    // Letters (SRS-PJK-09).
    Route::get('templat', [SuratController::class, 'templates'])->middleware('can:pjk.surat.cipta')->name('templat.index');
    Route::put('templat/{templat}', [SuratController::class, 'update'])->whereNumber('templat')->middleware('can:pjk.templat.kemas_kini')->name('templat.update');
    Route::post('kontrak/{kontrak}/surat', [SuratController::class, 'generate'])->whereUuid('kontrak')->middleware('can:pjk.surat.cipta')->name('surat.store');

    // Applications (SRS-PJK-06).
    Route::get('permohonan', [PermohonanController::class, 'index'])->middleware('can:pjk.permohonan.lihat')->name('permohonan.index');
    Route::get('permohonan/{permohonan}', [PermohonanController::class, 'show'])->whereNumber('permohonan')->middleware('can:pjk.permohonan.lihat')->name('permohonan.show');
    Route::post('permohonan', [PermohonanController::class, 'store'])->middleware('can:pjk.permohonan.cipta')->name('permohonan.store');
    Route::post('permohonan/{permohonan}/kontrak', [PermohonanController::class, 'toContract'])->whereNumber('permohonan')->middleware(['can:pjk.permohonan.kemas_kini', 'can:pjk.kontrak.cipta'])->name('permohonan.kontrak');
    Route::post('permohonan/{permohonan}/tolak', [PermohonanController::class, 'reject'])->whereNumber('permohonan')->middleware('can:pjk.permohonan.kemas_kini')->name('permohonan.tolak');

    // Portal Hartanah Wakaf Selangor — integration account (two-way API).
    Route::middleware(['can:pjk.portal.cipta', 'throttle:120,1'])->group(function () {
        Route::post('portal/permohonan', [PermohonanController::class, 'portalStore'])->name('portal.permohonan');
        Route::get('portal/permohonan/{rujukan}', [PermohonanController::class, 'portalStatus'])->name('portal.permohonan.status');
        Route::post('portal/bayaran', [PermohonanController::class, 'portalPayment'])->name('portal.bayaran');
    });

    // Reports and dashboard (SRS-PJK-26/27).
    Route::get('ringkasan', [LaporanController::class, 'dashboard'])->middleware('can:pjk.laporan.lihat')->name('ringkasan');
    Route::get('laporan/{jenis}', [LaporanController::class, 'show'])->middleware('can:pjk.laporan.lihat')->name('laporan.show');
    Route::get('laporan/{jenis}/eksport', [LaporanController::class, 'export'])->middleware('can:pjk.laporan.eksport')->name('laporan.eksport');
});
