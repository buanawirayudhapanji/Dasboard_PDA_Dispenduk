<?php

use App\Http\Controllers\Auth\PasswordOtpController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::dashboard')->name('home');
Route::redirect('dashboard', '/')->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [PasswordOtpController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordOtpController::class, 'store'])->middleware('throttle:password-otp')->name('password.email');
    Route::get('reset-password', [PasswordOtpController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordOtpController::class, 'update'])->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('input-data', 'pages::data-entry')->name('data-entry');
    Route::livewire('edit-data', 'pages::data-entry')->name('data-edit');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::livewire('kelola-petugas', 'pages::staff-management')->name('staff-management');
});

require __DIR__.'/settings.php';
