<?php

use App\Models\Organization;
use App\Models\Phd;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('profile thesis component renders thesis guided records', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Stanford University Research Center',
        'status' => '1',
    ]);

    $phd = Phd::create([
        'user_id' => (string) $user->_id,
        'phd_name' => 'Alice Walker',
        'phd_title' => 'Quantum Algorithms for Graph Optimization',
        'institution_id' => (string) $org->_id,
        'thesis_type' => 'Ph.D. Thesis',
        'research_area' => 'Quantum Computing',
        'phd_status' => 'Ongoing',
        'status' => 1,
    ]);

    $component = Volt::test('profile.thesis', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Thesis Guided');
    $component->assertSee('Quantum Algorithms for Graph Optimization');
    $component->assertSee('Name:');
    $component->assertSee('Alice Walker');
    $component->assertSee('Type:');
    $component->assertSee('Ph.D. Thesis');
    $component->assertSee('Status:');
    $component->assertSee('Ongoing');
    $component->assertSee('Institution Name:');
    $component->assertSee('Stanford University Research Center');
    $component->assertSee('Area:');
    $component->assertSee('Quantum Computing');
    $component->assertSee('Delete');
});

test('profile thesis component allows creating, editing and deleting thesis record', function () {
    $user = User::factory()->create();

    $component = Volt::test('profile.thesis', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    // Save new thesis
    $component->call('saveThesis', [
        'phd_name' => 'Robert Johnson',
        'phd_title' => 'Autonomous Robotics in Deep Ocean Exploration',
        'institution' => 'Massachusetts Institute of Technology',
        'thesis_type' => 'Ph.D. Thesis',
        'research_area' => 'Robotics & Control Systems',
        'phd_status' => 'Ongoing',
    ]);

    $phd = Phd::where('user_id', (string) $user->_id)->first();
    expect($phd)->not->toBeNull()
        ->and($phd->phd_name)->toBe('Robert Johnson')
        ->and($phd->phd_title)->toBe('Autonomous Robotics in Deep Ocean Exploration')
        ->and($phd->thesis_type)->toBe('Ph.D. Thesis')
        ->and($phd->research_area)->toBe('Robotics & Control Systems')
        ->and($phd->phd_status)->toBe('Ongoing')
        ->and((int) $phd->status)->toBe(1);

    // Verify Organization model resolution
    expect($phd->institution)->not->toBeNull()
        ->and($phd->institution->organization_name)->toBe('Massachusetts Institute of Technology');

    // Verify ReviewerProfile synchronization
    $reviewerProfile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($reviewerProfile)->not->toBeNull()
        ->and((array) $reviewerProfile->phd)->toContain((string) $phd->_id);

    // Update thesis
    $component->call('saveThesis', [
        'id' => (string) $phd->_id,
        'phd_name' => 'Dr. Robert Johnson',
        'phd_title' => 'Autonomous Robotics in Deep Ocean Exploration - Completed',
        'institution' => 'Massachusetts Institute of Technology',
        'thesis_type' => 'Ph.D. Thesis',
        'research_area' => 'Underwater Robotics',
        'phd_status' => 'Completed',
    ]);

    $updatedPhd = $phd->fresh();
    expect($updatedPhd->phd_name)->toBe('Dr. Robert Johnson')
        ->and($updatedPhd->phd_title)->toBe('Autonomous Robotics in Deep Ocean Exploration - Completed')
        ->and($updatedPhd->research_area)->toBe('Underwater Robotics')
        ->and($updatedPhd->phd_status)->toBe('Completed');

    // Delete thesis
    $component->call('deleteThesis', (string) $phd->_id);

    expect(Phd::find((string) $phd->_id))->toBeNull();

    $reviewerProfile->refresh();
    expect((array) ($reviewerProfile->phd ?? []))->not->toContain((string) $phd->_id);
});

test('admin thesis partial renders and allows toggling status', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Oxford University',
        'status' => '1',
    ]);

    $phd = Phd::create([
        'user_id' => (string) $user->_id,
        'phd_name' => 'Sophia Martinez',
        'phd_title' => 'CRISPR Gene Editing for Metabolic Disorders',
        'institution_id' => (string) $org->_id,
        'thesis_type' => 'Ph.D. Thesis',
        'research_area' => 'Genomics',
        'phd_status' => 'Completed',
        'status' => 1,
    ]);

    $adminComponent = Volt::test('admin.users.partials.thesis', [
        'user' => $user,
    ]);

    $adminComponent->assertStatus(200);
    $adminComponent->assertSee('CRISPR Gene Editing for Metabolic Disorders');
    $adminComponent->assertSee('Sophia Martinez');
    $adminComponent->assertSee('Oxford University');
    $adminComponent->assertSee('Active');

    // Toggle status to Inactive (0)
    $adminComponent->call('toggleThesisStatus', (string) $phd->_id);

    expect((int) $phd->fresh()->status)->toBe(0);

    // Toggle back to Active (1)
    $adminComponent->call('toggleThesisStatus', (string) $phd->_id);

    expect((int) $phd->fresh()->status)->toBe(1);
});

test('redis caching correctly caches and invalidates thesis guided records', function () {
    $user = User::factory()->create();

    $org = Organization::create([
        'organization_name' => 'Cambridge University',
        'status' => '1',
    ]);

    $phd = Phd::create([
        'user_id' => (string) $user->_id,
        'phd_name' => 'David Lee',
        'phd_title' => 'Photovoltaic Efficiency via Perovskite Structures',
        'institution_id' => (string) $org->_id,
        'thesis_type' => 'M.Tech Thesis',
        'research_area' => 'Renewable Energy',
        'phd_status' => 'Completed',
        'status' => 1,
    ]);

    // Initial cached retrieval
    $cached = Phd::getCachedForUser((string) $user->_id, true);
    expect($cached)->toHaveCount(1)
        ->and($cached[0]['phd_title'])->toBe('Photovoltaic Efficiency via Perovskite Structures');

    // Verify cache exists in Redis
    expect(Cache::has("phd:user:{$user->_id}:active"))->toBeTrue();

    // Updating model should invalidate cache
    $phd->phd_title = 'Photovoltaic Efficiency via Perovskite Structures - 2nd Edition';
    $phd->save();

    expect(Cache::has("phd:user:{$user->_id}:active"))->toBeFalse();

    // Re-fetch should repopulate cache with updated title
    $cachedUpdated = Phd::getCachedForUser((string) $user->_id, true);
    expect($cachedUpdated[0]['phd_title'])->toBe('Photovoltaic Efficiency via Perovskite Structures - 2nd Edition');

    // Deleting should invalidate cache
    $phd->delete();
    expect(Cache::has("phd:user:{$user->_id}:active"))->toBeFalse();
});
