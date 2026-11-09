<?php

use App\Models\Organization;
use App\Models\Phd;
use App\Models\User;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

test('phd model has correct collection and fillable attributes', function () {
    $phd = new Phd;

    expect($phd->getConnectionName())->toBe('mongodb')
        ->and($phd->getTable())->toBe('phd');

    $fillable = $phd->getFillable();
    expect($fillable)->toContain('phd_name')
        ->and($fillable)->toContain('phd_title')
        ->and($fillable)->toContain('thesis_type')
        ->and($fillable)->toContain('research_area')
        ->and($fillable)->toContain('institution_id')
        ->and($fillable)->toContain('status')
        ->and($fillable)->toContain('phd_status')
        ->and($fillable)->toContain('user_id');
});

test('phd model belongs to an institution organization', function () {
    $phd = new Phd([
        'institution_id' => '645a4f4a9b981f02000af954',
    ]);

    $institutionRelation = $phd->institution();
    expect($institutionRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($institutionRelation->getRelated())->toBeInstanceOf(Organization::class)
        ->and($institutionRelation->getForeignKeyName())->toBe('institution_id');

    $organizationRelation = $phd->organization();
    expect($organizationRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($organizationRelation->getRelated())->toBeInstanceOf(Organization::class);
});

test('phd model belongs to a user', function () {
    $phd = new Phd([
        'user_id' => '64523ea859b78ef7a90c64e2',
    ]);

    $userRelation = $phd->user();
    expect($userRelation)->toBeInstanceOf(BelongsTo::class)
        ->and($userRelation->getRelated())->toBeInstanceOf(User::class)
        ->and($userRelation->getForeignKeyName())->toBe('user_id');
});

test('organization has many phds relation', function () {
    $org = new Organization;
    $relation = $org->phds();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Phd::class)
        ->and($relation->getForeignKeyName())->toBe('institution_id');
});

test('user has many phds relation', function () {
    $user = new User;
    $relation = $user->phds();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Phd::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('phd factory generates model instance with proper states', function () {
    $phd = Phd::factory()->make([
        'phd_name' => 'Dr. Robert Smith',
        'phd_title' => 'Quantum Cryptography in Distributed Networks',
    ]);

    expect($phd)->toBeInstanceOf(Phd::class)
        ->and($phd->phd_name)->toBe('Dr. Robert Smith')
        ->and($phd->status)->toBe(1);

    $completed = Phd::factory()->completed()->make();
    expect($completed->phd_status)->toBe('Completed');

    $ongoing = Phd::factory()->ongoing()->make();
    expect($ongoing->phd_status)->toBe('Ongoing');

    $inactive = Phd::factory()->inactive()->make();
    expect($inactive->status)->toBe(0);
});

test('phd model detects spam via detects spam trait', function () {
    $cleanPhd = new Phd([
        'phd_name' => 'Dr. Sarah Connor',
        'phd_title' => 'Machine Learning in Robotic Systems',
        'research_area' => 'Robotics and Computer Vision',
    ]);

    expect($cleanPhd->isSpam())->toBeFalse();

    $spamPhd = new Phd([
        'phd_name' => 'Casino Bot',
        'phd_title' => 'Win Free Bitcoin and Online Slots Fast',
        'research_area' => 'Gambling',
    ]);

    expect($spamPhd->isSpam())->toBeTrue();
});
