<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

test('user model generates svg data uri avatar when no photo is set', function () {
    $user = new User([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
    ]);

    expect($user->initials())->toBe('JD')
        ->and($user->avatar)->toContain('data:image/svg+xml;utf8,')
        ->and($user->avatar)->toContain('JD');
});

test('user model correctly handles single name and unicode names for initials', function () {
    $singleNameUser = new User(['fullname' => 'Aristotle']);
    expect($singleNameUser->initials())->toBe('AR');

    $multiWordUser = new User(['fullname' => 'Dr. Jane Mary Watson']);
    expect($multiWordUser->initials())->toBe('DW');

    $emptyNameUser = new User(['email' => 'alexander@example.com']);
    expect($emptyNameUser->initials())->toBe('AL');
});

test('avatar blade component renders successfully for user model', function () {
    $user = new User([
        'first_name' => 'Albert',
        'last_name' => 'Einstein',
        'email' => 'albert@relativity.org',
    ]);

    $view = Blade::render('<x-avatar :user="$user" size="size-10" />', ['user' => $user]);

    expect($view)->toContain('AE')
        ->and($view)->toContain('size-10');
});

test('avatar blade component renders with photo when provided', function () {
    $user = new User([
        'first_name' => 'Marie',
        'last_name' => 'Curie',
        'photo' => 'https://example.com/photos/marie.jpg',
    ]);

    $view = Blade::render('<x-avatar :user="$user" />', ['user' => $user]);

    expect($view)->toContain('https://example.com/photos/marie.jpg')
        ->and($view)->toContain('MC');
});
