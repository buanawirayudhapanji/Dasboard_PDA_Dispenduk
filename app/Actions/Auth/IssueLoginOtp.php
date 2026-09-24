<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Http\Request;

class IssueLoginOtp
{
    public const SESSION_USER_ID = 'login_otp.user_id';

    public const SESSION_HASH = 'login_otp.hash';

    public const SESSION_EXPIRES_AT = 'login_otp.expires_at';

    public const SESSION_ATTEMPTS = 'login_otp.attempts';

    public function handle(User $user, Request $request): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->notify(new LoginOtpNotification($code));

        $request->session()->put([
            self::SESSION_USER_ID => $user->getKey(),
            self::SESSION_HASH => self::hash($code),
            self::SESSION_EXPIRES_AT => now()->addMinutes((int) config('auth.email_otp.expires_minutes'))->timestamp,
            self::SESSION_ATTEMPTS => 0,
        ]);
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** @return list<string> */
    public static function sessionKeys(): array
    {
        return [
            self::SESSION_USER_ID,
            self::SESSION_HASH,
            self::SESSION_EXPIRES_AT,
            self::SESSION_ATTEMPTS,
        ];
    }
}
