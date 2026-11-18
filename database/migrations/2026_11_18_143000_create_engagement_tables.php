<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * T-P9-02: wishlists (one row per learner and course) and moderated course reviews (one per learner and course).
 * A review stores only a rating, a text body and a display-name snapshot (first name and last initial; the full name is
 * never copied). Both tables go with the account or the course. Also grants the new `review.moderate` permission to an
 * already-seeded admin role; fresh installs get it from the role seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'course_id']);
            $table->index('course_id');
        });

        Schema::create('course_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('author_name', 80);
            $table->string('status', 16)->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'learner_id']);
            $table->index(['course_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        $this->grantModeration();
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('wishlists');
    }

    private function grantModeration(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = (string) config('permissions.guard', 'web');
        $admin = DB::table('roles')->where('name', 'admin')->where('guard_name', $guard)->value('id');
        if ($admin === null) {
            return;
        }

        DB::table('permissions')->insertOrIgnore(['name' => 'review.moderate', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'review.moderate')->where('guard_name', $guard)->value('id');
        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $admin]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
