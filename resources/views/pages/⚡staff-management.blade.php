<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kelola Petugas')] class extends Component
{
    public string $name = '';

    public string $nik = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'staff';

    public ?string $statusMessage = null;

    public function createStaff(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'digits:16', Rule::unique(User::class)],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', \Illuminate\Validation\Rules\Password::defaults()],
            'role' => ['required', Rule::in(['staff', 'admin'])],
        ]);

        User::query()->create([
            'name' => $this->name,
            'nik' => $this->nik,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->reset('name', 'nik', 'email', 'password');
        $this->role = 'staff';
        $this->statusMessage = 'Akun petugas berhasil dibuat.';
        unset($this->staffMembers);
    }

    public function toggleActive(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        abort_if($user->is(auth()->user()), 422, 'Anda tidak dapat menonaktifkan akun sendiri.');
        $user->update(['is_active' => ! $user->is_active]);
        $this->statusMessage = 'Status akun berhasil diperbarui.';
        unset($this->staffMembers);
    }

    public function toggleRole(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['role' => $user->isAdmin() ? 'staff' : 'admin']);
        $this->statusMessage = 'Peran akun berhasil diperbarui.';
        unset($this->staffMembers);
    }

    #[Computed]
    public function staffMembers()
    {
        return User::query()->orderBy('name')->get();
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6 bg-[#fff6fa] p-4 text-rose-950 sm:p-6">
    <div><flux:heading size="xl">Kelola Petugas</flux:heading><flux:text class="mt-2">Buat akun, atur peran admin, dan aktifkan atau nonaktifkan akses petugas.</flux:text></div>
    @if ($statusMessage)<flux:callout icon="check-circle" color="emerald"><flux:callout.text>{{ $statusMessage }}</flux:callout.text></flux:callout>@endif
    <section class="rounded-2xl border border-pink-100 bg-white p-5 shadow-sm shadow-pink-100/50">
        <flux:heading size="lg">Buat akun petugas</flux:heading>
        <form wire:submit="createStaff" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <flux:input wire:model="name" label="Nama" required /><flux:input wire:model="nik" label="NIK" inputmode="numeric" required />
            <flux:input wire:model="email" label="Email" type="email" required /><flux:input wire:model="password" label="Password awal" type="password" viewable required />
            <flux:field><flux:label>Peran</flux:label><flux:select wire:model="role"><option value="staff">Petugas</option><option value="admin">Admin</option></flux:select></flux:field>
            <div class="md:col-span-2 xl:col-span-5"><flux:button type="submit" variant="primary" icon="plus">Buat akun</flux:button></div>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50"><div class="border-b border-pink-100 px-5 py-4"><flux:heading size="lg">Daftar petugas</flux:heading></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-pink-50 text-pink-600"><tr><th class="px-5 py-3">Nama</th><th class="px-4 py-3">NIK</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Peran</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-pink-50">@foreach ($this->staffMembers as $member)<tr wire:key="staff-{{ $member->id }}"><td class="px-5 py-3 font-medium">{{ $member->name }}</td><td class="px-4 py-3 tabular-nums">{{ $member->nik }}</td><td class="px-4 py-3">{{ $member->email }}</td><td class="px-4 py-3"><span class="rounded-full bg-pink-100 px-2 py-1 text-xs font-semibold text-pink-700">{{ $member->isAdmin() ? 'Admin' : 'Petugas' }}</span></td><td class="px-4 py-3">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</td><td class="px-5 py-3 text-right"><flux:button size="sm" wire:click="toggleRole({{ $member->id }})">{{ $member->isAdmin() ? 'Jadikan petugas' : 'Jadikan admin' }}</flux:button><flux:button size="sm" class="ms-2" wire:click="toggleActive({{ $member->id }})" :disabled="$member->is(auth()->user())">{{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</flux:button></td></tr>@endforeach</tbody></table></div></section>
</div>
