<?php

use App\Modules\Ref\Http\Controllers\RefListController;
use App\Modules\Ref\Http\Controllers\SpatialController;
use App\Modules\Ref\Http\Middleware\RequirePermission;
use App\Modules\Ref\Models\Sempadan;
use App\Modules\Ref\RefServiceProvider;
use Illuminate\Support\Facades\Route;

/*
| Read-only reference data, mounted at /api/v1/ref. Authenticated users with
| ref.rujukan.lihat (meant for every role — see report to MOD-RBC).
*/

Route::middleware(['auth:sanctum', RequirePermission::class.':'.RefServiceProvider::PERMISSION_VIEW])->group(function () {
    $lists = implode('|', array_keys(RefListController::LISTS));
    $boundaries = implode('|', Sempadan::JENIS);

    Route::get('tempat-ibadat', [SpatialController::class, 'placesOfWorship'])->name('tempat-ibadat');
    Route::get('sempadan/{jenis}', [SpatialController::class, 'index'])->where('jenis', $boundaries)->name('sempadan.index');
    Route::get('{jenis}/{kod}/sempadan', [SpatialController::class, 'show'])->where('jenis', $boundaries)->name('sempadan.show');

    Route::get('{senarai}', [RefListController::class, 'index'])->where('senarai', $lists)->name('senarai');
    Route::get('{senarai}/{kod}/versi', [RefListController::class, 'versions'])->where('senarai', $lists)->name('senarai.versi');
});
