@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Dashboard Kependudukan" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center overflow-hidden">
            <img src="{{ asset('images/lambang-kabupaten-jember.png') }}" alt="Lambang Kabupaten Jember" class="size-full object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Dashboard Kependudukan" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center overflow-hidden">
            <img src="{{ asset('images/lambang-kabupaten-jember.png') }}" alt="Lambang Kabupaten Jember" class="size-full object-contain" />
        </x-slot>
    </flux:brand>
@endif
