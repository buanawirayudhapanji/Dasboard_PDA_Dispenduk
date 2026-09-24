<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <script>
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        </script>
    </head>
    <body class="min-h-screen bg-[#fff6fa] text-[#3d1228] antialiased">
        <header class="sticky top-0 z-30 border-b border-pink-100 bg-white/90 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-6 lg:px-8">
                <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-pink-500 to-rose-400 text-sm font-bold text-white shadow-sm">DJ</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">Dashboard Kependudukan Jember</span>
                        <span class="hidden text-xs text-pink-500 sm:block">Dispendukcapil Kabupaten Jember</span>
                    </span>
                </a>

                <nav class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('data-entry')" variant="primary" size="sm" icon="plus" wire:navigate>Input Data</flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon-trailing="chevron-down">{{ auth()->user()->name }}</flux:button>
                            <flux:menu>
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>Pengaturan</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">Keluar</flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <flux:button :href="route('login')" variant="primary" size="sm" icon="arrow-right-end-on-rectangle">Login Petugas</flux:button>
                    @endauth
                </nav>
            </div>
        </header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
