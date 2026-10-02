<?php

use App\Models\Answer;

test('it can instantiate answer model', function () {
    $answer = new Answer([
        'question_id' => 'q_123',
        'user_id' => 'u_123',
        'content' => 'This is an answer.',
        'votes_count' => 3,
        'is_accepted' => true,
        'status' => 1,
    ]);

    expect($answer->question_id)->toBe('q_123')
        ->and($answer->content)->toBe('This is an answer.')
        ->and($answer->votes_count)->toBe(3)
        ->and($answer->is_accepted)->toBeTrue()
        ->and($answer->getConnectionName())->toBe('mongodb')
        ->and($answer->getTable())->toBe('answers');
});

test('it defines relations for user, question, and votes', function () {
    $answer = new Answer;

    expect(method_exists($answer, 'user'))->toBeTrue()
        ->and(method_exists($answer, 'question'))->toBeTrue()
        ->and(method_exists($answer, 'votes'))->toBeTrue();
});
