<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Models\MigrationRecord;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Domain\Shared\Phone\PhoneNumbers;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

final class UserImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Users;
    }

    public function sourceTable(): string
    {
        return 'users';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'role_id' => $row['role_id'] ?? null,
            'status' => $row['status'] ?? null,
            'is_instructor' => $row['is_instructor'] ?? null,
            'title' => $row['title'] ?? null,
            'biography' => $row['biography'] ?? null,
            'image' => $row['image'] ?? null,
            'birth_date' => $row['birth_date'] ?? ($row['dob'] ?? null),
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return RowImportResult::rejected('invalid_email', 'Legacy user has missing or invalid email address.');
        }

        $existingTargetId = $context->existingRecord?->target_id;
        if (! $context->idMap->claimUniqueKey('user_email', $email, $sourceId)) {
            return RowImportResult::rejected('duplicate_email', "Duplicate email '{$email}' already mapped to another legacy user.");
        }

        $existingByEmail = User::query()->where('email', $email)->first();
        if ($existingByEmail !== null && $existingTargetId !== (int) $existingByEmail->getKey()) {
            // Allow matching the existing seeded user only if no other legacy record already owns this user ID.
            $alreadyClaimed = MigrationRecord::query()
                ->where('source_system', $context->sourceSystem)
                ->where('entity', LegacyEntity::Users->value)
                ->where('target_id', $existingByEmail->getKey())
                ->where('source_id', '!=', $sourceId)
                ->exists();

            if ($alreadyClaimed) {
                return RowImportResult::rejected('duplicate_email', "Duplicate email '{$email}' already mapped to another legacy user.");
            }
        }

        $fullName = trim((string) ($row['first_name'] ?? '').' '.(string) ($row['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = strstr($email, '@', true) ?: 'User '.$sourceId;
        }

        $names = ScriptNameMapper::splitPersonName($fullName);
        $exceptions = [];
        $phone = null;
        $rawPhone = trim((string) ($row['phone'] ?? ''));
        if ($rawPhone !== '') {
            try {
                $phone = PhoneNumbers::normalize($rawPhone, 'EG');
            } catch (InvalidArgumentException) {
                $exceptions[] = new ImportExceptionData(
                    'unparsed_phone',
                    MigrationExceptionSeverity::Warning,
                    'Legacy phone number could not be normalized to E.164; stored as null.',
                    ['raw_phone' => $rawPhone],
                );
            }
        }

        $birth = LegacyDateParser::birthYearMonth($row['birth_date'] ?? ($row['dob'] ?? null));
        $status = (int) ($row['status'] ?? 1) === 1 ? 'active' : 'suspended';
        $createdAt = LegacyDateParser::toUtc($row['date_added'] ?? null) ?? now()->utc();
        $verifiedAt = $status === 'active' && trim((string) ($row['verification_code'] ?? '')) === '' ? $createdAt : null;

        if ($context->dryRun) {
            $isUpdate = $existingTargetId !== null;
            $shadowId = $context->idMap->rememberShadow(
                LegacyEntity::Users,
                $sourceId,
                User::class,
                $existingTargetId ?? ($existingByEmail !== null ? (int) $existingByEmail->getKey() : null),
                ['is_instructor' => (int) ($row['is_instructor'] ?? 0) === 1],
            );

            return $isUpdate
                ? RowImportResult::updated(User::class, $shadowId, ['is_instructor' => (int) ($row['is_instructor'] ?? 0) === 1], $exceptions)
                : RowImportResult::imported(User::class, $shadowId, ['is_instructor' => (int) ($row['is_instructor'] ?? 0) === 1], $exceptions);
        }

        $user = $existingTargetId !== null
            ? User::query()->find($existingTargetId)
            : $existingByEmail;

        $isNew = $user === null;
        if ($isNew) {
            $user = new User;
            $user->forceFill([
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'locale' => $context->defaultLocale,
                'timezone' => $context->defaultTimezone,
            ]);
        }

        $user->forceFill([
            'name' => $names['display_name'] !== '' ? $names['display_name'] : $fullName,
            'name_ar' => $names['name_ar'],
            'name_en' => $names['name_en'],
            'display_name' => $names['display_name'] !== '' ? $names['display_name'] : null,
            'phone' => $phone,
            'birth_year' => $birth['birth_year'],
            'birth_month' => $birth['birth_month'],
            'status' => $status,
            'email_verified_at' => $user->email_verified_at ?? $verifiedAt,
        ]);

        if ($isNew) {
            $user->created_at = $createdAt->toMutable();
        }
        $user->save();

        $roleName = (int) ($row['role_id'] ?? 2) === 1
            ? 'admin'
            : ((int) ($row['is_instructor'] ?? 0) === 1 ? 'instructor_independent' : 'student');

        $guard = (string) config('permissions.guard', 'web');
        if (Role::query()->where('name', $roleName)->where('guard_name', $guard)->exists() && ! $user->hasRole($roleName, $guard)) {
            $user->assignRole($roleName);
        }

        $metadata = ['role' => $roleName, 'is_instructor' => (int) ($row['is_instructor'] ?? 0) === 1];

        if ((int) ($row['is_instructor'] ?? 0) === 1) {
            $profile = InstructorProfile::query()->where('user_id', $user->getKey())->first() ?? new InstructorProfile(['user_id' => $user->getKey()]);
            if (! $profile->exists) {
                $profile->forceFill([
                    'slug_ar' => ScriptNameMapper::uniqueSlug('instructor_profiles', 'slug_ar', $fullName, 'instructor-'.$sourceId),
                    'slug_en' => ScriptNameMapper::uniqueSlug('instructor_profiles', 'slug_en', $fullName, 'instructor-'.$sourceId),
                ]);
            }

            $headlineText = trim((string) ($row['title'] ?? ''));
            $bioText = trim((string) ($row['biography'] ?? ''));
            $profile->forceFill([
                'display_name' => ScriptNameMapper::bilingual($fullName, $context->defaultLocale),
                'headline' => $headlineText !== '' ? ScriptNameMapper::bilingual($headlineText, $context->defaultLocale) : null,
                'bio' => $bioText !== '' ? ScriptNameMapper::bilingual(strip_tags($bioText), $context->defaultLocale) : null,
                'avatar' => filled($row['image'] ?? null) ? trim((string) $row['image']) : null,
                'status' => $status === 'active' ? 'approved' : 'draft',
                'approved_at' => $status === 'active' ? ($profile->approved_at ?? now()) : null,
            ])->save();

            $metadata['instructor_profile_id'] = (int) $profile->getKey();
        }

        return $isNew
            ? RowImportResult::imported(User::class, (int) $user->getKey(), $metadata, $exceptions)
            : RowImportResult::updated(User::class, (int) $user->getKey(), $metadata, $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return User::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $user = User::query()->find($targetId);
        if ($user === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedEmail = strtolower(trim((string) ($sourceRow['email'] ?? '')));
        if ((string) $user->email !== $expectedEmail) {
            $mismatches[] = "email expected '{$expectedEmail}', got '{$user->email}'";
        }

        $expectedStatus = (int) ($sourceRow['status'] ?? 1) === 1 ? 'active' : 'suspended';
        if ((string) $user->getAttribute('status') !== $expectedStatus) {
            $mismatches[] = "status expected '{$expectedStatus}', got '{$user->getAttribute('status')}'";
        }

        return $mismatches;
    }
}
