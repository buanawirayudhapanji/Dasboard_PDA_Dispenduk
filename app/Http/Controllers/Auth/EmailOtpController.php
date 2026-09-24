<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\IssueLoginOtp;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmailOtpController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Sesi verifikasi tidak ditemukan. Silakan login kembali.',
            ]);
        }

        [$localPart, $domain] = explode('@', $user->email, 2);
        $maskedEmail = Str::substr($localPart, 0, 2).'***@'.$domain;

        return view('pages.auth.email-otp', ['maskedEmail' => $maskedEmail]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user || $this->hasExpired($request)) {
            $this->clearPendingLogin($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Kode OTP telah kedaluwarsa. Silakan login kembali.',
            ]);
        }

        $attempts = (int) $request->session()->get(IssueLoginOtp::SESSION_ATTEMPTS, 0);
        $maxAttempts = (int) config('auth.email_otp.max_attempts');
        $expectedHash = (string) $request->session()->get(IssueLoginOtp::SESSION_HASH);

        if ($attempts >= $maxAttempts || ! hash_equals($expectedHash, IssueLoginOtp::hash($validated['otp']))) {
            $attempts++;

            if ($attempts >= $maxAttempts) {
                $this->clearPendingLogin($request);

                return redirect()->route('login')->withErrors([
                    'email' => 'Batas percobaan OTP tercapai. Silakan login kembali.',
                ]);
            }

            $request->session()->put(IssueLoginOtp::SESSION_ATTEMPTS, $attempts);

            return back()->withErrors([
                'otp' => 'Kode OTP tidak valid.',
            ]);
        }

        $this->clearPendingLogin($request);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request, IssueLoginOtp $issueLoginOtp): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Sesi verifikasi tidak ditemukan. Silakan login kembali.',
            ]);
        }

        $issueLoginOtp->handle($user, $request);

        return back()->with('status', 'Kode OTP baru telah dikirim ke email Anda.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get(IssueLoginOtp::SESSION_USER_ID);

        return is_numeric($userId) ? User::query()->find((int) $userId) : null;
    }

    private function hasExpired(Request $request): bool
    {
        $expiresAt = (int) $request->session()->get(IssueLoginOtp::SESSION_EXPIRES_AT, 0);

        return $expiresAt === 0 || now()->timestamp > $expiresAt;
    }

    private function clearPendingLogin(Request $request): void
    {
        $request->session()->forget(IssueLoginOtp::sessionKeys());
    }
}
