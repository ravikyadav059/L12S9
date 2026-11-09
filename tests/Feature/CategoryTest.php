<?php

use App\Models\Category;
use App\Models\Patent;
use MongoDB\Laravel\Relations\HasMany;

test('category model has correct collection and fillable attributes', function () {
    $category = new Category;

    expect($category->getConnectionName())->toBe('mongodb')
        ->and($category->getTable())->toBe('category');

    $fillable = $category->getFillable();
    expect($fillable)->toBe([
        'category_title',
        'category_status',
        'serial_number',
        'category_slug',
        'category_image',
    ]);
});

test('category casts status and serial number to integers', function () {
    $category = new Category([
        'category_status' => '1',
        'serial_number' => '42',
    ]);

    expect($category->category_status)->toBe(1)
        ->and($category->serial_number)->toBe(42);
});

test('category model automatically creates serial number and slug when creating', function () {
    $category = new Category([
        'category_title' => 'Quantum Computing and Cryptography',
        'category_status' => 1,
    ]);

    // Manually trigger booted creating callback logic
    $category->save();

    expect($category->serial_number)->toBeGreaterThan(0)
        ->and($category->category_slug)->toContain('quantum-computing-and-cryptography');

    $category->delete();
});

test('category model has many patents relation', function () {
    $category = new Category;
    $relation = $category->patents();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Patent::class)
        ->and($relation->getForeignKeyName())->toBe('category_id');
});

test('category factory generates valid category instance', function () {
    $category = Category::factory()->make([
        'category_title' => 'Biotechnology',
    ]);

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->category_title)->toBe('Biotechnology')
        ->and($category->category_status)->toBe(1);

    $inactive = Category::factory()->inactive()->make();
    expect($inactive->category_status)->toBe(0);
});
