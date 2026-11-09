<?php

use App\Models\Certificate;
use App\Models\User;
use Livewire\Volt\Volt;

test('profile certificates component renders description with view more and view less toggle', function () {
    $user = User::where('slug', 'hitesh1-patel1-S9-052023-0500004')->first();

    if (! $user) {
        $user = User::factory()->create();
        Certificate::create([
            'user_id' => (string) $user->_id,
            'certificate' => 'AWS Certified Solutions Architect',
            'organizer' => 'Amazon Web Services',
            'month_year' => '05/2023',
            'description' => 'This is a long description for testing the view more toggle that exceeds the clamping threshold and provides full details regarding the certification.',
            'status' => 1,
        ]);
    }

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
});
