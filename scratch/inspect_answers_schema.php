<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

// Sample 5 answers
$answers = DB::connection('mongodb')->table('answers')->take(10)->get();
echo "Sample answers in MongoDB:\n";
foreach ($answers as $a) {
    echo json_encode($a)."\n\n";
}

// Check question 6ac39b3e249227590c057073 specifically
$targetQ = DB::connection('mongodb')->table('question')->where('_id', new ObjectId('6ac39b3e249227590c057073'))->first();
echo "\nTarget Question (6ac39b3e249227590c057073):\n";
echo json_encode($targetQ)."\n\n";

// Search if any answers have this question_id or slug or title
$qIdStr = '6ac39b3e249227590c057073';
$ansMatching = DB::connection('mongodb')->table('answers')
    ->where('question_id', $qIdStr)
    ->orWhere('question_id', new ObjectId($qIdStr))
    ->orWhere('question_id', 'z-heh-jee-jej-jej')
    ->get();

echo 'Answers matching target question: '.count($ansMatching)."\n";
foreach ($ansMatching as $a) {
    echo json_encode($a)."\n";
}

// Check all distinct question_id values in answers table
$allAnswers = DB::connection('mongodb')->table('answers')->get();
echo "\nTotal answers in answers collection: ".count($allAnswers)."\n";
$questionIdsInAnswers = [];
foreach ($allAnswers as $ans) {
    $rawQid = $ans['question_id'] ?? ($ans->question_id ?? null);
    $type = gettype($rawQid);
    if (is_object($rawQid)) {
        $type = get_class($rawQid);
    }
    $questionIdsInAnswers[] = (string) $rawQid.' ('.$type.')';
}
echo "Sample question_ids in answers collection:\n";
print_r(array_slice(array_unique($questionIdsInAnswers), 0, 15));
