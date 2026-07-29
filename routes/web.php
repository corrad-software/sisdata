<?php

use App\Http\Middleware\SetLocale;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, SetLocale::LOCALES, true), 404);

    session(['locale' => $locale]);

    return back();
})->name('locale.switch');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::get('users', UserManagement::class)->name('users.index');

    Route::prefix('kitchen-sink')->name('kitchen-sink.')->group(function () {
        Route::redirect('/', '/admin/kitchen-sink/components/buttons');

        Route::prefix('foundation')->name('foundation.')->group(function () {
            Route::redirect('/', '/admin/kitchen-sink/foundation/colour');
            Route::view('colour', 'admin.kitchen-sink.foundation.colour')->name('colour');
            Route::view('typography', 'admin.kitchen-sink.foundation.typography')->name('typography');
            Route::view('layout-spacing', 'admin.kitchen-sink.foundation.layout-spacing')->name('layout-spacing');
            Route::view('style', 'admin.kitchen-sink.foundation.style')->name('style');
        });

        Route::prefix('components')->name('components.')->group(function () {
            Route::redirect('/', '/admin/kitchen-sink/components/buttons');
            Route::view('buttons', 'admin.kitchen-sink.components.buttons')->name('buttons');
            Route::view('forms', 'admin.kitchen-sink.components.forms')->name('forms');
            Route::view('data-display', 'admin.kitchen-sink.components.data-display')->name('data-display');
            Route::view('feedback', 'admin.kitchen-sink.components.feedback')->name('feedback');
            Route::view('content', 'admin.kitchen-sink.components.content')->name('content');
            Route::view('navigation', 'admin.kitchen-sink.components.navigation')->name('navigation');
            Route::view('overlays', 'admin.kitchen-sink.components.overlays')->name('overlays');
            Route::view('progress', 'admin.kitchen-sink.components.progress')->name('progress');
        });

        Route::prefix('data-visual')->name('data-visual.')->group(function () {
            Route::redirect('/', '/admin/kitchen-sink/data-visual/dashboard');
            Route::view('dashboard', 'admin.kitchen-sink.data-visual.dashboard')->name('dashboard');
            Route::view('stats-view', 'admin.kitchen-sink.data-visual.stats-view')->name('stats-view');
        });

        Route::prefix('sample-pages')->name('sample-pages.')->group(function () {
            Route::redirect('/', '/admin/kitchen-sink/sample-pages/login');
            Route::view('login', 'admin.kitchen-sink.sample-pages.login')->name('login');
            Route::view('register', 'admin.kitchen-sink.sample-pages.register')->name('register');
            Route::view('not-found', 'admin.kitchen-sink.sample-pages.not-found')->name('not-found');
            Route::view('empty-state', 'admin.kitchen-sink.sample-pages.empty-state')->name('empty-state');
        });
    });
});

require __DIR__.'/auth.php';
