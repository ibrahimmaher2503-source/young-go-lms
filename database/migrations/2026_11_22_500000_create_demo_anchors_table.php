<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-P9-11 (ED-022): the demo marker. One row per root record (user, course, coupon, bundle, plan) the demo seeder creates.
 * Everything else in the demo dataset hangs off these roots through foreign keys, so `demo:reset` removes exactly the
 * demo rows and never a real one. No foreign key on purpose: the registry must survive a half-finished purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_anchors', function (Blueprint $table): void {
            $table->id();
            $table->string('table_name', 64);
            $table->unsignedBigInteger('record_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_anchors');
    }
};
