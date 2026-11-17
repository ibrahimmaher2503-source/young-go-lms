<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz settings and the question bank. Several values are OPEN owner decisions (O-021 result visibility values, O-022 pass mark unit);
 * the defaults here are working choices that the Actions validate, so they can change without a data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('time_limit_minutes')->nullable(); // null means untimed
            $table->unsignedTinyInteger('pass_mark_percent')->nullable(); // null means no pass mark
            $table->unsignedSmallInteger('max_attempts')->nullable(); // null means unlimited
            $table->string('result_visibility', 16)->default('full'); // full | score_only | hidden
            $table->boolean('randomize')->default(false); // shuffle question and option order per attempt
            $table->string('progression', 20)->default('after_submission'); // after_submission | require_pass
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // single | multiple | true_false | short_answer | essay
            $table->json('prompt');
            $table->json('explanation')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true); // a removed question is deactivated so past attempts keep their history
            $table->unsignedInteger('version')->default(1); // raised on every change that can alter grading
            $table->timestamps();
            $table->index(['quiz_id', 'active', 'sort']);
        });

        Schema::create('question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('text'); // for short_answer these are the accepted answers
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['question_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['time_limit_minutes', 'pass_mark_percent', 'max_attempts', 'result_visibility', 'randomize', 'progression']);
        });
    }
};
