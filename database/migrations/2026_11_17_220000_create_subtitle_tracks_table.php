<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitle_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label', 80);
            $table->string('path');
            $table->string('sha256', 64);
            $table->unsignedInteger('size');
            $table->boolean('is_default')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['lesson_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_tracks');
    }
};
