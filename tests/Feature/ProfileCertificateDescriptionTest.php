<?php

use App\Models\Certificate;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Volt;

test('profile certificates component renders description with view more and view less toggle', function () {
    $user = User::factory()->create();
    $cert = Certificate::create([
        'user_id' => (string) $user->_id,
        'certificate' => 'AWS Certified Solutions Architect',
        'organizer' => 'Amazon Web Services',
        'month_year' => '05/2023',
        'description' => 'This is a long description for testing the view more toggle that exceeds the clamping threshold and provides full details regarding the certification.',
        'status' => 1,
    ]);

    $unlinkedCert = Certificate::create([
        'user_id' => (string) $user->_id,
        'certificate' => 'Unlinked Secret Certificate',
        'organizer' => 'Ghost Org',
        'month_year' => '01/2022',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'certificate' => [(string) $cert->_id],
    ]);

    $component = Volt::test('profile.certificates', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Description:');
    $component->assertSee('View More');
    $component->assertSee('View Less');
    $component->assertSee('line-clamp-2');
    $component->assertSee('descContainer');
    $component->assertDontSee('Unlinked Secret Certificate');
});
