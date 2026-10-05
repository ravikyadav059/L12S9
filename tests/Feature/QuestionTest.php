<?php

use App\Models\Question;
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

test('volt question index can load more questions and search skills', function () {
    $test = Volt::test('question.index')
        ->assertSet('perPage', 10)
        ->call('loadMore')
        ->assertSet('perPage', 20);

    $results = $test->instance()->searchSkills('non_existent_dummy_skill_xyz');
    expect($results)->toBeArray();
});

test('inactive questions with status 0 or string 0 are excluded from listings', function () {
    $activeQuestion = Question::create([
        'title' => 'Active Status Question XYZ',
        'description' => 'This is an active question description.',
        'status' => 1,
        'tags' => ['PHP'],
    ]);

    $inactiveQuestion = Question::create([
        'title' => 'Inactive Status Question 000',
        'description' => 'This is an inactive question description.',
        'status' => 0,
        'tags' => ['PHP'],
    ]);

    $inactiveStringQuestion = Question::create([
        'title' => 'Inactive String Status Question 999',
        'description' => 'This is an inactive string status question description.',
        'status' => '0',
        'tags' => ['PHP'],
    ]);

    $test = Volt::test('question.index');
    $test->assertSee('Active Status Question XYZ')
        ->assertDontSee('Inactive Status Question 000')
        ->assertDontSee('Inactive String Status Question 999');

    $activeQuestion->delete();
    $inactiveQuestion->delete();
    $inactiveStringQuestion->delete();
});

test('question detail page renders successfully for a given question slug', function () {
    $question = Question::create([
        'title' => 'Test Detail Question ABC',
        'description' => 'Detailed explanation of something interesting.',
        'status' => 1,
        'tags' => ['Laravel', 'Vue'],
    ]);

    $response = $this->get(route('questions.show', $question->slug ?: (string) $question->_id));
    $response->assertOk();
    $response->assertSee('Test Detail Question ABC');
    $response->assertSee('Your Answer');

    $question->delete();
});

test('question slug is automatically generated and made unique when titles collide', function () {
    $q1 = Question::create([
        'title' => 'Duplicate Title Test Question',
        'description' => 'First question with duplicate title.',
        'status' => 1,
    ]);

    $q2 = Question::create([
        'title' => 'Duplicate Title Test Question',
        'description' => 'Second question with duplicate title.',
        'status' => 1,
    ]);

    expect($q1->slug)->toBe('duplicate-title-test-question');
    expect($q2->slug)->toBe('duplicate-title-test-question-1');

    $q1->delete();
    $q2->delete();
});

test('title uniqueness check prevents duplicate active questions', function () {
    $q = Question::create([
        'title' => 'Unique Title Question 12345',
        'description' => 'Description here.',
        'status' => 1,
    ]);

    expect(Question::isTitleUnique('Unique Title Question 12345'))->toBeFalse();
    expect(Question::isTitleUnique('Some Brand New Unique Question Title'))->toBeTrue();

    $q->delete();
});
