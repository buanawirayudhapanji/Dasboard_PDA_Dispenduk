<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Login Petugas" description="Masukkan NIK dan password Anda." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="nik"
                label="NIK"
                :value="old('nik')"
                inputmode="numeric"
                required
                autofocus
                autocomplete="username"
                placeholder="16 digit NIK"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    Lanjutkan
                </flux:button>
            </div>
        </form>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            Akun petugas dikelola oleh administrator.
        </p>
    </div>
</x-layouts::auth>
