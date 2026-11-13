<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_role_journal_profile_issues');
});

test('role in research journals reviewer profile routes are registered', function () {
    expect(Route::has('admin.database-fixing.role-in-research-journals.reviewer-profile'))->toBeTrue();
    expect(Route::has('admin.database-fixing.role-in-research-journals.role'))->toBeTrue();
});

test('admin can access role in research journals reviewer profile sync page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.role-in-research-journals.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('RoleInResearchJournals — Reviewer Profile &amp; User ID Sync', false);
    $response->assertSee('Missing User ID');
    $response->assertSee('Ghost Profile IDs');
});

test('role in research journals sync tool is listed on the database fixing index page', function () {
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
    $response->assertSee('RoleInResearchJournals');
    $response->assertSee('reviewer_profile &amp; user_id Sync', false);
});

test('role in research journals component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.role-in-research-journals.reviewer-profile')
        ->assertSee('RoleInResearchJournals — Reviewer Profile', false)
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

test('role in research journals sync attaches user_id from reviewer_profile.RoleInResearchJournal', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $testUserId = (string) new ObjectId;

    // Create a role record with no user_id and no status
    $testRecordId = (string) DB::connection('mongodb')->table('role_in_research_journals')->insertGetId([
        'role' => 'Editor',
        'journal_title' => 'Sync Test Journal',
        'status' => null,
        'user_id' => null,
        'created_at' => now(),
    ]);

    // Link record in reviewer_profile.RoleInResearchJournal
    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'RoleInResearchJournal' => [$testRecordId],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_role_journal_profile_issues');

    try {
        Volt::test('admin.database-fixing.role-in-research-journals.reviewer-profile')
            ->call('fixSingle', "nouser_{$testRecordId}");

        $updatedDoc = DB::connection('mongodb')->table('role_in_research_journals')->where('_id', $testRecordId)->first();
        expect((string) $updatedDoc->user_id)->toBe($testUserId);
        expect((int) $updatedDoc->status)->toBe(1);
    } finally {
        DB::connection('mongodb')->table('role_in_research_journals')->where('_id', $testRecordId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_role_journal_profile_issues');
    }
});

test('role in research journals sync marks true orphan record with status = 0', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $testRecordId = (string) DB::connection('mongodb')->table('role_in_research_journals')->insertGetId([
        'role' => 'Orphan Reviewer',
        'journal_title' => 'Orphan Journal',
        'status' => 1,
        'user_id' => null,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_role_journal_profile_issues');

    try {
        Volt::test('admin.database-fixing.role-in-research-journals.reviewer-profile')
            ->call('fixSingle', "nouser_{$testRecordId}");

        $updatedDoc = DB::connection('mongodb')->table('role_in_research_journals')->where('_id', $testRecordId)->first();
        expect((int) $updatedDoc->status)->toBe(0);
    } finally {
        DB::connection('mongodb')->table('role_in_research_journals')->where('_id', $testRecordId)->delete();
        Cache::forget('db_fixing_role_journal_profile_issues');
    }
});

test('role in research journals sync adds missing record ID to reviewer_profile.RoleInResearchJournal', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $testUserId = (string) new ObjectId;

    $testRecordId = (string) DB::connection('mongodb')->table('role_in_research_journals')->insertGetId([
        'role' => 'Reviewer',
        'journal_title' => 'Unlinked Journal',
        'status' => 1,
        'user_id' => $testUserId,
        'created_at' => now(),
    ]);

    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'RoleInResearchJournal' => [],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_role_journal_profile_issues');

    try {
        Volt::test('admin.database-fixing.role-in-research-journals.reviewer-profile')
            ->call('fixSingle', "missing_{$testRpId}_{$testRecordId}");

        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->first();
        expect($updatedRp->RoleInResearchJournal)->toContain($testRecordId);
    } finally {
        DB::connection('mongodb')->table('role_in_research_journals')->where('_id', $testRecordId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_role_journal_profile_issues');
    }
});

test('role in research journals sync purges ghost ID from reviewer_profile.RoleInResearchJournal', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $ghostRecordId = '65d777777777777777777777';
    $testUserId = (string) new ObjectId;

    $testRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $testUserId,
        'RoleInResearchJournal' => [$ghostRecordId],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_role_journal_profile_issues');

    try {
        Volt::test('admin.database-fixing.role-in-research-journals.reviewer-profile')
            ->call('fixSingle', "ghost_{$testRpId}_{$ghostRecordId}");

        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->first();
        expect($updatedRp->RoleInResearchJournal ?? [])->not->toContain($ghostRecordId);
    } finally {
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $testRpId)->delete();
        Cache::forget('db_fixing_role_journal_profile_issues');
    }
});
