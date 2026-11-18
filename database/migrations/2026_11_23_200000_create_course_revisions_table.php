<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P3-07: revisions of a published course. `base` is the live content when the revision was opened, `payload` the proposed
 * content. `open_course_id` holds the course id while the revision is a draft or pending and is null afterwards, so the unique
 * index allows one open revision per course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 16)->default('draft');
            $table->json('base');
            $table->json('payload');
            $table->unsignedBigInteger('open_course_id')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'number']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_revisions');
    }
};
