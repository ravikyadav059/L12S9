<?php

use App\Models\Category;
use App\Models\Patent;
use App\Models\User;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

test('patent model has correct collection and fillable attributes', function () {
    $patent = new Patent;

    expect($patent->getConnectionName())->toBe('mongodb')
        ->and($patent->getTable())->toBe('patent');

    $fillable = $patent->getFillable();
    expect($fillable)->toBe([
        'title',
        'inventor',
        'assignee',
        'application_no',
        'patent_no',
        'country',
        'category_id',
        'filing_date',
        'publication_date',
        'status',
        'description',
        'user_id',
    ]);
});

test('patent model belongs to a category', function () {
    $patent = new Patent([
        'category_id' => '63d94435ab1bc8a2ec837817',
    ]);

    $relation = $patent->category();
    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Category::class)
        ->and($relation->getForeignKeyName())->toBe('category_id');
});

test('patent model belongs to a user', function () {
    $patent = new Patent([
        'user_id' => '6931651ad2a75480a60a0174',
    ]);

    $relation = $patent->user();
    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('user has many patents relation', function () {
    $user = new User;
    $relation = $user->patents();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Patent::class)
        ->and($relation->getForeignKeyName())->toBe('user_id');
});

test('patent factory generates valid patent instance with states', function () {
    $patent = Patent::factory()->make([
        'title' => 'System and Method for Secure Distributed Ledger',
    ]);

    expect($patent)->toBeInstanceOf(Patent::class)
        ->and($patent->title)->toBe('System and Method for Secure Distributed Ledger')
        ->and($patent->status)->toBe(1);

    $inactive = Patent::factory()->inactive()->make();
    expect($inactive->status)->toBe(0);
});

test('patent model detects spam via detects spam trait', function () {
    $clean = new Patent([
        'title' => 'Solar Powered Agricultural Harvesting Mechanism',
        'description' => 'A clean and sustainable farming equipment.',
    ]);

    expect($clean->isSpam())->toBeFalse();

    $spam = new Patent([
        'title' => 'Free Bitcoin Crypto Giveaway Casino Bot',
        'description' => 'Earn money fast with online slots!',
    ]);

    expect($spam->isSpam())->toBeTrue();
});
