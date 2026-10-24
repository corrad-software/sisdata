<?php

// This application is a pure JSON API — see routes/api.php. The Next.js
// frontend in /frontend is the only consumer of these routes now; nothing
// under the "web" middleware group renders UI, aside from Sanctum's own
// /sanctum/csrf-cookie route (registered by its service provider) and the
// OIDC redirect/callback endpoints below.

use App\Http\Controllers\Auth\OidcController;
use Illuminate\Support\Facades\Route;

// SRS-RBC-01: SSO MAIS (OIDC authorization-code flow). These need the "web"
// group for the session (Socialite keeps `state`/PKCE verifier there, and
// the callback issues the same session cookie as the password login). The
// frontend proxies /auth/* to here so the flow stays on the :3000 origin.
Route::get('auth/oidc/redirect', [OidcController::class, 'redirect'])->name('oidc.redirect');
Route::get('auth/oidc/callback', [OidcController::class, 'callback'])->name('oidc.callback');
