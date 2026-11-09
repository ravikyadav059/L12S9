<?php

use App\Models\Experience;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Volt;

test('profile experience allows setting currently working even if orphan experience exists with same user_id', function () {
    $user = User::factory()->create();

    // Create an orphan current experience with same user_id (NOT in reviewer_profile)
    $orphan = Experience::create([
        'user_id' => (string) $user->_id,
        'join_date' => 'January 2020',
        'end_date' => null,
        'current_organization' => 1,
        'status' => 1,
    ]);

    // Reviewer profile has no experience linked yet
    $profile = ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'experience' => [],
    ]);

    $component = Volt::test('profile.experience', ['user' => $user, 'isOwnProfile' => true]);

    // Try to save a new experience marked as current
    $component->call('saveExperience', [
        'id' => '',
        'designation' => 'Lead Engineer',
        'organization' => 'Tech Corp',
        'start_date' => 'January 2024',
        'end_date' => '',
        'is_current' => true,
        'description' => 'Working as lead engineer in high performance systems team.',
    ]);

    $component->assertDispatched('toast', message: 'Experience saved successfully!');
    $component->assertNotDispatched('alert-modal');

    // Clean up
    Experience::where('user_id', (string) $user->_id)->delete();
    $profile->delete();
    $user->delete();
});

test('profile experience blocks setting currently working if another active role in reviewer_profile is already current', function () {
    $user = User::factory()->create();

    // Create an active current experience linked in reviewer_profile
    $existing = Experience::create([
        'user_id' => (string) $user->_id,
        'join_date' => 'January 2020',
        'end_date' => null,
        'current_organization' => 1,
        'status' => 1,
    ]);

    $profile = ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'experience' => [(string) $existing->_id],
    ]);

    $component = Volt::test('profile.experience', ['user' => $user, 'isOwnProfile' => true]);

    // Try to save a second experience as current
    $component->call('saveExperience', [
        'id' => '',
        'designation' => 'Secondary Lead',
        'organization' => 'Other Corp',
        'start_date' => 'March 2024',
        'end_date' => '',
        'is_current' => true,
        'description' => 'Working as secondary lead engineer in the infrastructure platform team.',
    ]);

    $component->assertDispatched('alert-modal', title: 'Currently Working Role Conflict');

    // Clean up
    $existing->delete();
    $profile->delete();
    $user->delete();
});

test('admin experience ignores orphan experiences when checking currently working role conflict', function () {
    $user = User::factory()->create();

    // Create an orphan current experience with same user_id (NOT in reviewer_profile)
    $orphan = Experience::create([
        'user_id' => (string) $user->_id,
        'join_date' => 'January 2020',
        'end_date' => null,
        'current_organization' => 1,
        'status' => 1,
    ]);

    // Reviewer profile has no experience linked yet
    $profile = ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'experience' => [],
    ]);

    $component = Volt::test('admin.users.partials.experience', ['user' => $user]);

    // Save a new active experience marked as current
    $component->call('saveExperience', [
        'id' => '',
        'designation' => 'Staff Architect',
        'organization' => 'Apex Solutions',
        'start_date' => 'January 2024',
        'end_date' => '',
        'is_current' => true,
        'status' => 1,
        'description' => 'Architecting resilient and distributed multi-tenant microservices systems.',
    ]);

    $component->assertDispatched('toast', message: 'Experience saved successfully!');
    $component->assertNotDispatched('alert-modal');

    // Clean up
    Experience::where('user_id', (string) $user->_id)->delete();
    $profile->delete();
    $user->delete();
});
