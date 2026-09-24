<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode OTP Login Dashboard Kependudukan Jember')
            ->greeting('Halo Petugas,')
            ->line('Gunakan kode berikut untuk menyelesaikan login:')
            ->line($this->code)
            ->line('Kode ini berlaku selama '.config('auth.email_otp.expires_minutes').' menit dan hanya dapat digunakan satu kali.')
            ->line('Abaikan email ini jika Anda tidak mencoba login.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
