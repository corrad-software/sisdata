<?php

use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('users', UserManagement::class)
    ->middleware(['auth'])
    ->name('users.index');

require __DIR__.'/auth.php';
