<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * T-P9-10 (ED-027): one row per support "view as learner" session, and the three permissions that gate Learner 360.
 * Rows point at users with RESTRICT: a session is audit evidence and user rows are never deleted. The staff member stays
 * signed in as themselves; nothing here stores or replaces credentials.
 */
return new class extends Migration
{
    private const array GRANTS = [
        'admin' => ['learner.360.view', 'learner.360.sensitive', 'support.view_as'],
        'support' => ['learner.360.view', 'support.view_as'],
    ];

    public function up(): void
    {
        Schema::create('view_as_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('learner_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_cause', 16)->nullable();
            $table->unsignedInteger('page_views')->default(0);
            $table->timestamps();

            $table->index(['staff_id', 'ended_at']);
            $table->index(['learner_id', 'started_at']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('view_as_sessions');
    }

    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = (string) config('permissions.guard', 'web');

        foreach (self::GRANTS as $role => $names) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', $guard)->value('id');
            if ($roleId === null) {
                continue;
            }

            foreach ($names as $name) {
                DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
                $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id');
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
