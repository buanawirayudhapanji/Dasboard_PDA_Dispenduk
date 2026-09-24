<?php

namespace App\Http\Responses;

use App\Actions\Auth\IssueLoginOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private IssueLoginOtp $issueLoginOtp) {}

    public function toResponse($request): RedirectResponse
    {
        if (! $request->user() instanceof User) {
            return redirect()->route('login');
        }

        $user = $request->user();

        Auth::logout();
        $this->issueLoginOtp->handle($user, $request);

        return redirect()
            ->route('login.otp')
            ->with('status', 'Kode OTP telah dikirim ke email Anda.');
    }
}
