<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::forget('db_fixing_invited_position_org_issues');
});

test('invited position organization database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.invited-position.organization'))->toBeTrue();
    expect(Route::has('admin.database-fixing.invited-position.reviewer-profile'))->toBeTrue();
    expect(Route::has('admin.database-fixing.invited-position.index'))->toBeTrue();
});

test('admin can access invited position organization database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.invited-position.organization'));
    $response->assertStatus(200);
    $response->assertSee('Invited Position — organization_id Fixer');
    $response->assertSee('Ghost Org IDs');
    $response->assertSee('Missing Org IDs');
});

test('invited position tools are listed on the database fixing index page', function () {
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
    $response->assertSee('InvitedPosition');
    $response->assertSee('organization_id (Ghost IDs &amp; Missing Organization Fixer)', false);
    $response->assertSee('reviewer_profile &amp; user_id Sync', false);
});

test('invited position organization component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.invited-position.organization')
        ->assertSee('Invited Position — organization_id Fixer')
        ->call('setTab', 'ghost_organization_id')
        ->assertSet('tab', 'ghost_organization_id')
        ->call('setTab', 'missing_organization_id')
        ->assertSet('tab', 'missing_organization_id')
        ->call('setTab', 'all')
        ->assertSet('tab', 'all');
});

test('invited position organization fixer cleans ghost organization id to NULL and assigns valid organization', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Cache::forget('db_fixing_invited_position_org_issues');

    // Create a temporary Organization
    $testOrgId = (string) DB::connection('mongodb')->table('organization')->insertGetId([
        'organization_name' => 'Invited Position Test University',
        'status' => 1,
        'created_at' => now(),
    ]);

    // Create a temporary Invited Position with a ghost organization_id
    $ghostOrgId = '65d999999999999999999999';
    $testPid = (string) DB::connection('mongodb')->table('invited_position')->insertGetId([
        'role' => 'Visiting Professor Unit Test',
        'organization_id' => $ghostOrgId,
        'status' => 1,
        'created_at' => now(),
    ]);

    try {
        Volt::test('admin.database-fixing.invited-position.organization')
            ->call('cleanGhost', "ghost_{$testPid}");

        // Verify ghost was cleaned to NULL
        $updatedDoc = DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->first();
        expect($updatedDoc->organization_id)->toBeNull();

        // Assign valid organization
        Volt::test('admin.database-fixing.invited-position.organization')
            ->set('assignInvitedPositionId', $testPid)
            ->call('assignOrganization', $testOrgId, 'Invited Position Test University');

        $reUpdatedDoc = DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->first();
        expect($reUpdatedDoc->organization_id)->toBe($testOrgId);
    } finally {
        DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->delete();
        DB::connection('mongodb')->table('organization')->where('_id', $testOrgId)->delete();
        Cache::forget('db_fixing_invited_position_org_issues');
    }
});

test('invited position organization fixer auto-applies suggested match from website url', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Cache::forget('db_fixing_invited_position_org_issues');

    // Create a temporary Organization with unique website
    $testOrgId = (string) DB::connection('mongodb')->table('organization')->insertGetId([
        'organization_name' => 'Test Tech Institute',
        'website_url' => 'https://testtechinstitute.edu.org',
        'status' => 1,
        'created_at' => now(),
    ]);

    // Create a temporary Invited Position matching the website but with no organization_id
    $testPid = (string) DB::connection('mongodb')->table('invited_position')->insertGetId([
        'role' => 'Honorary Researcher',
        'webiste_url' => 'testtechinstitute.edu.org',
        'organization_id' => null,
        'status' => 1,
        'created_at' => now(),
    ]);

    try {
        Volt::test('admin.database-fixing.invited-position.organization')
            ->call('applySuggestion', "missing_{$testPid}");

        $updatedDoc = DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->first();
        expect($updatedDoc->organization_id)->toBe($testOrgId);
    } finally {
        DB::connection('mongodb')->table('invited_position')->where('_id', $testPid)->delete();
        DB::connection('mongodb')->table('organization')->where('_id', $testOrgId)->delete();
        Cache::forget('db_fixing_invited_position_org_issues');
    }
});
