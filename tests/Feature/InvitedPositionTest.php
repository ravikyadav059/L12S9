<?php

use App\Models\InvitedPosition;
use App\Models\Organization;
use App\Models\User;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

test('invited position model has correct collection and fillable attributes', function () {
    $position = new InvitedPosition;

    expect($position->getConnectionName())->toBe('mongodb')
        ->and($position->getTable())->toBe('invited_position');

    $fillable = $position->getFillable();
    expect($fillable)->toContain('role')
        ->and($fillable)->toContain('join_date')
        ->and($fillable)->toContain('end_date')
        ->and($fillable)->toContain('continue_invited_position')
        ->and($fillable)->toContain('webiste_url')
        ->and($fillable)->toContain('organization_id')
        ->and($fillable)->toContain('country')
        ->and($fillable)->toContain('user_id')
        ->and($fillable)->toContain('status');
});

test('invited position model belongs to an organization', function () {
    $position = new InvitedPosition([
        'organization_id' => '645f3e0e33091b9baf0b1ec2',
    ]);

    $orgRelation = $position->organization();
    expect($orgRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($orgRelation->getRelated())->toBeInstanceOf(Organization::class)
        ->and($orgRelation->getForeignKeyName())->toBe('organization_id');
});

test('invited position model belongs to a user', function () {
    $position = new InvitedPosition([
        'user_id' => '64523ea859b78ef7a90c64e2',
    ]);

    $userRelation = $position->user();
    expect($userRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($userRelation->getRelated())->toBeInstanceOf(User::class)
        ->and($userRelation->getForeignKeyName())->toBe('user_id');
});

test('organization has many invited positions relation', function () {
    $org = new Organization;
    $relation = $org->invitedPositions();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(InvitedPosition::class)
        ->and($relation->getForeignKeyName())->toBe('organization_id');
});

test('user has many invited positions relation', function () {
    $user = new User;
    $relation = $user->invitedPositions();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(InvitedPosition::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('invited position provides website_url compatibility for webiste_url', function () {
    $position = new InvitedPosition([
        'webiste_url' => 'https://oxford.ac.uk',
    ]);

    expect($position->website_url)->toBe('https://oxford.ac.uk')
        ->and($position->webiste_url)->toBe('https://oxford.ac.uk');

    $position->website_url = 'https://cambridge.ac.uk';
    expect($position->website_url)->toBe('https://cambridge.ac.uk')
        ->and($position->webiste_url)->toBe('https://cambridge.ac.uk');
});

test('invited position factory generates valid instance with states', function () {
    $position = InvitedPosition::factory()->make([
        'role' => 'Visiting Professor of Data Science',
    ]);

    expect($position)->toBeInstanceOf(InvitedPosition::class)
        ->and($position->role)->toBe('Visiting Professor of Data Science')
        ->and($position->status)->toBe(1);

    $continuing = InvitedPosition::factory()->continuing()->make();
    expect($continuing->isContinuing())->toBeTrue();

    $inactive = InvitedPosition::factory()->inactive()->make();
    expect($inactive->status)->toBe(0);
});

test('invited position model detects spam via detects spam trait', function () {
    $clean = new InvitedPosition([
        'role' => 'Keynote Speaker at AI Summit 2025',
    ]);

    expect($clean->isSpam())->toBeFalse();

    $spam = new InvitedPosition([
        'role' => 'Online Casino Bonus Fast Loan Approval',
    ]);

    expect($spam->isSpam())->toBeTrue();
});

