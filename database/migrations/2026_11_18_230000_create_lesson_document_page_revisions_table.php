<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_document_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('lesson_document_pages')->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('previous_text');
            $table->longText('text');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_document_page_revisions');
    }
};
