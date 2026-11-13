<?php

use App\Models\InvitedPosition;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('profile invited positions component renders invited position records', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'University of Oxford',
        'status' => '1',
    ]);

    $position = InvitedPosition::create([
        'user_id' => (string) $user->_id,
        'organization_id' => (string) $org->_id,
        'role' => 'Visiting Professor',
        'country' => 'United Kingdom',
        'join_date' => '2019',
        'end_date' => null,
        'continue_invited_position' => 1,
        'webiste_url' => 'https://ox.ac.uk',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'invited_position' => [(string) $position->_id],
    ]);

    $component = Volt::test('profile.invited-positions', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Invited Positions');
    $component->assertSee('Visiting Professor');
    $component->assertSee('University of Oxford');
    $component->assertSee('United Kingdom');
    $component->assertSee('2019');
    $component->assertSee('Present');
    $component->assertSee('https://ox.ac.uk');
});

test('profile invited positions component allows creating, editing and deleting invited position record', function () {
    $user = User::factory()->create();

    $component = Volt::test('profile.invited-positions', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    // Save new invited position
    $component->call('savePosition', [
        'organization_name' => 'Stanford University',
        'role' => 'Keynote Speaker',
        'country' => 'United States',
        'join_date' => '2021',
        'end_date' => 'Present / Ongoing',
        'website_url' => 'https://stanford.edu',
    ]);

    $position = InvitedPosition::where('user_id', (string) $user->_id)->first();
    expect($position)->not->toBeNull()
        ->and($position->role)->toBe('Keynote Speaker')
        ->and($position->country)->toBe('United States')
        ->and($position->join_date)->toBe('2021')
        ->and((int) $position->continue_invited_position)->toBe(1)
        ->and($position->end_date)->toBeNull()
        ->and($position->website_url)->toBe('https://stanford.edu')
        ->and((int) $position->status)->toBe(1);

    // Verify Organization model resolution
    expect($position->organization)->not->toBeNull()
        ->and($position->organization->organization_name)->toBe('Stanford University');

    // Verify ReviewerProfile atomic synchronization
    $reviewerProfile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($reviewerProfile)->not->toBeNull()
        ->and((array) $reviewerProfile->invited_position)->toContain((string) $position->_id);

    // Update invited position
    $component->call('savePosition', [
        'id' => (string) $position->_id,
        'organization_name' => 'Stanford University',
        'role' => 'Distinguished Guest Lecturer',
        'country' => 'United States',
        'join_date' => '2021',
        'end_date' => '2024',
        'website_url' => 'https://stanford.edu/lectures',
    ]);

    $updated = $position->fresh();
    expect($updated->role)->toBe('Distinguished Guest Lecturer')
        ->and($updated->end_date)->toBe('2024')
        ->and((int) $updated->continue_invited_position)->toBe(0)
        ->and($updated->website_url)->toBe('https://stanford.edu/lectures');

    // Delete invited position
    $component->call('deletePosition', (string) $position->_id);

    expect(InvitedPosition::find((string) $position->_id))->toBeNull();

    $reviewerProfileUpdated = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect((array) ($reviewerProfileUpdated->invited_position ?? []))->not->toContain((string) $position->_id);
});

test('admin invited positions partial renders and allows toggling status', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Harvard University',
        'status' => '1',
    ]);

    $position = InvitedPosition::create([
        'user_id' => (string) $user->_id,
        'organization_id' => (string) $org->_id,
        'role' => 'Visiting Fellow',
        'country' => 'United States',
        'join_date' => '2020',
        'end_date' => '2022',
        'continue_invited_position' => 0,
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'invited_position' => [(string) $position->_id],
    ]);

    $component = Volt::actingAs($admin)
        ->test('admin.users.partials.invited-positions', [
            'user' => $user,
        ]);

    $component->assertStatus(200);
    $component->assertSee('Invited Positions');
    $component->assertSee('Visiting Fellow');
    $component->assertSee('Harvard University');

    // Toggle status to Inactive
    $component->call('togglePositionStatus', (string) $position->_id);

    $positionFresh = $position->fresh();
    expect((int) $positionFresh->status)->toBe(0);

    // Toggle status back to Active
    $component->call('togglePositionStatus', (string) $position->_id);
    expect((int) $position->fresh()->status)->toBe(1);
});

test('invited position caching correctly caches and invalidates records', function () {
    $user = User::factory()->create();

    $position = InvitedPosition::create([
        'user_id' => (string) $user->_id,
        'role' => 'Guest Speaker',
        'country' => 'United States',
        'join_date' => '2023',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'invited_position' => [(string) $position->_id],
    ]);

    // Initial cached retrieval
    $cached = InvitedPosition::getCachedForUser((string) $user->_id, true);
    expect($cached)->toHaveCount(1)
        ->and($cached[0]['role'])->toBe('Guest Speaker');

    expect(Cache::has("invited_position:user:{$user->_id}:active"))->toBeTrue();

    // Updating model should invalidate cache
    $position->role = 'Senior Guest Speaker';
    $position->save();

    expect(Cache::has("invited_position:user:{$user->_id}:active"))->toBeFalse();

    // Re-fetch should repopulate cache with updated data
    $cachedUpdated = InvitedPosition::getCachedForUser((string) $user->_id, true);
    expect($cachedUpdated[0]['role'])->toBe('Senior Guest Speaker');

    // Deleting should invalidate cache
    $position->delete();
    expect(Cache::has("invited_position:user:{$user->_id}:active"))->toBeFalse();
});
