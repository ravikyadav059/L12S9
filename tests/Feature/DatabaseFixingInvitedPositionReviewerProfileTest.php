<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_invited_position_profile_issues');
});

test('invited position reviewer profile database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.invited-position.reviewer-profile'))->toBeTrue();
});

test('admin can access invited position reviewer profile sync page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.invited-position.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Invited Position — Reviewer Profile &amp; User ID Sync', false);
    $response->assertSee('Missing User ID');
    $response->assertSee('Ghost Profile IDs');
});

test('invited position reviewer profile component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.invited-position.reviewer-profile')
        ->assertSee('Invited Position — Reviewer Profile', false)
        ->call('setTab', 'no_user_id')
        ->assertSet('tab', 'no_user_id')
        ->call('setTab', 'missing_in_profile')
        ->assertSet('tab', 'missing_in_profile')
        ->call('setTab', 'ghost_in_profile')
        ->assertSet('tab', 'ghost_in_profile')
        ->call('setTab', 'missing_status')
        ->assertSet('tab', 'missing_status')
        ->call('setTab', 'all')
        ->assertSet('tab', 'all');
});

test('invited position sync attaches user_id to invited_position from reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $testUserId = (string) new ObjectId;

    // Create an invited position with no user_id
    $testPid = (string) DB::connection('mongodb')->table('invited_position')->insertGetId([
        'role' => 'Visiting Keynote Speaker',
        'status' => 1,
        'user_id' => null,
        'created_at' => now(),
    ]);

    // Create a reviewer profile linking to this invited position
    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'invited_position' => [$testPid],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_invited_position_profile_issues');

    try {
        Volt::test('admin.database-fixing.invited-position.reviewer-profile')
            ->call('fixSingle', "nouser_{$testPid}");

        // Verify invited_position now has user_id attached
        $updatedDoc = DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->first();
        expect((string) $updatedDoc->user_id)->toBe($testUserId);
        expect((int) $updatedDoc->status)->toBe(1);
    } finally {
        DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_invited_position_profile_issues');
    }
});

test('invited position sync marks orphan invited position with status = 0 when not in any profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create an orphan invited position with no user_id and status = 1
    $testPid = (string) DB::connection('mongodb')->table('invited_position')->insertGetId([
        'role' => 'Orphan Position',
        'status' => 1,
        'user_id' => null,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_invited_position_profile_issues');

    try {
        Volt::test('admin.database-fixing.invited-position.reviewer-profile')
            ->call('fixSingle', "nouser_{$testPid}");

        // Verify orphan was set to status = 0
        $updatedDoc = DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->first();
        expect((int) $updatedDoc->status)->toBe(0);
    } finally {
        DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->delete();
        Cache::forget('db_fixing_invited_position_profile_issues');
    }
});

test('invited position sync adds unlinked invited position to user reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $testUserId = (string) new ObjectId;

    // Create an invited position with a user_id
    $testPid = (string) DB::connection('mongodb')->table('invited_position')->insertGetId([
        'role' => 'Visiting Fellow Position',
        'status' => 1,
        'user_id' => $testUserId,
        'created_at' => now(),
    ]);

    // Create a reviewer profile for this user without this position ID
    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'invited_position' => [],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_invited_position_profile_issues');

    try {
        Volt::test('admin.database-fixing.invited-position.reviewer-profile')
            ->call('fixSingle', "missing_{$testRpId}_{$testPid}");

        // Verify profile now contains the position ID
        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->first();
        expect($updatedRp->invited_position)->toContain($testPid);
    } finally {
        DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_invited_position_profile_issues');
    }
});

test('invited position sync purges ghost position ID from reviewer profile', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $ghostPid = '65d888888888888888888888';
    $testUserId = (string) new ObjectId;

    // Create a reviewer profile with the ghost position ID
    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'invited_position' => [$ghostPid],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_invited_position_profile_issues');

    try {
        Volt::test('admin.database-fixing.invited-position.reviewer-profile')
            ->call('fixSingle', "ghost_{$testRpId}_{$ghostPid}");

        // Verify ghost ID was purged
        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->first();
        expect($updatedRp->invited_position ?? [])->not->toContain($ghostPid);
    } finally {
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_invited_position_profile_issues');
    }
});
