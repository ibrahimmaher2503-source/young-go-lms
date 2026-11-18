<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePermissions('learner.360', 'learner.record');
    }

    public function down(): void
    {
        $this->renamePermissions('learner.record', 'learner.360');
    }

    private function renamePermissions(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            foreach (['view', 'sensitive'] as $ability) {
                $targetName = "{$to}.{$ability}";

                foreach (DB::table('permissions')->where('name', "{$from}.{$ability}")->get() as $permission) {
                    $duplicateId = DB::table('permissions')
                        ->where('name', $targetName)
                        ->where('guard_name', $permission->guard_name)
                        ->value('id');

                    if ($duplicateId !== null) {
                        $this->mergeGrants($permission->id, $duplicateId);
                    }

                    DB::table('permissions')->where('id', $permission->id)->update(['name' => $targetName]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function mergeGrants(int $permissionId, int $duplicateId): void
    {
        foreach ([
            'role_has_permissions' => ['role_id'],
            'model_has_permissions' => ['model_type', 'model_id'],
        ] as $table => $columns) {
            $grants = DB::table($table)
                ->selectRaw('? as permission_id', [$permissionId])
                ->addSelect($columns)
                ->where('permission_id', $duplicateId);

            DB::table($table)->insertOrIgnoreUsing(['permission_id', ...$columns], $grants);
        }

        DB::table('permissions')->where('id', $duplicateId)->delete();
    }
};
