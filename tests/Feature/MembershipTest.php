<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

test('membership model has correct collection and fillable attributes', function () {
    $membership = new Membership;

    expect($membership->getConnectionName())->toBe('mongodb')
        ->and($membership->getTable())->toBe('membership');

    $fillable = $membership->getFillable();
    expect($fillable)->toBe([
        'membership_type',
        'join_date',
        'end_date',
        'continue_membership',
        'webiste_url',
        'organization_id',
        'user_id',
        'country',
        'status',
    ]);
});

test('membership model belongs to an organization', function () {
    $membership = new Membership([
        'organization_id' => '645a499dd1330ad35e0146d5',
    ]);

    $orgRelation = $membership->organization();
    expect($orgRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($orgRelation->getRelated())->toBeInstanceOf(Organization::class)
        ->and($orgRelation->getForeignKeyName())->toBe('organization_id');

    $institutionRelation = $membership->institution();
    expect($institutionRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($institutionRelation->getRelated())->toBeInstanceOf(Organization::class);
});

test('membership model belongs to a user', function () {
    $membership = new Membership([
        'user_id' => '64523ea859b78ef7a90c64e2',
    ]);

    $userRelation = $membership->user();
    expect($userRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($userRelation->getRelated())->toBeInstanceOf(User::class)
        ->and($userRelation->getForeignKeyName())->toBe('user_id');
});

test('organization has many memberships relation', function () {
    $org = new Organization;
    $relation = $org->memberships();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Membership::class)
        ->and($relation->getForeignKeyName())->toBe('organization_id');
});

test('user has many memberships relation', function () {
    $user = new User;
    $relation = $user->memberships();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Membership::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('membership model provides website_url compatibility for webiste_url', function () {
    $membership = new Membership([
        'webiste_url' => 'https://ieee.org',
    ]);

    expect($membership->website_url)->toBe('https://ieee.org')
        ->and($membership->webiste_url)->toBe('https://ieee.org');

    $membership->website_url = 'https://acm.org';
    expect($membership->website_url)->toBe('https://acm.org')
        ->and($membership->webiste_url)->toBe('https://acm.org');
});

test('membership factory generates valid instance with states', function () {
    $membership = Membership::factory()->make([
        'membership_type' => 'IEEE Senior Member',
    ]);

    expect($membership)->toBeInstanceOf(Membership::class)
        ->and($membership->membership_type)->toBe('IEEE Senior Member')
        ->and($membership->status)->toBe(1);

    $continuing = Membership::factory()->continuing()->make();
    expect($continuing->isContinuing())->toBeTrue();

    $inactive = Membership::factory()->inactive()->make();
    expect($inactive->status)->toBe(0);
});

test('membership model detects spam via detects spam trait', function () {
    $clean = new Membership([
        'membership_type' => 'Association for Computing Machinery Member',
    ]);

    expect($clean->isSpam())->toBeFalse();

    $spam = new Membership([
        'membership_type' => 'Online Casino VIP Club Free Bitcoin Giveaway',
    ]);

    expect($spam->isSpam())->toBeTrue();
});
