<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Single-owner app: only login and logout. No registration, email verification or emailed
// password reset. Create or reset the owner account with `php artisan recipes:create-owner`.
Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
