<x-layouts::auth title="Verifikasi OTP">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Verifikasi OTP email"
            description="Masukkan 6 digit kode yang dikirim ke {{ $maskedEmail }}. Kode berlaku selama 10 menit."
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.otp.store') }}" class="flex flex-col gap-6">
            @csrf

            <div class="flex justify-center">
                <flux:otp
                    name="otp"
                    label="Kode OTP"
                    length="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                />
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                Verifikasi dan Login
            </flux:button>
        </form>

        <form method="POST" action="{{ route('login.otp.resend') }}" class="text-center">
            @csrf

            <flux:button type="submit" variant="ghost" size="sm">
                Kirim ulang kode OTP
            </flux:button>
        </form>

        <flux:link :href="route('login')" class="text-center" wire:navigate>
            Kembali ke login
        </flux:link>
    </div>
</x-layouts::auth>
