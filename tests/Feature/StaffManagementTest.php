<?php

use App\Models\User;
use Livewire\Livewire;

it('prevents staff from accessing staff management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('staff-management'))
        ->assertForbidden();
});

it('allows an admin to create and deactivate a staff account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::staff-management')
        ->set('name', 'Petugas Baru')
        ->set('nik', '3508153103040003')
        ->set('email', 'petugas@example.test')
        ->set('password', 'Qwerty789$')
        ->call('createStaff')
        ->assertHasNoErrors();

    $staff = User::query()->where('nik', '3508153103040003')->firstOrFail();

    Livewire::actingAs($admin)
        ->test('pages::staff-management')
        ->call('toggleActive', $staff->id)
        ->assertHasNoErrors();

    expect($staff->refresh()->is_active)->toBeFalse();
});

it('rejects an inactive account during nik login', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['nik' => $user->nik, 'password' => 'password'])
        ->assertSessionHasErrors('nik');

    $this->assertGuest();
});
