<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;

$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();
$item = CurriculumItem::find(100);
$quiz = Quiz::find($item->itemable_id);

if ($quiz->progression === null) {
    app(ConfigureQuiz::class)->__invoke($instructor, $quiz, [
        'time_limit_minutes' => 30,
        'pass_mark_percent' => 50,
        'max_attempts' => 3,
        'result_visibility' => 'full',
        'randomize' => false,
        'progression' => 'after_submission',
    ]);
    echo "Configured quiz 1.\n";
}

if (Question::where('quiz_id', $quiz->id)->count() === 0) {
    app(ManageQuestions::class)->add($instructor, $quiz, [
        'type' => 'single',
        'prompt' => ['ar' => 'ما هو لون السماء في يوم مشمس؟', 'en' => 'What color is the sky on a sunny day?'],
        'points' => 10,
        'options' => [
            ['text' => ['ar' => 'أزرق', 'en' => 'Blue'], 'is_correct' => true],
            ['text' => ['ar' => 'أحمر', 'en' => 'Red'], 'is_correct' => false],
            ['text' => ['ar' => 'أخضر', 'en' => 'Green'], 'is_correct' => false],
        ],
    ]);
    echo "Added question to quiz 1.\n";
}

$item->update(['status' => 'published']);
echo "Item 100 published: status = {$item->status}, questions = ".Question::where('quiz_id', $quiz->id)->count()."\n";
