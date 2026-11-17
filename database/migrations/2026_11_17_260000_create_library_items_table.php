<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reusable media and files (ED-026). Lessons attach an item by reference; a referenced item cannot be deleted. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 12); // academy or instructor
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 12); // video, audio, document, image, file
            $table->json('title');
            $table->json('tags')->nullable();
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('protection_tier', 2)->default('T1');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        foreach (['media_assets', 'lesson_resources'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('library_item_id')->nullable()->constrained('library_items')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['media_assets', 'lesson_resources'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('library_item_id');
            });
        }
        Schema::dropIfExists('library_items');
    }
};
