<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review of handed-in work (T-P6-04). Every reviewer decision is its own row, so the history of grades is kept and each
 * decision can be notified once. Feedback is a thread on the submission, optionally tied to a revision and a decision.
 * Scores are 0 to 100 (assignments have no points field; working choice). The late penalty is copied from the reviewed
 * revision when the decision is made. Essay answers get a feedback text next to their awarded points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('decision', 20); // graded | accepted | rejected | revision_requested
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 5, 2)->nullable(); // 0-100 as entered
            $table->unsignedTinyInteger('late_penalty_percent')->nullable();
            $table->decimal('final_score', 5, 2)->nullable(); // score after the late penalty
            $table->timestamps();
            $table->index(['submission_id', 'id']);
        });

        Schema::create('submission_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained('submission_decisions')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['submission_id', 'id']);
        });

        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->text('feedback')->nullable(); // reviewer comment on an essay answer
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->dropColumn('feedback');
        });
        Schema::dropIfExists('submission_feedback');
        Schema::dropIfExists('submission_decisions');
    }
};
