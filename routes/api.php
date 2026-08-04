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

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::put('settings', [SettingController::class, 'update'])->name('api.settings.update');
        Route::post('settings/logo', [SettingController::class, 'uploadLogo'])->name('api.settings.logo.upload');
        Route::post('settings/favicon', [SettingController::class, 'uploadFavicon'])->name('api.settings.favicon.upload');
        Route::delete('settings/logo', [SettingController::class, 'deleteLogo'])->name('api.settings.logo.delete');
        Route::delete('settings/favicon', [SettingController::class, 'deleteFavicon'])->name('api.settings.favicon.delete');
    });
});
