<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

test('reviewer profile award sync route is registered', function () {
    expect(Route::has('admin.database-fixing.award.reviewer-profile'))->toBeTrue();
    expect(Route::has('admin.database-fixing.award.index'))->toBeTrue();
});

test('admin can access award reviewer-profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.award.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Transformation Summary');
    $response->assertSee('Award');
    $response->assertSee('Reviewer Profile');
});

test('award reviewer profile sync tool is listed on the database fixing index page', function () {
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
    $response->assertSee('Award');
    $response->assertSee('reviewer_profile &amp; user_id Sync', false);
});

test('award component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.award.reviewer-profile')
        ->assertSee('Transformation Summary')
        ->call('setTab', 'no_user_id')
        ->assertSet('tab', 'no_user_id')
        ->call('setTab', 'missing_in_profile')
        ->assertSet('tab', 'missing_in_profile');
});
