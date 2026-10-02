<?php

use Livewire\Volt\Volt;

test('questions route is registered', function () {
    $this->assertTrue(Route::has('questions.index'));
});

test('questions index page renders successfully', function () {
    $response = $this->get(route('questions.index'));
    $response->assertOk();
    $response->assertSee('Question &amp; Answer', false);
    $response->assertSee('Community Stats');
    $response->assertSee('Ask Question');
});

test('volt question index component renders and handles search and filters', function () {
    Volt::test('question.index')
        ->assertSee('Question &amp; Answer', false)
        ->assertSee('Ask Question')
        ->assertSee('Community Stats')
        ->set('search', 'non_existent_search_query_12345')
        ->assertSee('No results for')
        ->assertSee('non_existent_search_query_12345')
        ->call('clearSearch')
        ->assertSet('search', '');
});

test('volt question index component can sort questions', function () {
    Volt::test('question.index')
        ->assertSet('sortBy', 'newest')
        ->call('setSort', 'unanswered')
        ->assertSet('sortBy', 'unanswered')
        ->call('setSort', 'active')
        ->assertSet('sortBy', 'active');
});

test('skills management in question index component works', function () {
    $test = Volt::test('question.index')
        ->call('addSkill', 'GraphQL');

    expect($test->get('userSkills'))->toContain('GraphQL');

    $test->call('removeSkill', 'GraphQL');

    expect($test->get('userSkills'))->not->toContain('GraphQL');
});

test('interaction toggles for vote, save, and follow work as expected', function () {
    $test = Volt::test('question.index')
        ->call('toggleVote', 'dummy_q_id_1', 'up');

    expect($test->get('votedQuestions')['dummy_q_id_1'] ?? null)->toBe('up');

    $test->call('toggleSave', 'dummy_q_id_1');
    expect($test->get('savedQuestions'))->toContain('dummy_q_id_1');

    $test->call('toggleFollowQuestion', 'dummy_q_id_1');
    expect($test->get('followingQuestions'))->toContain('dummy_q_id_1');
});
