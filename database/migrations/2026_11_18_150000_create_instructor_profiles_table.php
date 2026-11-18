<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public instructor profile (T-P2-09). Public only when approved by an admin; payout data lives elsewhere (T-P9-01). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug_ar', 120)->unique();
            $table->string('slug_en', 120)->unique();
            $table->json('display_name')->nullable();
            $table->json('headline')->nullable();
            $table->json('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('instructor_profile_category', function (Blueprint $table): void {
            $table->foreignId('instructor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['instructor_profile_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_profile_category');
        Schema::dropIfExists('instructor_profiles');
    }
};
