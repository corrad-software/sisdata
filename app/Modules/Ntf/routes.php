<?php

use App\Modules\Ntf\Http\Controllers\AnnouncementController;
use App\Modules\Ntf\Http\Controllers\InboxController;
use App\Modules\Ntf\Http\Controllers\MonitoringController;
use App\Modules\Ntf\Http\Controllers\PreferenceController;
use App\Modules\Ntf\Http\Controllers\TypeController;
use Illuminate\Support\Facades\Route;

// Mounted at /api/v1/ntf. Own inbox/preferences: any signed-in user.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifikasi', [InboxController::class, 'index'])->name('notifikasi.index');
    Route::get('notifikasi/ringkas', [InboxController::class, 'summary'])->name('notifikasi.ringkas');
    Route::get('notifikasi/pilihan', [InboxController::class, 'options'])->name('notifikasi.pilihan');
    Route::post('notifikasi/tindakan', [InboxController::class, 'act'])->name('notifikasi.tindakan');
    Route::get('kebenaran', [InboxController::class, 'abilities'])->name('kebenaran');

    Route::get('keutamaan', [PreferenceController::class, 'index'])->name('keutamaan.index');
    Route::put('keutamaan', [PreferenceController::class, 'update'])->name('keutamaan.update');

    // Admin: types, rules, templates (SRS-NTF-03/05).
    Route::middleware('can:ntf.jenis.lihat')->group(function () {
        Route::get('jenis', [TypeController::class, 'index'])->name('jenis.index');
        Route::get('jenis/{jenis}', [TypeController::class, 'show'])->name('jenis.show');
        Route::post('jenis/{jenis}/templat/pratonton', [TypeController::class, 'preview'])->name('jenis.templat.pratonton');
    });
    Route::middleware('can:ntf.jenis.kemas_kini')->group(function () {
        Route::put('jenis/{jenis}', [TypeController::class, 'update'])->name('jenis.update');
        Route::post('jenis/{jenis}/templat', [TypeController::class, 'storeTemplate'])->name('jenis.templat.store');
        Route::post('jenis/{jenis}/templat/{templat}/aktif', [TypeController::class, 'activateTemplate'])->name('jenis.templat.aktif');
        Route::post('jenis/{jenis}/peraturan', [TypeController::class, 'storeRule'])->name('jenis.peraturan.store');
        Route::delete('jenis/{jenis}/peraturan/{peraturan}', [TypeController::class, 'destroyRule'])->name('jenis.peraturan.destroy');
    });

    // Monitoring + delivery log (SRS-NTF-15/23).
    Route::middleware('can:ntf.pemantauan.lihat')->group(function () {
        Route::get('pemantauan', [MonitoringController::class, 'dashboard'])->name('pemantauan');
        Route::get('penghantaran', [MonitoringController::class, 'deliveries'])->name('penghantaran.index');
    });
    Route::post('penghantaran/{penghantaran}/cuba-semula', [MonitoringController::class, 'retry'])
        ->middleware('can:ntf.saluran.kemas_kini')->name('penghantaran.cuba_semula');

    // Announcements (SRS-NTF-24).
    Route::middleware('can:ntf.pengumuman.cipta')->group(function () {
        Route::get('pengumuman/kumpulan', [AnnouncementController::class, 'groups'])->name('pengumuman.kumpulan');
        Route::post('pengumuman', [AnnouncementController::class, 'store'])->name('pengumuman.store');
    });
});
