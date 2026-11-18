<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// T-P9-05: admin template overrides, per-user opt-outs for optional notices, and course/cohort announcements.
return new class extends Migration
{
    public function up(): void
    {
        // One row per matrix event. A null text means "use the shipped language file default".
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('title_ar', 160)->nullable();
            $table->string('title_en', 160)->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Only optional events are ever stored. A row means the user turned that notice off.
        Schema::create('user_notification_opt_outs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'event_key']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->text('body_ar');
            $table->text('body_en');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('user_notification_opt_outs');
        Schema::dropIfExists('notification_templates');
    }
};
