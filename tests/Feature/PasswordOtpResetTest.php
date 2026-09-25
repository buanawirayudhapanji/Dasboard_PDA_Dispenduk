<?php

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Support\Facades\Notification;

it('sends an email otp and resets a password with the verified code', function () {
    Notification::fake();
    $user = User::factory()->create();
    $code = null;

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.reset'));

    Notification::assertSentTo($user, PasswordResetOtpNotification::class, function (PasswordResetOtpNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    $this->post(route('password.update'), [
        'otp' => $code,
        'password' => 'NewPassword789$',
        'password_confirmation' => 'NewPassword789$',
    ])->assertRedirect(route('login'));

    $this->post(route('login.store'), ['nik' => $user->nik, 'password' => 'NewPassword789$'])
        ->assertRedirect(route('dashboard'));
});
