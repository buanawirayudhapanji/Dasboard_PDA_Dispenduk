<?php

use App\Actions\Auth\IssueLoginOtp;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Notification;

it('authenticates a user after the correct email otp', function () {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $otp = null;

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    Notification::assertSentTo(
        $user,
        LoginOtpNotification::class,
        function (LoginOtpNotification $notification) use (&$otp): bool {
            $otp = $notification->code;

            return mb_strlen($notification->code) === 6;
        },
    );
    expect($otp)->toBeString();

    $this->post(route('login.otp.store'), ['otp' => $otp])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects an incorrect email otp', function () {
    Notification::fake();
    $user = User::factory()->create();
    $issuedOtp = null;

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    Notification::assertSentTo(
        $user,
        LoginOtpNotification::class,
        function (LoginOtpNotification $notification) use (&$issuedOtp): bool {
            $issuedOtp = $notification->code;

            return true;
        },
    );
    $incorrectOtp = $issuedOtp === '000000' ? '111111' : '000000';

    $this->post(route('login.otp.store'), ['otp' => $incorrectOtp])
        ->assertSessionHasErrors(['otp' => 'Kode OTP tidak valid.']);

    $this->assertGuest();
});

it('rejects an expired email otp', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->withSession([
        IssueLoginOtp::SESSION_EXPIRES_AT => now()->subMinute()->timestamp,
    ])->post(route('login.otp.store'), ['otp' => '123456'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Kode OTP telah kedaluwarsa. Silakan login kembali.']);

    $this->assertGuest();
});

it('resends a new email otp for a pending login', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('login.otp.resend'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Kode OTP baru telah dikirim ke email Anda.');

    Notification::assertSentTimes(LoginOtpNotification::class, 2);
    $this->assertGuest();
});

it('uses a twelve hour session lifetime', function () {
    expect(config('session.lifetime'))->toBe(720);
});
