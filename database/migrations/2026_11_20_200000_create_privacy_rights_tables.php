<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * T-P9-08: data-subject requests, retention policy overrides and the "anonymized" marks. `data_requests` rows point at users
 * with RESTRICT (a request is evidence and the user row is never deleted; an account is anonymized in place). `retention_policies`
 * holds only overrides; a class with no row uses the documented default (O-025 stays OPEN). Certificates keep their number and
 * token so public verification still works; `anonymized_at` marks that the holder's name was removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('status', 16)->default('requested');
            $table->text('details')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->string('archive_path')->nullable();
            $table->string('archive_sha256', 64)->nullable();
            $table->unsignedBigInteger('archive_size')->nullable();
            $table->timestamp('archive_expires_at')->nullable();
            $table->timestamp('archive_downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
            $table->index(['subject_id', 'type', 'status']);
        });

        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('data_class', 40)->unique();
            $table->boolean('overridden')->default(false); // false: the class default applies; the row only tracks the last prune
            $table->unsignedInteger('retention_days')->nullable(); // when overridden: null keeps the data indefinitely
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_pruned_at')->nullable();
            $table->unsignedInteger('last_pruned_count')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable();
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable();
        });

        $this->grantPermission();
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('data_requests');
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = (string) config('permissions.guard', 'web');
        $admin = DB::table('roles')->where('name', 'admin')->where('guard_name', $guard)->value('id');
        if ($admin === null) {
            return;
        }

        DB::table('permissions')->insertOrIgnore(['name' => 'privacy.manage', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'privacy.manage')->where('guard_name', $guard)->value('id');
        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $admin]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
