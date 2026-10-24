<?php

use App\Modules\Platform\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('status', StatusController::class)->name('status');
});
