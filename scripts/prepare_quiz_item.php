<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\User;

$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();
$course = Course::where('slug_ar', 'demo-course')->firstOrFail();
$section = CourseSection::where('course_id', $course->id)->firstOrFail();

// Check if quiz already exists
$existingQuizItem = CurriculumItem::where('course_id', $course->id)->where('itemable_type', 'quiz')->first();
if ($existingQuizItem) {
    echo "Quiz item already exists: ID {$existingQuizItem->id}\n";
    exit(0);
}

echo "Creating quiz item for demo course...\n";
$item = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', [
    'title' => ['ar' => 'اختبار تجريبي', 'en' => 'Demo Quiz'],
    'description' => ['ar' => 'وصف الاختبار التجريبي', 'en' => 'Demo Quiz Description'],
]);

$quiz = $item->itemable;
if (! ($quiz instanceof Quiz)) {
    $quiz = Quiz::find($item->itemable_id);
}

// Configure Quiz
app(ConfigureQuiz::class)->__invoke($instructor, $quiz, [
    'time_limit_minutes' => 30,
    'pass_mark_percent' => 50,
    'max_attempts' => 3,
    'result_visibility' => 'full',
    'randomize' => false,
    'progression' => 'after_submission',
]);

// Add a single-choice question
app(ManageQuestions::class)->add($instructor, $quiz, [
    'type' => 'single',
    'prompt' => ['ar' => 'ما هو لون السماء في يوم مشمس؟', 'en' => 'What color is the sky on a sunny day?'],
    'points' => 10,
    'options' => [
        ['text' => ['ar' => 'أزرق', 'en' => 'Blue'], 'correct' => true],
        ['text' => ['ar' => 'أحمر', 'en' => 'Red'], 'correct' => false],
        ['text' => ['ar' => 'أخضر', 'en' => 'Green'], 'correct' => false],
    ],
]);

// Publish the quiz item so student can access it
$item->update(['status' => 'published']);
echo "Quiz item created and published successfully: Item ID {$item->id}, Quiz ID {$quiz->id}\n";
