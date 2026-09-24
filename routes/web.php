<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified', 'password.change'])->group(function (): void {
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/password', [PasswordController::class, 'show'])->name('account.password');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
});
