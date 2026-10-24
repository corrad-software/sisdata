<?php

use App\Modules\Wfe\Http\Controllers\StepUpController;
use Illuminate\Support\Facades\Route;

// SRS-WFE-19: MFA step-up at SSO MAIS. "web" group (session) like the RBC
// login routes; reached through the Next.js /auth/* rewrite.
Route::get('auth/wfe/stepup', [StepUpController::class, 'redirect'])->name('wfe.stepup');
Route::get('auth/wfe/stepup/callback', [StepUpController::class, 'callback'])->name('wfe.stepup.callback');
