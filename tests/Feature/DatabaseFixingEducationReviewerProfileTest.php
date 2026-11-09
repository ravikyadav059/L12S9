<?php

use App\Models\Education;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::forget('db_fixing_education_issues');
});

test('education reviewer profile database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.education.reviewer-profile'))->toBeTrue();
    expect(Route::has('admin.database-fixing.education.index'))->toBeTrue();
});

test('admin can access education reviewer profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.education.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Education — Reviewer Profile');
    $response->assertSee('No User ID');
    $response->assertSee('Missing in Profile');
    $response->assertSee('Ghost Profile IDs');
});

test('education reviewer profile sync tool is listed on database fixing index page', function () {
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
    $response->assertSee('Education');
    $response->assertSee('reviewer_profile &amp; user_id Sync (Missing IDs, Ghost IDs &amp; Status Fixer)', false);
});

test('education reviewer profile component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.education.reviewer-profile')
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

test('education sync can recover user_id from reviewer profile array', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Education Scholar',
        'email' => 'edu_scholar@test.com',
        'password' => bcrypt('secret'),
    ]);

    // Create an Education record with NO user_id
    $education = Education::create([
        'degree_type' => 'Doctor of Science',
        'field_of_study' => 'Applied Mathematics',
        'user_id' => null,
        'status' => 1,
    ]);

    $eidStr = (string) $education->_id;
    $uidStr = (string) $user->_id;

    // Create ReviewerProfile referencing this education
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'education' => [$eidStr],
        'status' => 1,
    ]);

    Cache::forget('db_fixing_education_issues');

    $test = Volt::test('admin.database-fixing.education.reviewer-profile');
    $test->assertSee('Assign User (Status 1)');

    // Fix the issue
    $test->call('fixSingle', "nouser_{$eidStr}");

    // Verify in database: user_id is restored and status is 1
    $updated = Education::find($education->_id);
    expect((string) $updated->user_id)->toBe($uidStr);
    expect((int) $updated->status)->toBe(1);

    // Clean up
    $education->delete();
    $profile->delete();
    $user->delete();
});

test('education sync can add unlinked education to reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Unlinked Graduate',
        'email' => 'unlinked_grad@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;

    // Create ReviewerProfile with empty education array
    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'education' => [],
        'status' => 1,
    ]);

    // Create Education belonging to this user
    $education = Education::create([
        'degree_type' => 'Bachelor of Engineering',
        'field_of_study' => 'Aerospace Engineering',
        'user_id' => $uidStr,
        'status' => 1,
    ]);

    $eidStr = (string) $education->_id;
    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_education_issues');

    $test = Volt::test('admin.database-fixing.education.reviewer-profile')
        ->set('tab', 'missing_in_profile');
    $test->assertSee('Add to Profile');

    // Fix the issue
    $test->call('fixSingle', "missing_{$rpidStr}_{$eidStr}");

    // Verify profile now contains the education ID
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->education)->toContain($eidStr);

    // Clean up
    $education->delete();
    $profile->delete();
    $user->delete();
});

test('education sync can remove ghost IDs from reviewer profiles', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'Dr. Ghost Graduate Profile',
        'email' => 'ghost_grad@test.com',
        'password' => bcrypt('secret'),
    ]);

    $uidStr = (string) $user->_id;
    $ghostId = '654321098765432109876543';

    $profile = ReviewerProfile::create([
        'user_id' => $uidStr,
        'education' => [$ghostId],
        'status' => 1,
    ]);

    $rpidStr = (string) $profile->_id;

    Cache::forget('db_fixing_education_issues');

    $test = Volt::test('admin.database-fixing.education.reviewer-profile')
        ->set('tab', 'ghost_in_profile');
    $test->assertSee('Ghost ID');
    $test->assertSee('Remove Ghost');

    // Fix the ghost issue
    $test->call('fixSingle', "ghost_{$rpidStr}_{$ghostId}");

    // Verify profile education array is now empty
    $updatedProfile = ReviewerProfile::find($profile->_id);
    expect($updatedProfile->education)->not->toContain($ghostId);

    // Clean up
    $profile->delete();
    $user->delete();
});

test('education sync marks true orphan educations as status 0', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create an orphan Education (no user and not in any reviewer profile) with status = 1
    $orphanEducation = Education::create([
        'degree_type' => 'True Orphan Associate Degree',
        'user_id' => null,
        'status' => 1,
    ]);

    $eidStr = (string) $orphanEducation->_id;

    Cache::forget('db_fixing_education_issues');

    $test = Volt::test('admin.database-fixing.education.reviewer-profile');
    $test->assertSee('Mark Orphan (Status 0)');

    // Fix the orphan
    $test->call('fixSingle', "nouser_{$eidStr}");

    // Verify education status is now 0 (Inactive)
    $updated = Education::find($orphanEducation->_id);
    expect((int) $updated->status)->toBe(0);

    // Clean up
    $orphanEducation->delete();
});
