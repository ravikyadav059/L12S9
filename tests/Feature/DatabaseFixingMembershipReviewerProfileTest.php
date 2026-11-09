<?php

use App\Models\Membership;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::forget('db_fixing_membership_issues');
});

test('membership reviewer profile database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.membership.reviewer-profile'))->toBeTrue();
});

test('admin can access membership reviewer profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.membership.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Membership — Reviewer Profile');
    $response->assertSee('No User ID');
    $response->assertSee('Missing in Profile');
    $response->assertSee('Ghost Profile IDs');
});

test('membership reviewer profile sync tool is listed on database fixing index page', function () {
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
    $response->assertSee('Membership');
    $response->assertSee('reviewer_profile &amp; user_id Sync (Missing IDs, Ghost IDs &amp; Status Fixer)', false);
});

test('membership reviewer profile component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.membership.reviewer-profile')
        ->assertSee('Total Sync Issues')
        ->set('tab', 'no_user_id')
        ->assertSet('tab', 'no_user_id')
        ->set('tab', 'missing_in_profile')
        ->assertSet('tab', 'missing_in_profile')
        ->set('tab', 'ghost_in_profile')
        ->assertSet('tab', 'ghost_in_profile')
        ->set('tab', 'all')
        ->assertSet('tab', 'all');
});

test('membership sync can recover user_id from reviewer profile array', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Membership Scholar',
        'email' => 'scholar_m@test.com',
        'password' => bcrypt('secret'),
    ]);

    // Create a Membership with NO user_id
    $membership = Membership::create([
        'membership_type' => 'Senior IEEE Fellow',
        'user_id' => null,
        'status' => 1,
    ]);

    $midStr = (string) $membership->_id;
    $uidStr = (string) $user->_id;

    // Create ReviewerProfile referencing this membership
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'membership' => [$midStr],
        'status' => 1,
    ]);

    Cache::forget('db_fixing_membership_issues');

    $test = Volt::test('admin.database-fixing.membership.reviewer-profile');
    $test->assertSee('Senior IEEE Fellow');
    $test->assertSee('Assign User (Status 1)');

    // Fix the issue
    $test->call('fixSingle', "nouser_{$midStr}");

    // Verify in database: user_id is restored and status is 1
    $updated = Membership::find($membership->_id);
    expect((string) $updated->user_id)->toBe($uidStr);
    expect((int) $updated->status)->toBe(1);

    // Clean up
    $membership->delete();
    $profile->delete();
    $user->delete();
});

test('membership sync can add unlinked membership to reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Unlinked Member',
        'email' => 'unlinked_m@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;

    // Create ReviewerProfile with empty membership array
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'membership' => [],
        'status' => 1,
    ]);

    // Create Membership belonging to this user
    $membership = Membership::create([
        'membership_type' => 'ACM Professional Member',
        'user_id' => $uidStr,
        'status' => 1,
    ]);

    $midStr = (string) $membership->_id;
    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_membership_issues');

    $test = Volt::test('admin.database-fixing.membership.reviewer-profile')
        ->set('tab', 'missing_in_profile');
    $test->assertSee('ACM Professional Member');
    $test->assertSee('Add to Profile');

    // Fix the issue
    $test->call('fixSingle', "missing_{$rpidStr}_{$midStr}");

    // Verify profile now contains the membership ID
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->membership)->toContain($midStr);

    // Clean up
    $membership->delete();
    $profile->delete();
    $user->delete();
});

test('membership sync can remove ghost IDs from reviewer profiles', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Ghost Member Profile',
        'email' => 'ghost_m@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;
    $ghostId = '654321098765432109876543';

    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'membership' => [$ghostId],
        'status' => 1,
    ]);

    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_membership_issues');

    $test = Volt::test('admin.database-fixing.membership.reviewer-profile')
        ->set('tab', 'ghost_in_profile');
    $test->assertSee('Ghost ID');
    $test->assertSee('Remove Ghost');

    // Fix the ghost issue
    $test->call('fixSingle', "ghost_{$rpidStr}_{$ghostId}");

    // Verify profile membership array is now empty
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->membership)->not->toContain($ghostId);

    // Clean up
    $profile->delete();
    $user->delete();
});

test('membership sync marks true orphan memberships as status 0', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create an orphan Membership (no user and not in any reviewer profile) with status = 1
    $orphanMembership = Membership::create([
        'membership_type' => 'True Orphan Society Member',
        'user_id' => null,
        'status' => 1,
    ]);

    $midStr = (string) $orphanMembership->_id;

    Cache::forget('db_fixing_membership_issues');

    $test = Volt::test('admin.database-fixing.membership.reviewer-profile');
    $test->assertSee('True Orphan Society Member');
    $test->assertSee('Mark Orphan (Status 0)');

    // Fix the orphan
    $test->call('fixSingle', "nouser_{$midStr}");

    // Verify membership status is now 0 (Inactive)
    $updated = Membership::find($orphanMembership->_id);
    expect((int) $updated->status)->toBe(0);

    // Clean up
    $orphanMembership->delete();
});
