<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('api.login');
Route::post('register', [AuthController::class, 'register'])->name('api.register');

Route::get('settings', [SettingController::class, 'show'])->name('api.settings.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('user', [AuthController::class, 'user'])->name('api.user');

    // MOD-RBC: user accounts, authorised by permission (never the legacy role string).
    Route::get('users', [UserController::class, 'index'])->middleware('can:rbc.pengguna.lihat')->name('users.index');
    Route::post('users', [UserController::class, 'store'])->middleware('can:rbc.pengguna.cipta')->name('users.store');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('can:rbc.pengguna.kemas_kini')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:rbc.pengguna.padam')->name('users.destroy');

    // `admin` = permission rbc.pentadbiran.akses (see EnsureUserIsAdmin).
    Route::middleware('admin')->group(function () {
        Route::put('settings', [SettingController::class, 'update'])->name('api.settings.update');
        Route::post('settings/logo', [SettingController::class, 'uploadLogo'])->name('api.settings.logo.upload');
        Route::post('settings/favicon', [SettingController::class, 'uploadFavicon'])->name('api.settings.favicon.upload');
        Route::delete('settings/logo', [SettingController::class, 'deleteLogo'])->name('api.settings.logo.delete');
        Route::delete('settings/favicon', [SettingController::class, 'deleteFavicon'])->name('api.settings.favicon.delete');
    });
});
