<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;

echo 'Courses count: '.Course::count()."\n";
echo 'CurriculumItems count: '.CurriculumItem::count()."\n";
foreach (CurriculumItem::all() as $item) {
    echo "- Item ID {$item->id}, type: {$item->type}, title: ".json_encode($item->title).", section_id: {$item->section_id}\n";
}

echo "\nQuizzes count: ".Quiz::count()."\n";
foreach (Quiz::all() as $q) {
    echo "- Quiz ID {$q->id}, title: ".json_encode($q->title).", item_id: {$q->item_id}\n";
}

echo "\nQuizAttempts count: ".QuizAttempt::count()."\n";
