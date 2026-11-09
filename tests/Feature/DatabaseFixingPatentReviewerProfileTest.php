<?php

use App\Models\Patent;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::forget('db_fixing_patent_issues');
});

test('patent reviewer profile database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.patent.reviewer-profile'))->toBeTrue();
});

test('admin can access patent reviewer profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.patent.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Patent — Reviewer Profile');
    $response->assertSee('No User ID');
    $response->assertSee('Missing in Profile');
    $response->assertSee('Ghost Profile IDs');
});

test('patent reviewer profile sync tool is listed on database fixing index page', function () {
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
    $response->assertSee('Patent');
    $response->assertSee('reviewer_profile &amp; user_id Sync (Missing IDs, Ghost IDs &amp; Status Fixer)', false);
});

test('patent reviewer profile component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.patent.reviewer-profile')
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

test('patent sync can recover user_id from reviewer profile array', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Patent Inventor',
        'email' => 'inventor@test.com',
        'password' => bcrypt('secret'),
    ]);

    // Create a Patent with NO user_id
    $patent = Patent::create([
        'title' => 'Quantum Computing Circuit Architecture',
        'user_id' => null,
        'status' => 1,
    ]);

    $pidStr = (string) $patent->_id;
    $uidStr = (string) $user->_id;

    // Create ReviewerProfile referencing this patent
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'patent' => [$pidStr],
        'status' => 1,
    ]);

    Cache::forget('db_fixing_patent_issues');

    $test = Volt::test('admin.database-fixing.patent.reviewer-profile');
    $test->assertSee('Quantum Computing Circuit Architecture');
    $test->assertSee('Assign User (Status 1)');

    // Fix the issue
    $test->call('fixSingle', "nouser_{$pidStr}");

    // Verify in database: user_id is restored and status is 1
    $updatedPatent = Patent::find($patent->_id);
    expect((string) $updatedPatent->user_id)->toBe($uidStr);
    expect((int) $updatedPatent->status)->toBe(1);

    // Clean up
    $patent->delete();
    $profile->delete();
    $user->delete();
});

test('patent sync can add unlinked patent to reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Unlinked Inventor',
        'email' => 'unlinked@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;

    // Create ReviewerProfile with empty patent array
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'patent' => [],
        'status' => 1,
    ]);

    // Create Patent belonging to this user
    $patent = Patent::create([
        'title' => 'Autonomous Drone Navigation System',
        'user_id' => $uidStr,
        'status' => 1,
    ]);

    $pidStr = (string) $patent->_id;
    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_patent_issues');

    $test = Volt::test('admin.database-fixing.patent.reviewer-profile')
        ->set('tab', 'missing_in_profile');
    $test->assertSee('Autonomous Drone Navigation System');
    $test->assertSee('Add to Profile');

    // Fix the issue
    $test->call('fixSingle', "missing_{$rpidStr}_{$pidStr}");

    // Verify profile now contains the patent ID
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->patent)->toContain($pidStr);

    // Clean up
    $patent->delete();
    $profile->delete();
    $user->delete();
});

test('patent sync can remove ghost IDs from reviewer profiles', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Ghost Profile User',
        'email' => 'ghost@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;
    $ghostPatentId = '654321098765432109876543';

    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'patent' => [$ghostPatentId],
        'status' => 1,
    ]);

    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_patent_issues');

    $test = Volt::test('admin.database-fixing.patent.reviewer-profile')
        ->set('tab', 'ghost_in_profile');
    $test->assertSee('Ghost ID');
    $test->assertSee('Remove Ghost');

    // Fix the ghost issue
    $test->call('fixSingle', "ghost_{$rpidStr}_{$ghostPatentId}");

    // Verify profile patent array is now empty
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->patent)->not->toContain($ghostPatentId);

    // Clean up
    $profile->delete();
    $user->delete();
});

test('patent sync marks true orphan patents as status 0', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create an orphan Patent (no user and not in any reviewer profile) with status = 1
    $orphanPatent = Patent::create([
        'title' => 'True Orphan Solar Panel Design',
        'user_id' => null,
        'status' => 1,
    ]);

    $pidStr = (string) $orphanPatent->_id;

    Cache::forget('db_fixing_patent_issues');

    $test = Volt::test('admin.database-fixing.patent.reviewer-profile');
    $test->assertSee('True Orphan Solar Panel Design');
    $test->assertSee('Mark Orphan (Status 0)');

    // Fix the orphan
    $test->call('fixSingle', "nouser_{$pidStr}");

    // Verify patent status is now 0 (Inactive)
    $updated = Patent::find($orphanPatent->_id);
    expect((int) $updated->status)->toBe(0);

    // Clean up
    $orphanPatent->delete();
});
