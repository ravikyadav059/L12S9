<?php

use App\Models\DegreeType;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Grade;
use App\Models\Organization;

test('education model formats passout_year strictly to 4-digit year and casts current_institute to integer', function () {
    $degreeType = DegreeType::create([
        'degree_type' => 'Bachelor of Technology',
        'status' => '1',
    ]);

    $grade = Grade::create([
        'grade' => 'Distinction',
        'status' => '1',
    ]);

    $org = Organization::create([
        'organization_name' => 'Gujarat Technological University',
        'status' => 1,
    ]);

    $education = Education::create([
        'degree_type' => (string) $degreeType->_id,
        'field_of_study' => 'Computer Science & Engineering',
        'passout_year' => '2023-06-15',
        'pursuing' => 0,
        'description' => 'Studied core computer science curriculum.',
        'grade' => (string) $grade->_id,
        'institution_id' => (string) $org->_id,
        'current_institute' => '1',
        'status' => '1',
    ]);

    expect($education->passout_year)->toBe('2023');
    expect($education->current_institute)->toBe(1);
    expect($education->current_institute)->toBeInt();
    expect($education->isPursuing())->toBeTrue();

    // Verify relationships
    expect($education->institution)->not->toBeNull();
    expect($education->institution->_id)->toBe($org->_id);

    expect($education->degreeType)->not->toBeNull();
    expect($education->degreeType->degree_type)->toBe('Bachelor of Technology');

    expect($education->gradeRelation)->not->toBeNull();
    expect($education->gradeRelation->grade)->toBe('Distinction');

    // Clean up
    $education->delete();
    $degreeType->delete();
    $grade->delete();
    $org->delete();
});

test('education formatYear normalizes spaced, prefixed, and date string years correctly', function () {
    expect(Education::formatYear(2012))->toBe('2012');
    expect(Education::formatYear('19 96'))->toBe('1996');
    expect(Education::formatYear('05/2018'))->toBe('2018');
    expect(Education::formatYear('May 2021'))->toBe('2021');
    expect(Education::formatYear('19996'))->toBe('1999');
    expect(Education::formatYear('-----'))->toBeNull();
    expect(Education::formatYear('null'))->toBeNull();
    expect(Education::formatYear(''))->toBeNull();
    expect(Education::formatYear('Pursuing', true))->toBe('Pursuing');
});

test('education isPursuing detects all pursuing variants', function () {
    expect((new Education(['current_institute' => 1]))->isPursuing())->toBeTrue();
    expect((new Education(['current_institute' => '1']))->isPursuing())->toBeTrue();
    expect((new Education(['pursuing' => 1]))->isPursuing())->toBeTrue();
    expect((new Education(['pursuing' => '1']))->isPursuing())->toBeTrue();
    expect((new Education(['passout_year' => 'Pursuing']))->isPursuing())->toBeTrue();
    expect((new Education(['current_institute' => 0, 'pursuing' => 0]))->isPursuing())->toBeFalse();
});

test('experience isCurrent detects all current role variants', function () {
    $exp1 = new Experience(['current_organization' => 1]);
    $exp2 = new Experience(['current_organization' => '1']);
    $exp3 = new Experience(['end_date' => 'Present']);
    $exp4 = new Experience(['current_organization' => 0, 'end_date' => 'May 2023']);

    expect($exp1->isCurrent())->toBeTrue();
    expect($exp2->isCurrent())->toBeTrue();
    expect($exp3->isCurrent())->toBeTrue();
    expect($exp4->isCurrent())->toBeFalse();
});
