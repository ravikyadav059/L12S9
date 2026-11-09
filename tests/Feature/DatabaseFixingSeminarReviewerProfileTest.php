<?php

use App\Models\ReviewerProfile;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::forget('db_fixing_seminar_issues');
});

test('seminar reviewer profile database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.seminar.reviewer-profile'))->toBeTrue();
});

test('admin can access seminar reviewer profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.seminar.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Seminar — Reviewer Profile');
    $response->assertSee('No User ID');
    $response->assertSee('Missing in Profile');
    $response->assertSee('Ghost Profile IDs');
});

test('seminar reviewer profile sync tool is listed on database fixing index page', function () {
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
    $response->assertSee('Seminar');
    $response->assertSee('reviewer_profile &amp; user_id Sync (Missing IDs, Ghost IDs &amp; Status Fixer)', false);
});

test('seminar reviewer profile component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.seminar.reviewer-profile')
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

test('seminar sync can recover user_id from reviewer profile array', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Seminar Speaker',
        'email' => 'speaker@test.com',
        'password' => bcrypt('secret'),
    ]);

    // Create a Seminar with NO user_id
    $seminar = Seminar::create([
        'title' => 'International Deep Learning Colloquium',
        'user_id' => null,
        'status' => 1,
    ]);

    $sidStr = (string) $seminar->_id;
    $uidStr = (string) $user->_id;

    // Create ReviewerProfile referencing this seminar
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'seminar' => [$sidStr],
        'status' => 1,
    ]);

    Cache::forget('db_fixing_seminar_issues');

    $test = Volt::test('admin.database-fixing.seminar.reviewer-profile');
    $test->assertSee('International Deep Learning Colloquium');
    $test->assertSee('Assign User (Status 1)');

    // Fix the issue
    $test->call('fixSingle', "nouser_{$sidStr}");

    // Verify in database: user_id is restored and status is 1
    $updated = Seminar::find($seminar->_id);
    expect((string) $updated->user_id)->toBe($uidStr);
    expect((int) $updated->status)->toBe(1);

    // Clean up
    $seminar->delete();
    $profile->delete();
    $user->delete();
});

test('seminar sync can add unlinked seminar to reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Unlinked Speaker',
        'email' => 'unlinked_speaker@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;

    // Create ReviewerProfile with empty seminar array
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'seminar' => [],
        'status' => 1,
    ]);

    // Create Seminar belonging to this user
    $seminar = Seminar::create([
        'title' => 'Advanced Robotics Keynote',
        'user_id' => $uidStr,
        'status' => 1,
    ]);

    $sidStr = (string) $seminar->_id;
    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_seminar_issues');

    $test = Volt::test('admin.database-fixing.seminar.reviewer-profile')
        ->set('tab', 'missing_in_profile');
    $test->assertSee('Advanced Robotics Keynote');
    $test->assertSee('Add to Profile');

    // Fix the issue
    $test->call('fixSingle', "missing_{$rpidStr}_{$sidStr}");

    // Verify profile now contains the seminar ID
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->seminar)->toContain($sidStr);

    // Clean up
    $seminar->delete();
    $profile->delete();
    $user->delete();
});

test('seminar sync can remove ghost IDs from reviewer profiles', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Ghost Seminar Profile',
        'email' => 'ghost_speaker@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;
    $ghostId = '654321098765432109876543';

    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'seminar' => [$ghostId],
        'status' => 1,
    ]);

    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_seminar_issues');

    $test = Volt::test('admin.database-fixing.seminar.reviewer-profile')
        ->set('tab', 'ghost_in_profile');
    $test->assertSee('Ghost ID');
    $test->assertSee('Remove Ghost');

    // Fix the ghost issue
    $test->call('fixSingle', "ghost_{$rpidStr}_{$ghostId}");

    // Verify profile seminar array is now empty
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->seminar)->not->toContain($ghostId);

    // Clean up
    $profile->delete();
    $user->delete();
});

test('seminar sync marks true orphan seminars as status 0', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create an orphan Seminar (no user and not in any reviewer profile) with status = 1
    $orphanSeminar = Seminar::create([
        'title' => 'True Orphan Symposium Record',
        'user_id' => null,
        'status' => 1,
    ]);

    $sidStr = (string) $orphanSeminar->_id;

    Cache::forget('db_fixing_seminar_issues');

    $test = Volt::test('admin.database-fixing.seminar.reviewer-profile');
    $test->assertSee('True Orphan Symposium Record');
    $test->assertSee('Mark Orphan (Status 0)');

    // Fix the orphan
    $test->call('fixSingle', "nouser_{$sidStr}");

    // Verify seminar status is now 0 (Inactive)
    $updated = Seminar::find($orphanSeminar->_id);
    expect((int) $updated->status)->toBe(0);

    // Clean up
    $orphanSeminar->delete();
});
