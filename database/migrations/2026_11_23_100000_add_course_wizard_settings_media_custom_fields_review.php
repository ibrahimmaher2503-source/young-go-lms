<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P3-03: access duration and bundle/manual-access flags, cover image and promo video reference, custom field values,
 * the review trail for the approval workflow, and the admin-defined custom field definitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('access_duration_days')->nullable();
            $table->boolean('bundle_eligible')->default(true);
            $table->boolean('manual_access_allowed')->default(true);
            $table->string('cover_image', 255)->nullable();
            $table->json('promo_video')->nullable();
            $table->json('custom_values')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
        });

        Schema::create('course_custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('field_key', 60)->unique();
            $table->string('type', 16);
            $table->json('label');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('min_value')->nullable();
            $table->unsignedInteger('max_value')->nullable();
            $table->string('visibility', 16)->default('internal');
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_custom_fields');
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['access_duration_days', 'bundle_eligible', 'manual_access_allowed', 'cover_image', 'promo_video', 'custom_values', 'submitted_at', 'reviewed_at', 'review_reason']);
        });
    }
};
