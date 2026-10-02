<?php

use App\Models\Question;

test('it can instantiate question model and generate slug on title set', function () {
    $question = new Question([
        'title' => 'How to use Laravel with MongoDB?',
        'description' => 'This is a test question description.',
        'tags' => ['laravel', 'mongodb', 'php'],
        'views_count' => 10,
        'votes_count' => 5,
        'shared_count' => 2,
        'answer_count' => 1,
        'likes_count' => 3,
        'is_closed' => false,
        'is_reported' => false,
    ]);

    expect($question->title)->toBe('How to use Laravel with MongoDB?')
        ->and($question->slug)->toBe('how-to-use-laravel-with-mongodb')
        ->and($question->tags)->toBe(['laravel', 'mongodb', 'php'])
        ->and($question->getConnectionName())->toBe('mongodb')
        ->and($question->getTable())->toBe('question');
});

test('it defines relations for user, answers, and votes', function () {
    $question = new Question;

    expect(method_exists($question, 'user'))->toBeTrue()
        ->and(method_exists($question, 'answers'))->toBeTrue()
        ->and(method_exists($question, 'votes'))->toBeTrue();
});
