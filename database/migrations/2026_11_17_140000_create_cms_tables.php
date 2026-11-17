<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('seed_key')->nullable()->unique();
            $table->json('title');
            $table->json('body');
            $table->string('status')->default('draft');
            $table->string('template')->default('standard');
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('homepage_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->json('title');
            $table->json('body')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('visible')->default(false);
            $table->timestamps();
            $table->index(['visible', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_blocks');
        Schema::dropIfExists('pages');
    }
};
