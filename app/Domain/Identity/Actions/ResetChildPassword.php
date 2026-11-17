<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\GuardianAccess;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use SensitiveParameter;

final class ResetChildPassword
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Only a verified, linked, active parent may reset; every session of the child is revoked. */
    public function handle(User $parent, User $child, #[SensitiveParameter] string $password): void
    {
        if ($parent->getAttribute('status') !== 'active'
            || ! $parent->hasRole('parent')
            || ! $parent->can('guardian.child.manage')
            || ! $child->hasRole('student')
            || ! GuardianAccess::parentCanAccessChild($parent, $child)->allowed()) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($parent, $child, $password): void {
            // Changing the hash also invalidates sessions through AuthenticateSession; rows are removed for database sessions.
            $child->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $child->getKey())->delete();
            }

            if ($this->audit->record('guardian.child_password_reset', $child, (int) $parent->getKey()) === null) {
                throw new RuntimeException('Unable to record child password reset audit.');
            }
        });
    }
}
