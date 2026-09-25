<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordOtpController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user !== null && $user->is_active) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $request->session()->put('password_otp', [
                'user_id' => $user->id,
                'hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
            $user->notify(new PasswordResetOtpNotification($code));
        }

        return redirect()->route('password.reset')->with('status', 'Jika email terdaftar, kode OTP telah dikirim.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        return $request->session()->has('password_otp')
            ? view('pages.auth.reset-password')
            : redirect()->route('password.request');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $otp = $request->session()->get('password_otp');
        $user = null;

        if (is_array($otp) && isset($otp['user_id']) && is_int($otp['user_id'])) {
            $user = User::query()->whereKey($otp['user_id'])->first();
        }

        if ($user === null || now()->timestamp > ($otp['expires_at'] ?? 0) || ! Hash::check($validated['otp'], $otp['hash'] ?? '')) {
            return back()->withErrors(['otp' => 'Kode OTP tidak valid atau telah kedaluwarsa.']);
        }

        $user->update(['password' => $validated['password']]);
        $request->session()->forget('password_otp');

        return redirect()->route('login')->with('status', 'Password berhasil diubah. Silakan login.');
    }
}
