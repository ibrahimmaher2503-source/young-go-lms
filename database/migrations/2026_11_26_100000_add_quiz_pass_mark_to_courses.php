<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** O-020: optional course-level default pass mark for its quizzes (1-100). Null follows the platform default. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('quiz_pass_mark_percent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('quiz_pass_mark_percent');
        });
    }
};
