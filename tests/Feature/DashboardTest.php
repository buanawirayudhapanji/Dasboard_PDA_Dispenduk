<?php

use App\Models\User;

it('renders the public dashboard without authentication', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Kependudukan Jember')
        ->assertSee('Login Petugas');
});

it('shows the data entry shortcut to authenticated staff', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Input Data');
});
