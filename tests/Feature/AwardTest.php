<?php

use App\Models\Award;
use App\Models\User;
use MongoDB\Laravel\Relations\HasMany;

test('award model has correct collection and fillable attributes', function () {
    $award = new Award;

    expect($award->getConnectionName())->toBe('mongodb')
        ->and($award->getTable())->toBe('award');

    $fillable = $award->getFillable();
    expect($fillable)->toContain('award_year')
        ->and($fillable)->toContain('award_name')
        ->and($fillable)->toContain('award_by')
        ->and($fillable)->toContain('status')
        ->and($fillable)->toContain('user_id')
        ->and($fillable)->toContain('description')
        ->and($fillable)->toContain('post_id');
});

test('award model uses detects spam trait on clean text', function () {
    $award = new Award([
        'award_year' => '2024',
        'award_name' => 'Best Researcher Award',
        'award_by' => 'International Science Foundation',
        'status' => 1,
        'description' => 'Honored for outstanding contributions to renewable energy research.',
    ]);

    expect($award->isSpam())->toBeFalse()
        ->and($award->containsSpam())->toBeFalse()
        ->and($award->getSpamScore())->toBeLessThan(50);
});

test('award model detects spam when text contains blacklisted keywords', function () {
    $award = new Award([
        'award_year' => '2024',
        'award_name' => 'Online Casino Bonus Winner',
        'award_by' => 'Free Bitcoin Giveaway',
        'status' => 1,
        'description' => 'Claim free spins and cheap pills today!',
    ]);

    $analysis = $award->detectSpam();

    expect($analysis['is_spam'])->toBeTrue()
        ->and($analysis['score'])->toBeGreaterThanOrEqual(50)
        ->and($analysis['matches'])->toContain('casino')
        ->and($award->isSpam())->toBeTrue();
});

test('detects spam trait detects standalone spam text string', function () {
    $award = new Award;

    expect($award->isSpam('Normal congratulations on your achievement.'))->toBeFalse()
        ->and($award->isSpam('Earn money fast with poker online and free spins now!'))->toBeTrue();
});

test('detects spam trait detects excessive repeated punctuation and letters', function () {
    $award = new Award;

    $resultPunctuation = $award->detectSpam('Winner Award $$$$$$$$!!!!!!!');
    expect($resultPunctuation['score'])->toBeGreaterThan(0)
        ->and($resultPunctuation['reasons'])->not->toBeEmpty();

    $resultLetters = $award->detectSpam('Congratsssssssss on winning');
    expect($resultLetters['score'])->toBeGreaterThan(0);
});

test('award factory creates valid award instances', function () {
    $award = Award::factory()->make([
        'award_name' => 'Distinguished Scientist Award',
    ]);

    expect($award)->toBeInstanceOf(Award::class)
        ->and($award->award_name)->toBe('Distinguished Scientist Award')
        ->and($award->status)->toBe(1);
});

test('user has awards relationship', function () {
    $user = new User([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $relation = $user->awards();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Award::class);
});
