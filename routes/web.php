<?php

use App\Http\Controllers\Auth\EmailOtpController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::dashboard')->name('home');
Route::redirect('dashboard', '/')->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login/otp', [EmailOtpController::class, 'show'])->name('login.otp');
    Route::post('login/otp', [EmailOtpController::class, 'store'])
        ->middleware('throttle:email-otp')
        ->name('login.otp.store');
    Route::post('login/otp/resend', [EmailOtpController::class, 'resend'])
        ->middleware('throttle:email-otp-resend')
        ->name('login.otp.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('input-data', 'pages::data-entry')->name('data-entry');
    Route::livewire('edit-data', 'pages::data-entry')->name('data-edit');
});

require __DIR__.'/settings.php';
