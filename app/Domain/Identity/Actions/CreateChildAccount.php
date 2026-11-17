<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CreateChildAccount
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{username:string, password:string, name_ar?:?string, name_en?:?string, birth_year:int, birth_month:int}  $input
     */
    public function handle(User $parent, array $input): User
    {
        // Parent role required explicitly: Gate::before lets super_admin pass a permission-only check.
        if ($parent->getAttribute('status') !== 'active' || ! $parent->hasRole('parent') || ! $parent->can('guardian.child.manage')) {
            throw new AuthorizationException;
        }

        $username = mb_strtolower(trim($input['username']));
        $displayName = $input['name_ar'] ?? $input['name_en'] ?? $username;

        try {
            return DB::transaction(function () use ($parent, $input, $username, $displayName): User {
                $child = new User;
                $child->forceFill([
                    'name' => $displayName,
                    'display_name' => $displayName,
                    'name_ar' => $input['name_ar'] ?? null,
                    'name_en' => $input['name_en'] ?? null,
                    'username' => $username,
                    'email' => null,
                    'password' => $input['password'],
                    'birth_year' => $input['birth_year'],
                    'birth_month' => $input['birth_month'],
                    'locale' => $parent->getAttribute('locale'),
                ])->save();
                $child->assignRole('student');

                DB::table('guardian_profiles')->insertOrIgnore([
                    'user_id' => $parent->getKey(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                GuardianChildLink::query()->create([
                    'guardian_id' => $parent->getKey(),
                    'child_id' => $child->getKey(),
                    'status' => GuardianChildLink::STATUS_VERIFIED,
                    'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
                    'verified_at' => now(),
                ]);

                $recorded = $this->audit->record('guardian.child_created', $child, (int) $parent->getKey(), null, [
                    'username' => $username,
                    'birth_year' => $input['birth_year'],
                    'birth_month' => $input['birth_month'],
                ], ['acting_for' => ['type' => User::class, 'id' => $child->getKey()]]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record child creation audit.');
                }

                return $child;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (User::query()->where('username', $username)->exists()) {
                throw ValidationException::withMessages([
                    'username' => __('validation.unique', ['attribute' => __('validation.attributes.username')]),
                ]);
            }

            throw $exception;
        }
    }
}
