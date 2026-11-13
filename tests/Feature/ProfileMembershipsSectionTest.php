<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('profile memberships component renders membership records', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Institute of Electrical and Electronics Engineers',
        'status' => '1',
    ]);

    $membership = Membership::create([
        'user_id' => (string) $user->_id,
        'organization_id' => (string) $org->_id,
        'membership_type' => 'Senior Member',
        'country' => 'United States',
        'join_date' => '2018',
        'end_date' => null,
        'continue_membership' => 1,
        'webiste_url' => 'https://ieee.org',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'membership' => [(string) $membership->_id],
    ]);

    $component = Volt::test('profile.memberships', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Professional Memberships');
    $component->assertSee('Senior Member');
    $component->assertSee('Institute of Electrical and Electronics Engineers');
    $component->assertSee('United States');
    $component->assertSee('2018');
    $component->assertSee('Present');
    $component->assertSee('https://ieee.org');
});

test('profile memberships component allows creating, editing and deleting membership record', function () {
    $user = User::factory()->create();

    $component = Volt::test('profile.memberships', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    // Save new membership
    $component->call('saveMembership', [
        'organization_name' => 'Association for Computing Machinery',
        'membership_type' => 'Life Member',
        'country' => 'United States',
        'join_date' => '2015',
        'end_date' => 'Present / Ongoing',
        'website_url' => 'https://acm.org',
    ]);

    $membership = Membership::where('user_id', (string) $user->_id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->membership_type)->toBe('Life Member')
        ->and($membership->country)->toBe('United States')
        ->and($membership->join_date)->toBe('2015')
        ->and((int) $membership->continue_membership)->toBe(1)
        ->and($membership->end_date)->toBeNull()
        ->and($membership->website_url)->toBe('https://acm.org')
        ->and((int) $membership->status)->toBe(1);

    // Verify Organization model resolution
    expect($membership->organization)->not->toBeNull()
        ->and($membership->organization->organization_name)->toBe('Association for Computing Machinery');

    // Verify ReviewerProfile atomic synchronization
    $reviewerProfile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($reviewerProfile)->not->toBeNull()
        ->and((array) $reviewerProfile->membership)->toContain((string) $membership->_id);

    // Update membership
    $component->call('saveMembership', [
        'id' => (string) $membership->_id,
        'organization_name' => 'Association for Computing Machinery',
        'membership_type' => 'Distinguished Member',
        'country' => 'United States',
        'join_date' => '2015',
        'end_date' => '2024',
        'website_url' => 'https://acm.org/distinguished',
    ]);

    $updatedMembership = $membership->fresh();
    expect($updatedMembership->membership_type)->toBe('Distinguished Member')
        ->and($updatedMembership->end_date)->toBe('2024')
        ->and((int) $updatedMembership->continue_membership)->toBe(0)
        ->and($updatedMembership->website_url)->toBe('https://acm.org/distinguished');

    // Delete membership
    $component->call('deleteMembership', (string) $membership->_id);

    expect(Membership::find((string) $membership->_id))->toBeNull();

    $reviewerProfile->refresh();
    expect((array) ($reviewerProfile->membership ?? []))->not->toContain((string) $membership->_id);
});

test('admin memberships partial renders and allows toggling status', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'British Computer Society',
        'status' => '1',
    ]);

    $membership = Membership::create([
        'user_id' => (string) $user->_id,
        'organization_id' => (string) $org->_id,
        'membership_type' => 'Fellow',
        'country' => 'United Kingdom',
        'join_date' => '2020',
        'end_date' => '2023',
        'continue_membership' => 0,
        'webiste_url' => 'https://bcs.org',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'membership' => [(string) $membership->_id],
    ]);

    $adminComponent = Volt::test('admin.users.partials.memberships', [
        'user' => $user,
    ]);

    $adminComponent->assertStatus(200);
    $adminComponent->assertSee('Professional Memberships');
    $adminComponent->assertSee('Fellow');
    $adminComponent->assertSee('British Computer Society');
    $adminComponent->assertSee('Active');

    // Toggle status to Inactive (0)
    $adminComponent->call('toggleMembershipStatus', (string) $membership->_id);

    expect((int) $membership->fresh()->status)->toBe(0);

    // Toggle back to Active (1)
    $adminComponent->call('toggleMembershipStatus', (string) $membership->_id);

    expect((int) $membership->fresh()->status)->toBe(1);
});

test('redis caching correctly caches and invalidates membership records', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Royal Society of Chemistry',
        'status' => '1',
    ]);

    $membership = Membership::create([
        'user_id' => (string) $user->_id,
        'organization_id' => (string) $org->_id,
        'membership_type' => 'Chartered Chemist',
        'country' => 'United Kingdom',
        'join_date' => '2019',
        'end_date' => null,
        'continue_membership' => 1,
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'membership' => [(string) $membership->_id],
    ]);

    // Initial cached retrieval
    $cached = Membership::getCachedForUser((string) $user->_id, true);
    expect($cached)->toHaveCount(1)
        ->and($cached[0]['membership_type'])->toBe('Chartered Chemist');

    // Verify cache exists in Redis
    expect(Cache::has("membership:user:{$user->_id}:active"))->toBeTrue();

    // Updating model should invalidate cache
    $membership->membership_type = 'Fellow of the Royal Society of Chemistry';
    $membership->save();

    expect(Cache::has("membership:user:{$user->_id}:active"))->toBeFalse();

    // Re-fetch should repopulate cache with updated data
    $cachedUpdated = Membership::getCachedForUser((string) $user->_id, true);
    expect($cachedUpdated[0]['membership_type'])->toBe('Fellow of the Royal Society of Chemistry');

    // Deleting should invalidate cache
    $membership->delete();
    expect(Cache::has("membership:user:{$user->_id}:active"))->toBeFalse();
});
