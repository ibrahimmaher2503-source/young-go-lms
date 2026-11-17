<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bilingual blog (T-P2-07, YG-WEB-006): categories, tags, posts and the post-tag pivot. Text fields are per-locale JSON. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('slug_ar', 120)->unique();
            $table->string('slug_en', 120)->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 20)->default('active');
            $table->text('search_text');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('slug_ar', 120)->unique();
            $table->string('slug_en', 120)->unique();
            $table->text('search_text');
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug_ar', 120)->unique();
            $table->string('slug_en', 120)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->string('featured_image')->nullable();
            $table->json('featured_image_alt')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->text('search_text');
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['blog_post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('blog_categories');
    }
};
