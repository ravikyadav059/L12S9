<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_publication_profile_issues');
});

test('publication reviewer profile routes are registered', function () {
    expect(Route::has('admin.database-fixing.publication.reviewer-profile'))->toBeTrue();
});

test('admin can access publication reviewer profile sync page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.publication.reviewer-profile'));
    $response->assertStatus(200);
    $response->assertSee('Publication — Reviewer Profile & Co-Author Sync', false);
    $response->assertSee('Missing in Profile');
    $response->assertSee('Ghost Profile IDs');
});

test('publication reviewer profile sync tool is listed on database fixing index page', function () {
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
    $response->assertSee('Publication');
    $response->assertSee('reviewer_profile &amp; Co-Author Sync', false);
});

test('publication sync adds publication ID to profile when linked via user_id or registered_co_author', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $ownerUid = (string) new ObjectId;
    $coAuthorUid = (string) new ObjectId;

    $pubId = (string) DB::connection('mongodb')->table('publications')->insertGetId([
        'title' => 'Co-Authored AI Paper',
        'user_id' => $ownerUid,
        'registered_co_author' => [$coAuthorUid],
        'status' => 1,
        'created_at' => now(),
    ]);

    $ownerRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $ownerUid,
        'publication' => [],
        'status' => 1,
        'created_at' => now(),
    ]);

    $coAuthorRpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $coAuthorUid,
        'publication' => [],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_publication_profile_issues');

    try {
        Volt::test('admin.database-fixing.publication.reviewer-profile')
            ->call('fixSingle', "missing_{$ownerRpId}_{$pubId}")
            ->call('fixSingle', "missing_{$coAuthorRpId}_{$pubId}");

        $ownerRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $ownerRpId)->first();
        $coAuthorRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $coAuthorRpId)->first();

        expect($ownerRp->publication)->toContain($pubId)
            ->and($coAuthorRp->publication)->toContain($pubId);
    } finally {
        DB::connection('mongodb')->table('publications')->where('_id', $pubId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $ownerRpId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $coAuthorRpId)->delete();
        Cache::forget('db_fixing_publication_profile_issues');
    }
});

test('publication sync purges ghost IDs without modifying existing publications documents', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $userId = (string) new ObjectId;
    $ghostPubId = '65d666666666666666666666';

    $pubId = (string) DB::connection('mongodb')->table('publications')->insertGetId([
        'title' => 'Existing Publication',
        'publication_type' => 'auth_person',
        'user_id' => null,
        'status' => 1,
        'created_at' => now(),
    ]);

    $rpId = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $userId,
        'publication' => [$pubId, $ghostPubId],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_publication_profile_issues');

    try {
        Volt::test('admin.database-fixing.publication.reviewer-profile')
            ->call('fixSingle', "ghost_{$rpId}_{$ghostPubId}");

        $updatedPub = DB::connection('mongodb')->table('publications')->where('_id', $pubId)->first();
        $updatedRp = DB::connection('mongodb')->table('reviewer_profile')->where('_id', $rpId)->first();

        expect($updatedPub->user_id)->toBeNull()
            ->and($updatedPub->publication_type)->toBe('auth_person')
            ->and($updatedRp->publication)->toContain($pubId)
            ->and($updatedRp->publication)->not->toContain($ghostPubId);
    } finally {
        DB::connection('mongodb')->table('publications')->where('_id', $pubId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->where('_id', $rpId)->delete();
        Cache::forget('db_fixing_publication_profile_issues');
    }
});

test('guest publication keeps user_id null and syncs all registered_co_author users including creating missing reviewer profiles', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $coAuthor1 = User::factory()->create();
    $coAuthor2 = User::factory()->create();
    $uid1 = (string) $coAuthor1->_id;
    $uid2 = (string) $coAuthor2->_id;

    // Only coAuthor1 has an existing reviewer_profile; coAuthor2 does not yet
    $rp1Id = (string) DB::connection('mongodb')->table('reviewer_profile')->insertGetId([
        'user_id' => $uid1,
        'publication' => [],
        'status' => 1,
        'created_at' => now(),
    ]);

    $pubId = (string) DB::connection('mongodb')->table('publications')->insertGetId([
        'title' => 'Guest Deposit Co-Authored Paper',
        'publication_type' => 'guest',
        'user_id' => null,
        'registered_co_author' => [$uid1, $uid2],
        'status' => 1,
        'created_at' => now(),
    ]);

    Cache::forget('db_fixing_publication_profile_issues');

    try {
        Volt::test('admin.database-fixing.publication.reviewer-profile')
            ->call('fixSingle', "missing_{$rp1Id}_{$pubId}")
            ->call('fixSingle', "missing_user_{$uid2}_{$pubId}");

        $updatedPub = DB::connection('mongodb')->table('publications')->where('_id', $pubId)->first();
        $rp1 = DB::connection('mongodb')->table('reviewer_profile')->where('user_id', $uid1)->first();
        $rp2 = DB::connection('mongodb')->table('reviewer_profile')->where('user_id', $uid2)->first();

        expect($updatedPub->user_id)->toBeNull()
            ->and($rp1->publication)->toContain($pubId)
            ->and($rp2)->not->toBeNull()
            ->and($rp2->publication)->toContain($pubId);
    } finally {
        DB::connection('mongodb')->table('publications')->where('_id', $pubId)->delete();
        DB::connection('mongodb')->table('reviewer_profile')->whereIn('user_id', [$uid1, $uid2])->delete();
        User::whereIn('_id', [$uid1, $uid2])->delete();
        Cache::forget('db_fixing_publication_profile_issues');
    }
});
