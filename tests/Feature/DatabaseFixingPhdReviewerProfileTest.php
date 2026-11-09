<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_phd_issues');
});

test('reviewer profile phd sync route is registered', function () {
    expect(Route::has('admin.database-fixing.phd.reviewer-profile'))->toBeTrue();
    expect(Route::has('admin.database-fixing.phd.index'))->toBeTrue();
});

test('admin can access phd reviewer-profile database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.phd.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Reviewer Profile');
    $response->assertSee('PhD Synchronizer');
    $response->assertSee('No User ID');
    $response->assertSee('No PhD Status');
});

test('phd reviewer profile sync tool is listed on the database fixing index page', function () {
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
    $response->assertSee('PhD');
    $response->assertSee('reviewer_profile &amp; user_id Sync', false);
});

test('phd component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.phd.reviewer-profile')
        ->assertSee('Reviewer Profile')
        ->assertSee('PhD Synchronizer')
        ->call('setTab', 'no_user_id')
        ->assertSet('tab', 'no_user_id')
        ->call('setTab', 'missing_phd_status')
        ->assertSet('tab', 'missing_phd_status')
        ->call('setTab', 'missing_institution')
        ->assertSet('tab', 'missing_institution');
});

test('phd synchronizer properly handles user assignment, status 1, orphan status 0, and completed phd_status', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Cache::forget('db_fixing_phd_issues');

    // Create a temporary PhD record with no user_id, no phd_status, and empty string institution_id
    $testPhdId = (string) DB::connection('mongodb')->table('phd')->insertGetId([
        'phd_name' => 'Test Scholar',
        'phd_title' => 'Quantum Deep Learning Algorithms Unit Test',
        'research_area' => 'Computer Science',
        'status' => null,
        'phd_status' => null,
        'institution_id' => '',
        'user_id' => null,
        'created_at' => now(),
    ]);

    // Create a temporary ReviewerProfile referencing this PhD ID
    $mockUserId = '65d111111111111111111111';
    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $mockUserId,
        'phd' => [$testPhdId, 'ghost_phd_id_999'],
        'created_at' => now(),
    ]);

    $mongoPhdId = new ObjectId($testPhdId);
    $mongoRpId = new ObjectId($testRpId);

    try {
        $component = Volt::test('admin.database-fixing.phd.reviewer-profile');

        // Fix the user_id assignment issue
        $component->call('fixSingle', "nouser_{$testPhdId}");

        // Verify that PhD record has user_id set, status set to 1, phd_status set to 'Completed', and institution_id set to null
        $updatedPhd = DB::connection('mongodb')->table('phd')->where('_id', $mongoPhdId)->first();
        expect((string) $updatedPhd->user_id)->toBe($mockUserId);
        expect((int) $updatedPhd->status)->toBe(1);
        expect($updatedPhd->phd_status)->toBe('Completed');
        expect($updatedPhd->institution_id)->toBeNull();

        // Fix the ghost ID
        $component->call('fixSingle', "ghost_{$testRpId}_ghost_phd_id_999");

        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $mongoRpId)->first();
        $rpPhds = is_array($updatedRp->phd ?? null) ? array_map('strval', $updatedRp->phd) : [];
        expect($rpPhds)->toContain($testPhdId);
        expect($rpPhds)->not->toContain('ghost_phd_id_999');

    } finally {
        // Cleanup test data
        DB::connection('mongodb')->table('phd')->where('_id', $mongoPhdId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $mongoRpId)->delete();
        Cache::forget('db_fixing_phd_issues');
    }
});
