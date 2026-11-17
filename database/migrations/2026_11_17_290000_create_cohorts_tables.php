<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cohorts of live and hybrid courses: capacity, seated members and an ordered waitlist (YG-GAP-COH-001..003). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('capacity')->nullable(); // null means no limit
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('open'); // open | closed (closed takes no new seats or waitlist)
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('cohort_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16); // seated | waitlisted | removed
            $table->timestamp('queued_at')->nullable(); // waitlist order is queued_at then id
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['cohort_id', 'learner_id']);
            $table->index(['cohort_id', 'status', 'queued_at']);
        });

        Schema::create('cohort_instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cohort_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_instructors');
        Schema::dropIfExists('cohort_members');
        Schema::dropIfExists('cohorts');
    }
};
