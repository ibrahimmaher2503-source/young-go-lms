<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->json('title');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('published')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'sort']);
        });

        // Lessons carry no sort column: curriculum_items is the only ordering source (ED-002).
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16)->default('video');
            $table->json('title');
            $table->json('description')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('curriculum_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained('course_sections')->restrictOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('itemable_type', 32);
            $table->unsignedBigInteger('itemable_id');
            $table->string('status', 16)->default('draft');
            $table->string('visibility', 16)->default('enrolled');
            $table->string('unlock_rule', 16)->default('none');
            $table->timestamp('unlock_at')->nullable();
            $table->unsignedSmallInteger('unlock_days')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('ai_enabled')->default(true);
            $table->timestamps();
            $table->unique(['itemable_type', 'itemable_id']);
            $table->index(['course_id', 'section_id', 'sort']);
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_items');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_sections');
    }
};
