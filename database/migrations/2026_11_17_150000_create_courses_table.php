<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('title');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('slug_ar', 120)->unique();
            $table->string('slug_en', 120)->unique();
            $table->string('level')->nullable();
            $table->string('content_language', 16)->nullable();
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();
            $table->string('format')->default('recorded');
            $table->string('owner_type')->default('academy');
            $table->string('status')->default('draft')->index();
            $table->string('access_mode')->nullable()->index();
            $table->boolean('featured')->default(false);
            $table->boolean('ai_enabled')->default(true);
            $table->text('search_text');
            $table->timestamps();
        });

        Schema::create('course_instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->string('role')->default('instructor');
            $table->string('scope')->default('view');
            $table->unique(['course_id', 'instructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructors');
        Schema::dropIfExists('courses');
    }
};
