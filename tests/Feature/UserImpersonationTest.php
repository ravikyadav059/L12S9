<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

test('admin can see View, Edit, Login as User, and Delete buttons in users management', function () {
    $admin = new User([
        'name' => 'Main Admin',
        'email' => 'admin_test@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Dr. Jane Scholar',
        'fullname' => 'Dr. Jane Scholar',
        'email' => 'jane_scholar@test.local',
        'role' => 'scholar',
        'is_admin' => false,
        'slug' => 'dr-jane-scholar-S9-012025-1000001',
    ]);
    $scholar->save();

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->assertSee('Add User')
        ->assertSee('Profile ID')
        ->assertSee('First Name')
        ->assertSee('Last Name')
        ->assertSee('Role')
        ->assertSee('Status')
        ->assertSee('Actions')
        ->assertSee('All Users')
        ->assertSee('Pending (0)')
        ->assertSee('Active (1)')
        ->assertSee('Re-Document (2)')
        ->assertSee('Reject (3)')
        ->assertSee('Administrators')
        ->assertDontSee('Affiliation')
        ->assertDontSee('Registered')
        ->assertSee('View')
        ->assertSee('Edit')
        ->assertSee('Login as User')
        ->assertSee('Delete')
        ->assertSee(route('userscholar', ['slug' => 'dr-jane-scholar-S9-012025-1000001']));
});

test('admin can impersonate user and session is updated', function () {
    $admin = new User([
        'name' => 'Admin Controller',
        'email' => 'admin_ctrl@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Target Scholar',
        'fullname' => 'Target Scholar',
        'email' => 'target_scholar@test.local',
        'role' => 'scholar',
        'is_admin' => false,
        'slug' => 'target-scholar-S9-012025-1000002',
    ]);
    $scholar->save();

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->call('impersonate', (string) $scholar->_id)
        ->assertRedirect('/');

    expect(Auth::id())->toEqual((string) $scholar->_id);
    expect(session('impersonated_by'))->toEqual((string) $admin->_id);
});

test('impersonation banner shows currently browsing as and return to admin link', function () {
    $admin = new User([
        'name' => 'Original Admin',
        'email' => 'orig_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Browsed Scholar',
        'fullname' => 'Browsed Scholar',
        'email' => 'browsed_scholar@test.local',
        'role' => 'scholar',
        'is_admin' => false,
    ]);
    $scholar->save();

    $this->actingAs($scholar);
    session()->put('impersonated_by', (string) $admin->_id);

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('You are currently browsing as');
    $response->assertSee('Browsed Scholar');
    $response->assertSee('Click here to return to Admin');
    $response->assertSee(route('impersonate.leave'));
});

test('clicking return to admin restores admin session and redirects to admin users index', function () {
    $admin = new User([
        'name' => 'Master Admin',
        'email' => 'master_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Test User',
        'email' => 'test_user@test.local',
        'role' => 'scholar',
        'is_admin' => false,
    ]);
    $scholar->save();

    $this->actingAs($scholar);
    session()->put('impersonated_by', (string) $admin->_id);

    $response = $this->get(route('impersonate.leave'));

    $response->assertRedirect(route('admin.users.index'));
    expect(Auth::id())->toEqual((string) $admin->_id);
    expect(session()->has('impersonated_by'))->toBeFalse();
});

test('admin can access edit user page at admin/edit_users/{user} with unique_id', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'super_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Profile User',
        'first_name' => 'Profile',
        'last_name' => 'User',
        'email' => 'profile_user@test.local',
        'role' => 'scholar',
        'unique_id' => 'S9-032026-1006735',
        'status' => 0,
    ]);
    $scholar->save();

    $this->actingAs($admin);

    $editUrl = route('admin.users.edit', ['user' => 'S9-032026-1006735']);
    expect($editUrl)->toContain('/admin/edit_users/S9-032026-1006735');

    $response = $this->get($editUrl);
    $response->assertSuccessful();
    $response->assertSee('S9-032026-1006735');
    $response->assertSee('Edit User Profile');
    $response->assertSee('Please select Prefix');
    $response->assertSee('Dr.');
    $response->assertSee('Prof.');
    $response->assertSee('Dr. (Prof.)');
    $response->assertSee('Shree');
    $response->assertSee('Profile Status:');
    $response->assertSee('Pending');
    $response->assertSee('Active');
    $response->assertSee('Re-Document');
    $response->assertSee('Reject');
    $response->assertSee('Verified User:');
    $response->assertSee('Publisher Status:');
    $response->assertSee('Reviewer Status:');
    $response->assertSee('Badge Type:');
    $response->assertSee('Enable Conference for All Journals');
    $response->assertSee('Change Password');
});

test('admin can directly update status, verified, reviewer, badge, and conference on edit page', function () {
    $admin = new User([
        'name' => 'Admin Direct',
        'email' => 'direct_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Direct Scholar',
        'email' => 'scholar_direct@test.local',
        'unique_id' => 'S9-032026-1008888',
        'status' => 0,
    ]);
    $scholar->save();

    $this->actingAs($admin);

    Volt::test('admin.users.edit', ['user' => $scholar])
        ->call('updateProfileStatus', '1')
        ->call('updateVerifiedUser', '1')
        ->call('updatePublisherStatus', '1')
        ->call('updateReviewerStatus', '1')
        ->call('updateBadgeType', '2')
        ->call('toggleConference');

    $scholar->refresh();
    expect($scholar->getStatusCode())->toBe(1);
    expect($scholar->verified)->toBe(1);
    expect($scholar->publisher_status)->toBe(1);
    expect($scholar->reviewer_status)->toBe(1);
    expect($scholar->badge_type)->toBe('2');
    expect($scholar->enable_conference)->toBeTrue();
});

test('admin can update user prefix on edit page', function () {
    $admin = new User([
        'name' => 'Prefix Admin',
        'email' => 'prefix_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $scholar = new User([
        'name' => 'Prof User',
        'first_name' => 'Albert',
        'last_name' => 'Einstein',
        'email' => 'einstein@test.local',
        'unique_id' => 'S9-032026-1006999',
        'status' => 1,
    ]);
    $scholar->save();

    $this->actingAs($admin);

    Volt::test('admin.users.edit', ['user' => $scholar])
        ->set('prefix', 'Prof.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('changes saved successfully');

    $scholar->refresh();
    expect($scholar->prefix)->toBe('Prof.');
});

test('user list renders status badges for users', function () {
    $admin = new User([
        'name' => 'Status Admin',
        'email' => 'status_admin@test.local',
        'type' => 1,
    ]);
    $admin->save();

    $user = new User([
        'name' => 'Active Status User',
        'email' => 'active_status@test.local',
        'role' => 'scholar',
        'status' => '1',
    ]);
    $user->save();

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->assertSee('Active (1)');
});
