<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

test('reviewer profile experience sync route is registered', function () {
    expect(Route::has('admin.database-fixing.experience.reviewer-profile'))->toBeTrue();
});

test('admin can access experience reviewer-profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.experience.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Transformation Summary');
    $response->assertSee('Reviewer Profile');
    $response->assertSee('User ID Sync');
});

test('experience reviewer profile sync tool is listed on the database fixing index page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.index'));
    $response->assertStatus(200);
    $response->assertSee('reviewer_profile');
});

test('component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.experience.reviewer-profile')
        ->assertSee('Transformation Summary')
        ->call('setTab', 'missing_in_profile')
        ->assertSet('tab', 'missing_in_profile')
        ->call('setTab', 'no_user_id')
        ->assertSet('tab', 'no_user_id');
});
