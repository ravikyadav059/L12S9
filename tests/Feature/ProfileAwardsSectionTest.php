<?php

use App\Models\Award;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('profile awards component renders awards with view more toggle', function () {
    $user = User::factory()->create();

    $award = Award::create([
        'user_id' => (string) $user->_id,
        'award_name' => 'Distinguished Researcher Award',
        'award_by' => 'International Science Foundation',
        'award_year' => '2024',
        'description' => 'This is a long description for testing the view more toggle on awards section that exceeds the line-clamp threshold and provides complete details.',
        'status' => 1,
    ]);

    $unlinkedAward = Award::create([
        'user_id' => (string) $user->_id,
        'award_name' => 'Unlinked Hidden Award',
        'award_by' => 'Ghost Org',
        'award_year' => '2022',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'award' => [(string) $award->_id],
    ]);

    $component = Volt::test('profile.awards', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Achievements');
    $component->assertSee('Awarded by:');
    $component->assertSee('Description:');
    $component->assertSee('View More');
    $component->assertSee('View Less');
    $component->assertSee('line-clamp-2');
    $component->assertSee('descContainer');
    $component->assertDontSee('Unlinked Hidden Award');
});

test('profile awards component allows creating, editing and deleting award', function () {
    $user = User::factory()->create();

    $component = Volt::test('profile.awards', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    // Save new award
    $component->call('saveAward', [
        'award_name' => 'Excellence in AI Research',
        'award_by' => 'Global AI Council',
        'award_year' => '2025',
        'description' => 'Awarded for novel architectures in autonomous code agents.',
    ]);

    $award = Award::where('user_id', (string) $user->_id)->first();
    expect($award)->not->toBeNull()
        ->and($award->award_name)->toBe('Excellence in AI Research')
        ->and($award->award_by)->toBe('Global AI Council')
        ->and($award->award_year)->toBe('2025')
        ->and((int) $award->status)->toBe(1);

    // Verify ReviewerProfile synchronization
    $reviewerProfile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($reviewerProfile)->not->toBeNull()
        ->and((array) $reviewerProfile->award)->toContain((string) $award->_id);

    // Update award
    $component->call('saveAward', [
        'id' => (string) $award->_id,
        'award_name' => 'Excellence in AI & Agentic Research',
        'award_by' => 'Global AI Council',
        'award_year' => '2025',
        'description' => 'Updated award description with broader scope.',
    ]);

    expect($award->fresh()->award_name)->toBe('Excellence in AI & Agentic Research');

    // Delete award
    $component->call('deleteAward', (string) $award->_id);

    expect(Award::find((string) $award->_id))->toBeNull();

    $reviewerProfile->refresh();
    expect((array) ($reviewerProfile->award ?? []))->not->toContain((string) $award->_id);
});

test('admin awards partial renders and allows toggling status', function () {
    $user = User::factory()->create();

    $award = Award::create([
        'user_id' => (string) $user->_id,
        'award_name' => 'Dean Honor Roll',
        'award_by' => 'Engineering University',
        'award_year' => '2023',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'award' => [(string) $award->_id],
    ]);

    $component = Volt::test('admin.users.partials.awards', [
        'user' => $user,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Dean Honor Roll');
    $component->assertSee('Awarded by:');
    $component->assertSee('Active');

    // Toggle status to inactive
    $component->call('toggleAwardStatus', (string) $award->_id);
    expect((int) $award->fresh()->status)->toBe(0);

    // Toggle status back to active
    $component->call('toggleAwardStatus', (string) $award->_id);
    expect((int) $award->fresh()->status)->toBe(1);
});

test('awards utilize redis cache and invalidate upon changes', function () {
    $user = User::factory()->create();
    $userId = (string) $user->_id;

    Award::forgetUserCache($userId);
    expect(Cache::has("awards:user:{$userId}:active"))->toBeFalse();

    $award = Award::create([
        'user_id' => $userId,
        'award_name' => 'National Technology Award',
        'award_by' => 'Science Academy',
        'award_year' => '2024',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => $userId,
        'award' => [(string) $award->_id],
    ]);

    // Initial fetch should populate Redis cache
    $cached = Award::getCachedForUser($userId, true);
    expect($cached)->toHaveCount(1)
        ->and(Cache::has("awards:user:{$userId}:active"))->toBeTrue();

    // Updating award should automatically invalidate Redis cache via model events
    $award->update(['award_name' => 'International Technology Award']);
    expect(Cache::has("awards:user:{$userId}:active"))->toBeFalse();

    // Re-cache and verify deletion invalidates
    Award::getCachedForUser($userId, true);
    expect(Cache::has("awards:user:{$userId}:active"))->toBeTrue();

    $award->delete();
    expect(Cache::has("awards:user:{$userId}:active"))->toBeFalse();
});
