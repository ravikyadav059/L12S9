<?php

use App\Models\Designation;
use Illuminate\Support\Facades\Cache;

test('designation caches active list and invalidates on save', function () {
    Designation::clearActiveCache();

    $desig1 = Designation::create([
        'designation' => 'Principal Software Architect',
        'status' => 1,
        'submission_count' => 10,
    ]);

    $cached = Designation::getAllActiveCached();
    expect(Cache::has(Designation::CACHE_KEY_ACTIVE))->toBeTrue();
    $names = array_column($cached, 'name');
    expect(in_array('Principal Software Architect', $names))->toBeTrue();

    // Verify searchActive filters from memory
    $search = Designation::searchActive('Principal');
    expect(count($search['data']))->toBeGreaterThanOrEqual(1);
    expect($search['data'][0]['name'])->toBe('Principal Software Architect');

    // Clean up
    $desig1->delete();
    expect(Cache::has(Designation::CACHE_KEY_ACTIVE))->toBeFalse();
});

test('designation searchActive falls back to database when not found in memory cache', function () {
    Designation::clearActiveCache();

    // Pre-populate cache without the test item
    Cache::put(Designation::CACHE_KEY_ACTIVE, [
        ['id' => 'dummy_1', 'name' => 'Existing Cached Role'],
    ], now()->addDays(2));

    // Create a new record directly in DB without clearing cache
    $desig = Designation::withoutEvents(function () {
        return Designation::create([
            'designation' => 'Quantum Computing Specialist',
            'status' => 1,
            'submission_count' => 5,
        ]);
    });

    // Search for the item that is NOT in the cached list
    $result = Designation::searchActive('Quantum Computing');

    // Fallback should find it in the database
    expect(count($result['data']))->toBeGreaterThanOrEqual(1);
    expect($result['data'][0]['name'])->toBe('Quantum Computing Specialist');

    // Clean up
    $desig->delete();
    Designation::clearActiveCache();
});

test('designation recordUsage increments submission_count and auto-promotes when submissions >= 3', function () {
    Designation::clearActiveCache();

    // First submission: created with status 0 and submission_count 1
    $first = Designation::recordUsage('Test Role Auto Promote');
    expect($first->status)->toBe(0);
    expect($first->submission_count)->toBe(1);

    // Second submission: increments submission_count to 2, status remains 0
    $second = Designation::recordUsage('Test Role Auto Promote');
    expect($second->submission_count)->toBe(2);
    expect($second->status)->toBe(0);

    // Third submission: increments submission_count to 3, auto-promotes status to 1
    $third = Designation::recordUsage('Test Role Auto Promote');
    expect($third->submission_count)->toBe(3);
    expect($third->status)->toBe(1);

    // Clean up
    $third->delete();
    Designation::clearActiveCache();
});
