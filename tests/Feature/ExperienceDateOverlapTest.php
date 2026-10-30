<?php

use App\Models\Experience;
use App\Models\User;

test('parseMonthYearToValue correctly converts valid month year formats to integers', function () {
    expect(Experience::parseMonthYearToValue('January 2025'))->toBe((2025 * 12) + 1);
    expect(Experience::parseMonthYearToValue('February 2025'))->toBe((2025 * 12) + 2);
    expect(Experience::parseMonthYearToValue('December 2026'))->toBe((2026 * 12) + 12);
    expect(Experience::parseMonthYearToValue('Jan, 2025'))->toBe((2025 * 12) + 1);
    expect(Experience::parseMonthYearToValue('Present'))->toBeNull();
    expect(Experience::parseMonthYearToValue(''))->toBeNull();
    expect(Experience::parseMonthYearToValue(null))->toBeNull();
});

test('checkDateOverlap detects overlapping dates correctly', function () {
    // Case 1: The user's exact bug from the screenshot:
    // Job 1: February 2025 - February 2026
    // Job 2: January 2025 - January 2026
    $start1 = Experience::parseMonthYearToValue('February 2025');
    $end1 = Experience::parseMonthYearToValue('February 2026');
    $start2 = Experience::parseMonthYearToValue('January 2025');
    $end2 = Experience::parseMonthYearToValue('January 2026');

    expect(Experience::checkDateOverlap($start1, $end1, $start2, $end2))->toBeTrue();
    expect(Experience::checkDateOverlap($start2, $end2, $start1, $end1))->toBeTrue();
});

test('checkDateOverlap allows same-month transition when one job ends and next job starts in the same month', function () {
    // User left role on Jan 15 2025 and joined new role on Jan 16 2025
    // Job 1: January 2020 - January 2025
    // Job 2: January 2025 - December 2025
    $start1 = Experience::parseMonthYearToValue('January 2020');
    $end1 = Experience::parseMonthYearToValue('January 2025');
    $start2 = Experience::parseMonthYearToValue('January 2025');
    $end2 = Experience::parseMonthYearToValue('December 2025');

    expect(Experience::checkDateOverlap($start1, $end1, $start2, $end2))->toBeFalse();
    expect(Experience::checkDateOverlap($start2, $end2, $start1, $end1))->toBeFalse();
});

test('checkDateOverlap rejects multi-month overlap and ongoing current role overlaps', function () {
    // Job 1: March 2024 - Ongoing (Present)
    // Job 2: June 2024 - December 2024
    $start1 = Experience::parseMonthYearToValue('March 2024');
    $end1 = PHP_INT_MAX;
    $start2 = Experience::parseMonthYearToValue('June 2024');
    $end2 = Experience::parseMonthYearToValue('December 2024');

    expect(Experience::checkDateOverlap($start1, $end1, $start2, $end2))->toBeTrue();

    // Disjoint non-overlapping roles
    // Job A: January 2022 - December 2022
    // Job B: January 2023 - December 2023
    $startA = Experience::parseMonthYearToValue('January 2022');
    $endA = Experience::parseMonthYearToValue('December 2022');
    $startB = Experience::parseMonthYearToValue('January 2023');
    $endB = Experience::parseMonthYearToValue('December 2023');

    expect(Experience::checkDateOverlap($startA, $endA, $startB, $endB))->toBeFalse();
});

test('findOverlappingActiveExperience checks only active records (status 1) and ignores inactive records (status 0)', function () {
    $user = User::factory()->create();

    // Create an inactive experience (status 0) overlapping with Jan 2025 - Dec 2025
    $inactiveExp = Experience::create([
        'user_id' => (string) $user->_id,
        'join_date' => 'January 2025',
        'end_date' => 'December 2025',
        'current_organization' => 0,
        'status' => 0,
    ]);

    $startMonth = Experience::parseMonthYearToValue('March 2025');
    $endMonth = Experience::parseMonthYearToValue('September 2025');

    // Should NOT flag inactive experience
    $found = Experience::findOverlappingActiveExperience($user, $startMonth, $endMonth);
    expect($found)->toBeNull();

    // Now create an active experience (status 1)
    $activeExp = Experience::create([
        'user_id' => (string) $user->_id,
        'join_date' => 'January 2025',
        'end_date' => 'December 2025',
        'current_organization' => 0,
        'status' => 1,
    ]);

    // Should flag the active experience
    $foundActive = Experience::findOverlappingActiveExperience($user, $startMonth, $endMonth);
    expect($foundActive)->not->toBeNull();
    expect((string) $foundActive->_id)->toBe((string) $activeExp->_id);

    // Should allow excluding the record itself when updating
    $foundExcluded = Experience::findOverlappingActiveExperience($user, $startMonth, $endMonth, (string) $activeExp->_id);
    expect($foundExcluded)->toBeNull();

    // Clean up
    $inactiveExp->delete();
    $activeExp->delete();
    $user->delete();
});
