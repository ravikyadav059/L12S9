<?php

use App\Models\Question;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

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

use App\Models\Answer;
use App\Models\Replies;
use App\Models\Vote;

test('interaction toggles for save and follow work as expected', function () {
    $user = User::create([
        'first_name' => 'Interacting',
        'last_name' => 'User',
        'email' => 'interact_'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
    ]);

    $question = Question::create([
        'title' => 'Interaction Test Question '.uniqid(),
        'description' => 'Description for testing toggles',
        'status' => 1,
        'save' => [],
        'follow' => [],
    ]);

    $qId = (string) $question->_id;

    actingAs($user);

    $test = Volt::test('question.index')
        ->call('toggleSave', $qId);
    expect($test->get('savedQuestions'))->toContain($qId);

    $test->call('toggleFollowQuestion', $qId);
    expect($test->get('followingQuestions'))->toContain($qId);

    $question->delete();
    $user->delete();
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

test('title uniqueness check prevents duplicate active questions but allows same title on edit', function () {
    $q1 = Question::create([
        'title' => 'Unique Title Question 12345',
        'description' => 'Description here.',
        'status' => 1,
    ]);

    $q2 = Question::create([
        'title' => 'Another Question Title 67890',
        'description' => 'Description here.',
        'status' => 1,
    ]);

    // When creating a new question with same title
    expect(Question::isTitleUnique('Unique Title Question 12345'))->toBeFalse();
    expect(Question::isTitleUnique('Some Brand New Unique Question Title'))->toBeTrue();

    // When editing $q1: keeping same title is allowed because excludeId matches
    expect(Question::isTitleUnique('Unique Title Question 12345', (string) $q1->_id))->toBeTrue();

    // When editing $q1: changing title to $q2's title is blocked
    expect(Question::isTitleUnique('Another Question Title 67890', (string) $q1->_id))->toBeFalse();

    $q1->delete();
    $q2->delete();
});

test('volt question index allows author to edit question and updates data', function () {
    $user = User::factory()->create();

    $question = Question::create([
        'user_id' => (string) $user->_id,
        'title' => 'Initial Question Title for Edit Test',
        'description' => 'Initial long description for edit testing with more than twenty characters.',
        'tags' => ['Laravel', 'PHP'],
        'status' => 1,
    ]);

    actingAs($user);

    Volt::test('question.index')
        ->call('openEditModal', (string) $question->_id)
        ->assertSet('editingQuestionId', (string) $question->_id)
        ->assertSet('editTitle', 'Initial Question Title for Edit Test')
        ->assertSet('showEditModal', true)
        ->set('editTitle', 'Updated Unique Question Title for Edit Test')
        ->set('editDescription', 'Updated new details content with more than twenty characters for test.')
        ->set('editTags', 'Laravel, React, Tailwind')
        ->call('saveEdit')
        ->assertSet('showEditModal', false);

    $question->refresh();
    expect($question->title)->toBe('Updated Unique Question Title for Edit Test');
    expect($question->tags)->toEqual(['Laravel', 'React', 'Tailwind']);

    $question->delete();
    $user->delete();
});

test('authenticated user can upvote a question in feed and updates Vote model and question votes_count', function () {
    $user = User::factory()->create();
    actingAs($user);

    $question = Question::create([
        'title' => 'Question for Upvoting Test',
        'description' => 'Detailed description for upvoting test.',
        'status' => 1,
        'votes_count' => 0,
    ]);

    $qId = (string) $question->_id;

    // 1. Initial Upvote
    Volt::test('question.index')
        ->call('toggleVote', $qId, 'upvote')
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->votes_count)->toBe(1);

    $vote = Vote::where('user_id', (string) $user->_id)->where('question_id', $qId)->first();
    expect($vote)->not->toBeNull();
    expect($vote->vote_type)->toBe('upvote');
    expect($vote->votable_type)->toBe('question');
    expect($vote->answer_id)->toBeNull();

    // 2. Click Upvote again to toggle off (unvote)
    Volt::test('question.index')
        ->call('toggleVote', $qId, 'upvote')
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->votes_count)->toBe(0);

    $voteAfterToggle = Vote::where('user_id', (string) $user->_id)->where('question_id', $qId)->first();
    expect($voteAfterToggle)->toBeNull();

    // 3. Downvote
    Volt::test('question.index')
        ->call('toggleVote', $qId, 'downvote')
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->votes_count)->toBe(0); // 0 floored

    $downVote = Vote::where('user_id', (string) $user->_id)->where('question_id', $qId)->first();
    expect($downVote)->not->toBeNull();
    expect($downVote->vote_type)->toBe('downvote');
    expect($downVote->votable_type)->toBe('question');

    // 4. Switch from Downvote to Upvote
    Volt::test('question.index')
        ->call('toggleVote', $qId, 'upvote');

    $question->refresh();
    expect($question->votes_count)->toBe(1);
    expect($question->upvotes_count)->toBe(1);
    expect($question->downvotes_count)->toBe(0);

    $switchedVote = Vote::where('user_id', (string) $user->_id)->where('question_id', $qId)->first();
    expect($switchedVote->vote_type)->toBe('upvote');

    $switchedVote->delete();
    $question->delete();
    $user->delete();
});

test('guest user voting triggers auth modal dispatch', function () {
    $question = Question::create([
        'title' => 'Guest Vote Protected Question',
        'description' => 'Test description for guest user voting check.',
        'status' => 1,
    ]);

    Volt::test('question.index')
        ->call('toggleVote', (string) $question->_id, 'upvote')
        ->assertDispatched('open-auth-alert');

    $question->delete();
});

test('authenticated user can upvote and downvote on question detail show page', function () {
    $user = User::factory()->create();
    actingAs($user);

    $question = Question::create([
        'title' => 'Show Page Vote Test Question',
        'description' => 'Testing voting directly on the show page.',
        'status' => 1,
        'votes_count' => 0,
        'upvotes_count' => 0,
        'downvotes_count' => 0,
    ]);

    // Upvote on show page
    Volt::test('question.show', ['slug' => $question->slug])
        ->assertSet('userQuestionVote', null)
        ->call('toggleVote', 'upvote')
        ->assertSet('userQuestionVote', 'upvote');

    $question->refresh();
    expect($question->votes_count)->toBe(1);
    expect($question->upvotes_count)->toBe(1);
    expect($question->downvotes_count)->toBe(0);

    // Switch to downvote
    Volt::test('question.show', ['slug' => $question->slug])
        ->assertSet('userQuestionVote', 'upvote')
        ->call('toggleVote', 'downvote')
        ->assertSet('userQuestionVote', 'downvote');

    $question->refresh();
    expect($question->votes_count)->toBe(0);
    expect($question->upvotes_count)->toBe(0);
    expect($question->downvotes_count)->toBe(1);

    // Remove downvote
    Volt::test('question.show', ['slug' => $question->slug])
        ->assertSet('userQuestionVote', 'downvote')
        ->call('toggleVote', 'downvote')
        ->assertSet('userQuestionVote', null);

    $question->refresh();
    expect($question->votes_count)->toBe(0);
    expect($question->upvotes_count)->toBe(0);
    expect($question->downvotes_count)->toBe(0);

    Vote::where('user_id', (string) $user->_id)->delete();
    $question->delete();
    $user->delete();
});

test('user following and followers arrays are updated when following/unfollowing a user', function () {
    $follower = User::create([
        'first_name' => 'John',
        'last_name' => 'Follower',
        'email' => 'follower_'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
        'following' => [],
        'followers' => [],
    ]);

    $author = User::create([
        'first_name' => 'Jane',
        'last_name' => 'Author',
        'email' => 'author_'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
        'following' => [],
        'followers' => [],
    ]);

    $followerId = (string) $follower->_id;
    $authorId = (string) $author->_id;

    // Test Model helper methods directly
    expect($follower->isFollowing($author))->toBeFalse();
    expect($author->isFollowedBy($follower))->toBeFalse();

    $follower->follow($author);
    $follower->refresh();
    $author->refresh();

    expect($follower->isFollowing($author))->toBeTrue();
    expect($follower->following)->toContain($authorId);
    expect($author->followers)->toContain($followerId);

    $follower->unfollow($author);
    $follower->refresh();
    $author->refresh();

    expect($follower->isFollowing($author))->toBeFalse();
    expect($follower->following)->not->toContain($authorId);
    expect($author->followers)->not->toContain($followerId);

    // Test Volt component toggleFollowUser as authenticated user
    actingAs($follower);

    Volt::test('question.index')
        ->call('toggleFollowUser', $authorId, 'Jane Author')
        ->assertDispatched('toast');

    $follower->refresh();
    $author->refresh();

    expect($follower->following)->toContain($authorId);
    expect($author->followers)->toContain($followerId);

    // Toggle again to unfollow
    Volt::test('question.index')
        ->call('toggleFollowUser', $authorId, 'Jane Author')
        ->assertDispatched('toast');

    $follower->refresh();
    $author->refresh();

    expect($follower->following)->not->toContain($authorId);
    expect($author->followers)->not->toContain($followerId);

    // Clean up
    $follower->delete();
    $author->delete();
});

test('user can save and bookmark question and question save array stores user id', function () {
    $user = User::create([
        'first_name' => 'Saver',
        'last_name' => 'User',
        'email' => 'saver_'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
    ]);

    $question = Question::create([
        'title' => 'Question to be Saved '.uniqid(),
        'description' => 'Description for testing save functionality',
        'status' => 1,
        'save' => [],
    ]);

    $userId = (string) $user->_id;
    $questionId = (string) $question->_id;

    // Test Model helper
    expect($question->isSavedBy($user))->toBeFalse();

    $question->toggleSave($user);
    $question->refresh();

    expect($question->isSavedBy($user))->toBeTrue();
    expect($question->save)->toContain($userId);

    $question->toggleSave($user);
    $question->refresh();

    expect($question->isSavedBy($user))->toBeFalse();
    expect($question->save)->not->toContain($userId);

    // Test Livewire component toggleSave
    actingAs($user);

    Volt::test('question.index')
        ->call('toggleSave', $questionId)
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->save)->toContain($userId);

    // Toggle again to remove
    Volt::test('question.index')
        ->call('toggleSave', $questionId)
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->save)->not->toContain($userId);

    $question->delete();
    $user->delete();
});

test('user can follow question and question follow array stores user id', function () {
    $user = User::create([
        'first_name' => 'QuestionFollower',
        'last_name' => 'User',
        'email' => 'qfollower_'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
    ]);

    $question = Question::create([
        'title' => 'Question to be Followed '.uniqid(),
        'description' => 'Description for testing follow functionality',
        'status' => 1,
        'follow' => [],
    ]);

    $userId = (string) $user->_id;
    $questionId = (string) $question->_id;

    // Test Model helper
    expect($question->isFollowedBy($user))->toBeFalse();

    $question->toggleFollow($user);
    $question->refresh();

    expect($question->isFollowedBy($user))->toBeTrue();
    expect($question->follow)->toContain($userId);
    expect($question->followCount())->toBe(1);

    $question->toggleFollow($user);
    $question->refresh();

    expect($question->isFollowedBy($user))->toBeFalse();
    expect($question->follow)->not->toContain($userId);
    expect($question->followCount())->toBe(0);

    // Test Livewire component toggleFollowQuestion
    actingAs($user);

    Volt::test('question.index')
        ->call('toggleFollowQuestion', $questionId)
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->follow)->toContain($userId);

    // Toggle again to unfollow
    Volt::test('question.index')
        ->call('toggleFollowQuestion', $questionId)
        ->assertDispatched('toast');

    $question->refresh();
    expect($question->follow)->not->toContain($userId);

    $question->delete();
    $user->delete();
});

test('replies model stores comments on answers and cascades deletion', function () {
    $user = User::factory()->create();
    $question = Question::create([
        'title' => 'Question with Answer and Replies '.uniqid(),
        'description' => 'Test description for replies model check',
        'status' => 1,
    ]);

    $answer = Answer::create([
        'question_id' => (string) $question->_id,
        'user_id' => (string) $user->_id,
        'content' => '<p>This is a test answer.</p>',
    ]);

    $reply = Replies::create([
        'answer_id' => (string) $answer->_id,
        'user_id' => (string) $user->_id,
        'content' => 'This is a test reply comment.',
        'status' => 1,
    ]);

    expect($reply->user)->not->toBeNull();
    expect($reply->answer)->not->toBeNull();
    expect($answer->replies)->toHaveCount(1);
    expect($answer->replies->first()->content)->toBe('This is a test reply comment.');

    // Cleanup
    $reply->delete();
    $answer->delete();
    $question->delete();
    $user->delete();
});

test('answer author can start editing and save updated content in question show component', function () {
    $user = User::factory()->create();
    $question = Question::create([
        'title' => 'Question For Editing Answer '.uniqid(),
        'description' => 'Test description for editing answer',
        'status' => 1,
    ]);

    $answer = Answer::create([
        'question_id' => (string) $question->_id,
        'user_id' => (string) $user->_id,
        'content' => '<p>Original answer content.</p>',
    ]);

    $ansId = (string) $answer->_id;

    actingAs($user);

    Volt::test('question.show', ['slug' => $question->slug])
        ->call('startEditAnswer', $ansId)
        ->assertSet('editingAnswerId', $ansId)
        ->assertSet('editingAnswerContent', '<p>Original answer content.</p>')
        ->set('editingAnswerContent', '<p>Updated answer content with rich text.</p>')
        ->call('saveEditAnswer')
        ->assertSet('editingAnswerId', null);

    $answer->refresh();
    expect($answer->content)->toBe('<p>Updated answer content with rich text.</p>');

    $answer->delete();
    $question->delete();
    $user->delete();
});
