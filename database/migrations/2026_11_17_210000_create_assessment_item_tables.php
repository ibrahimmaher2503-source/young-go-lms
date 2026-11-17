<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base tables for quizzes and assignments so they can sit in the curriculum next to lessons. Order and publish state
 * live on curriculum_items only (ED-002); questions, attempts and grading arrive in P6.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quizzes', 'assignments'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->json('title');
                $table->json('description')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quizzes');
    }
};
