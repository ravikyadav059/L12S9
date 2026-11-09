<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_membership_org_issues');
});

test('membership organization database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.membership.organization'))->toBeTrue();
    expect(Route::has('admin.database-fixing.membership.index'))->toBeTrue();
});

test('admin can access membership organization database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.membership.organization'));
    $response->assertStatus(200);
    $response->assertSee('Membership Organization ID Fixer');
    $response->assertSee('Ghost Org IDs');
    $response->assertSee('No Org ID');
});

test('membership organization fixer tool is listed on the database fixing index page', function () {
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
    $response->assertSee('organization_id (Ghost IDs &amp; Missing Organization Fixer)', false);
});

test('membership component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.membership.organization')
        ->assertSee('Membership Organization ID Fixer')
        ->call('setTab', 'ghost_organization_id')
        ->assertSet('tab', 'ghost_organization_id')
        ->call('setTab', 'missing_organization_id')
        ->assertSet('tab', 'missing_organization_id')
        ->call('setTab', 'all')
        ->assertSet('tab', 'all');
});

test('membership organization fixer cleans ghost organization id to NULL and assigns valid organization', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Cache::forget('db_fixing_membership_org_issues');

    // Create a temporary Organization
    $testOrgId = (string) DB::connection('mongodb')->table('organization')->insertGetId([
        'organization_name' => 'IEEE Computer Society Unit Test',
        'status' => 1,
        'created_at' => now(),
    ]);

    // Create a temporary Membership with a ghost organization_id
    $ghostOrgId = '65d999999999999999999999';
    $testMid = (string) DB::connection('mongodb')->table('membership')->insertGetId([
        'membership_type' => 'Honorary Fellow',
        'organization_id' => $ghostOrgId,
        'status' => 1,
        'created_at' => now(),
    ]);

    $mongoMid = new ObjectId($testMid);
    $mongoOrgId = new ObjectId($testOrgId);

    try {
        $component = Volt::test('admin.database-fixing.membership.organization');

        // 1. Clean Ghost to NULL
        $component->call('cleanSingleToNull', "ghost_{$testMid}");

        $updatedMembership = DB::connection('mongodb')->table('membership')->where('_id', $mongoMid)->first();
        expect($updatedMembership->organization_id)->toBeNull();

        // 2. Assign valid organization
        $component->call('openAssignModal', $testMid, 'Honorary Fellow');
        expect($component->get('showAssignModal'))->toBeTrue();

        $component->call('assignOrganization', $testOrgId, 'IEEE Computer Society Unit Test');
        expect($component->get('showAssignModal'))->toBeFalse();

        $reupdatedMembership = DB::connection('mongodb')->table('membership')->where('_id', $mongoMid)->first();
        expect((string) $reupdatedMembership->organization_id)->toBe($testOrgId);

    } finally {
        // Cleanup test data
        DB::connection('mongodb')->table('membership')->where('_id', $mongoMid)->delete();
        DB::connection('mongodb')->table('organization')->where('_id', $mongoOrgId)->delete();
        Cache::forget('db_fixing_membership_org_issues');
    }
});
