<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Answer;
use App\Models\Question;
use App\Models\Vote;
use Illuminate\Contracts\Console\Kernel;

$questions = Question::all();
echo 'Total Questions: '.$questions->count()."\n";
echo 'Total Answers: '.Answer::count()."\n\n";

$mismatchCount = 0;
foreach ($questions as $q) {
    $qId = (string) ($q->_id ?? $q->id);

    // Answers count check
    $actualAnswers = Answer::where('question_id', $qId)
        ->orWhere('question_id', (string) $qId)
        ->count();

    // Votes check
    $upvotes = Vote::where(function ($query) use ($qId) {
        $query->where('question_id', $qId)->orWhere('votable_id', $qId);
    })->where('vote_type', 'upvote')->count();

    $downvotes = Vote::where(function ($query) use ($qId) {
        $query->where('question_id', $qId)->orWhere('votable_id', $qId);
    })->where('vote_type', 'downvote')->count();

    $actualVotes = max(0, $upvotes - $downvotes);

    $storedAns = $q->answer_count;
    $storedVotes = $q->votes_count;

    $ansMismatch = ($storedAns !== $actualAnswers);
    $voteMismatch = ($storedVotes !== $actualVotes);

    if ($ansMismatch || $voteMismatch || is_null($storedAns) || is_null($storedVotes)) {
        $mismatchCount++;
        echo "ID: {$qId} | Title: ".substr($q->title, 0, 30)."\n";
        echo '   - Answers: Stored='.var_export($storedAns, true).", Actual={$actualAnswers}\n";
        echo '   - Votes: Stored='.var_export($storedVotes, true).", Actual={$actualVotes} (Up: {$upvotes}, Down: {$downvotes})\n";
    }
}

echo "\nSummary: {$mismatchCount} out of ".$questions->count()." questions have mismatched or null counts.\n";
