<?php

use App\Modules\Rbc\Http\Controllers\AlertController;
use App\Modules\Rbc\Http\Controllers\ClaimMappingController;
use App\Modules\Rbc\Http\Controllers\LoginLogController;
use App\Modules\Rbc\Http\Controllers\MatrixController;
use App\Modules\Rbc\Http\Controllers\PasswordController;
use App\Modules\Rbc\Http\Controllers\PolicyController;
use App\Modules\Rbc\Http\Controllers\RecertificationController;
use App\Modules\Rbc\Http\Controllers\ReportController;
use App\Modules\Rbc\Http\Controllers\RoleController;
use App\Modules\Rbc\Http\Controllers\SessionController;
use App\Modules\Rbc\Http\Controllers\UserAccessController;
use Illuminate\Support\Facades\Route;

/*
| MOD-RBC API — mounted at /api/v1/rbc, route names "rbc.*". Every route
| authorises through AccessControl via `can:` (Gate). User CRUD itself stays
| at /api/v1/users (routes/api.php) for compatibility.
*/

Route::middleware('auth:sanctum')->group(function () {
    // Own session & password (any signed-in user).
    Route::get('sesi/status', [SessionController::class, 'status'])->name('sesi.status');
    Route::post('sesi/lanjut', [SessionController::class, 'extend'])->name('sesi.lanjut');
    Route::post('kata-laluan', [PasswordController::class, 'update'])->name('kata_laluan.tukar');

    // Permission catalogue (for the role editor).
    Route::get('kebenaran', [RoleController::class, 'catalogue'])->middleware('can:rbc.peranan.lihat')->name('kebenaran.index');

    // Roles (SRS-RBC-06).
    Route::get('peranan', [RoleController::class, 'index'])->middleware('can:rbc.peranan.lihat')->name('peranan.index');
    Route::post('peranan', [RoleController::class, 'store'])->middleware('can:rbc.peranan.cipta')->name('peranan.store');
    Route::get('peranan/{peranan}', [RoleController::class, 'show'])->middleware('can:rbc.peranan.lihat')->name('peranan.show');
    Route::put('peranan/{peranan}', [RoleController::class, 'update'])->middleware('can:rbc.peranan.kemas_kini')->name('peranan.update');
    Route::put('peranan/{peranan}/kebenaran', [RoleController::class, 'permissions'])->middleware('can:rbc.peranan.kemas_kini')->name('peranan.kebenaran');
    Route::put('peranan/{peranan}/skop', [RoleController::class, 'scopes'])->middleware('can:rbc.peranan.kemas_kini')->name('peranan.skop');
    Route::put('peranan/{peranan}/status', [RoleController::class, 'status'])->middleware('can:rbc.peranan.kemas_kini')->name('peranan.status');
    Route::post('peranan/{peranan}/klon', [RoleController::class, 'clone'])->middleware('can:rbc.peranan.cipta')->name('peranan.klon');
    Route::delete('peranan/{peranan}', [RoleController::class, 'destroy'])->middleware('can:rbc.peranan.padam')->name('peranan.destroy');

    // User access: detail, status, roles, scopes, unlock, sessions (SRS-RBC-04/07/15/17).
    Route::get('pengguna/{user}', [UserAccessController::class, 'show'])->middleware('can:rbc.pengguna.lihat')->name('pengguna.show');
    Route::put('pengguna/{user}/status', [UserAccessController::class, 'status'])->middleware('can:rbc.pengguna.kemas_kini')->name('pengguna.status');
    Route::put('pengguna/{user}/peranan', [UserAccessController::class, 'roles'])->middleware('can:rbc.pengguna.kemas_kini')->name('pengguna.peranan');
    Route::put('pengguna/{user}/skop', [UserAccessController::class, 'scopes'])->middleware('can:rbc.pengguna.kemas_kini')->name('pengguna.skop');
    Route::put('pengguna/{user}/capaian', [UserAccessController::class, 'accessPeriod'])->middleware('can:rbc.pengguna.kemas_kini')->name('pengguna.capaian');
    Route::post('pengguna/{user}/buka-kunci', [UserAccessController::class, 'unlock'])->middleware('can:rbc.pengguna.kemas_kini')->name('pengguna.buka_kunci');
    Route::delete('pengguna/{user}/sesi', [UserAccessController::class, 'terminateSessions'])->middleware('can:rbc.sesi.padam')->name('pengguna.sesi.tamat');

    // Active sessions (SRS-RBC-19).
    Route::get('sesi', [SessionController::class, 'index'])->middleware('can:rbc.sesi.lihat')->name('sesi.index');
    Route::delete('sesi/{sesi}', [SessionController::class, 'destroy'])->middleware('can:rbc.sesi.padam')->name('sesi.destroy');

    // SSO claim → role mapping rules (SRS-RBC-02).
    Route::get('pemetaan', [ClaimMappingController::class, 'index'])->middleware('can:rbc.pemetaan.lihat')->name('pemetaan.index');
    Route::post('pemetaan', [ClaimMappingController::class, 'store'])->middleware('can:rbc.pemetaan.kemas_kini')->name('pemetaan.store');
    Route::post('pemetaan/uji', [ClaimMappingController::class, 'preview'])->middleware('can:rbc.pemetaan.lihat')->name('pemetaan.uji');
    Route::put('pemetaan/{pemetaan}', [ClaimMappingController::class, 'update'])->middleware('can:rbc.pemetaan.kemas_kini')->name('pemetaan.update');
    Route::delete('pemetaan/{pemetaan}', [ClaimMappingController::class, 'destroy'])->middleware('can:rbc.pemetaan.kemas_kini')->name('pemetaan.destroy');

    // Login log + alerts (SRS-RBC-21/22).
    Route::get('log-masuk', [LoginLogController::class, 'index'])->middleware('can:rbc.log_masuk.lihat')->name('log_masuk.index');
    Route::get('log-masuk/eksport', [LoginLogController::class, 'export'])->middleware('can:rbc.log_masuk.eksport')->name('log_masuk.eksport');
    Route::get('amaran', [AlertController::class, 'index'])->middleware('can:rbc.amaran.lihat')->name('amaran.index');
    Route::put('amaran/{amaran}/semak', [AlertController::class, 'acknowledge'])->middleware('can:rbc.amaran.kemas_kini')->name('amaran.semak');

    // Access matrix (SRS-RBC-10) + reports (SRS-RBC-26).
    Route::get('matriks', [MatrixController::class, 'show'])->middleware('can:rbc.matriks.lihat')->name('matriks.show');
    Route::get('matriks/eksport', [MatrixController::class, 'export'])->middleware('can:rbc.matriks.eksport')->name('matriks.eksport');
    Route::get('laporan', [ReportController::class, 'summary'])->middleware('can:rbc.laporan.lihat')->name('laporan.ringkasan');
    Route::get('laporan/eksport', [ReportController::class, 'export'])->middleware('can:rbc.laporan.eksport')->name('laporan.eksport');

    // Security policy (SRS-RBC-13/16/18/19).
    Route::get('polisi', [PolicyController::class, 'show'])->middleware('can:rbc.polisi.lihat')->name('polisi.show');
    Route::put('polisi', [PolicyController::class, 'update'])->middleware('can:rbc.polisi.kemas_kini')->name('polisi.update');

    // Access recertification (SRS-RBC-27).
    Route::get('recert', [RecertificationController::class, 'index'])->middleware('can:rbc.recert.lihat')->name('recert.index');
    Route::post('recert', [RecertificationController::class, 'store'])->middleware('can:rbc.recert.cipta')->name('recert.store');
    Route::get('recert/{kitaran}', [RecertificationController::class, 'show'])->middleware('can:rbc.recert.lihat')->name('recert.show');
    Route::put('recert/{kitaran}/keputusan/{keputusan}', [RecertificationController::class, 'decide'])->middleware('can:rbc.recert.lulus')->name('recert.keputusan');
    Route::post('recert/{kitaran}/tutup', [RecertificationController::class, 'close'])->middleware('can:rbc.recert.cipta')->name('recert.tutup');
});
