<?php

use App\Models\Vote;

test('it can instantiate vote model', function () {
    $vote = new Vote([
        'user_id' => 'u_123',
        'question_id' => 'q_123',
        'vote_type' => 'up',
    ]);

    expect($vote->user_id)->toBe('u_123')
        ->and($vote->question_id)->toBe('q_123')
        ->and($vote->vote_type)->toBe('up')
        ->and($vote->getConnectionName())->toBe('mongodb')
        ->and($vote->getTable())->toBe('votes');
});

test('it defines relations for user, question, answer, and votable', function () {
    $vote = new Vote;

    expect(method_exists($vote, 'user'))->toBeTrue()
        ->and(method_exists($vote, 'question'))->toBeTrue()
        ->and(method_exists($vote, 'answer'))->toBeTrue()
        ->and(method_exists($vote, 'votable'))->toBeTrue();
});
