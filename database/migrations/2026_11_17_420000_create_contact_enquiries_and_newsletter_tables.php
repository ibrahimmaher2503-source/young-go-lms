<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P2-08 (YG-WEB-007, YG-WEB-008): public contact enquiries with an internal inbox (status, assignee) and newsletter
 * subscribers with consent version, source and unsubscribe state. One subscription row per lower-cased address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('phone', 32)->nullable(); // E.164
            $table->text('message');
            $table->string('locale', 8);
            $table->string('status', 16)->default('new');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('assigned_to_id');
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('locale', 8);
            $table->string('source', 24);
            $table->string('consent_version', 64);
            $table->timestamp('consented_at');
            $table->timestamp('confirmed_at')->nullable(); // reserved for double opt-in once a mail provider exists
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->index('unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('contact_enquiries');
    }
};
