<?php

use App\Models\DegreeType;
use App\Models\FieldOfStudy;

test('degree type recordUsage tracks submission_count and auto-promotes when submissions >= 3', function () {
    DegreeType::clearActiveCache();

    $first = DegreeType::recordUsage('Test Bachelor Auto Promote');
    expect($first->status)->toBe(0);
    expect($first->submission_count)->toBe(1);

    $second = DegreeType::recordUsage('Test Bachelor Auto Promote');
    expect($second->status)->toBe(0);
    expect($second->submission_count)->toBe(2);

    $third = DegreeType::recordUsage('Test Bachelor Auto Promote');
    expect($third->status)->toBe(1);
    expect($third->submission_count)->toBe(3);

    $third->delete();
    DegreeType::clearActiveCache();
});

test('field of study recordUsage tracks submission_count and auto-promotes when submissions >= 3', function () {
    FieldOfStudy::clearActiveCache();

    $first = FieldOfStudy::recordUsage('Test Astrobiology Auto Promote');
    expect($first->status)->toBe(0);
    expect($first->submission_count)->toBe(1);

    $second = FieldOfStudy::recordUsage('Test Astrobiology Auto Promote');
    expect($second->status)->toBe(0);
    expect($second->submission_count)->toBe(2);

    $third = FieldOfStudy::recordUsage('Test Astrobiology Auto Promote');
    expect($third->status)->toBe(1);
    expect($third->submission_count)->toBe(3);

    $third->delete();
    FieldOfStudy::clearActiveCache();
});
