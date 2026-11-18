<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\RetentionClass;
use App\Domain\Privacy\RetentionPolicies;
use App\Domain\Privacy\RetentionPolicy;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves retention days per class (O-025 is OPEN: values are configurable, defaults are conservative). A blank value keeps the
 * data. The AI class is owned by the AI settings page and is ignored here. Non-export classes cannot go below the configured
 * minimum; export archives need 1..365 days. Financial, certificate and audit data has no class and cannot be configured.
 */
final class SaveRetentionPolicies
{
    public function __construct(private readonly AuditLogger $audit, private readonly RetentionPolicies $policies) {}

    /** @param  array<string, int|string|null>  $days  class value => days (null/blank = keep) */
    public function __invoke(User $actor, array $days): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('privacy.manage')) {
            throw new AuthorizationException;
        }

        $min = Config::integer('privacy.min_retention_days');
        $clean = [];
        foreach ($days as $key => $value) {
            $class = RetentionClass::tryFrom((string) $key);
            if ($class === null) {
                throw ValidationException::withMessages(['retention' => __('privacy.error_unknown_class')]);
            }
            if ($class->isManagedElsewhere()) {
                continue;
            }
            $number = $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_INT);
            if ($number === false) {
                throw ValidationException::withMessages([$class->value => __('privacy.error_days_invalid')]);
            }
            if ($class === RetentionClass::DataExports) {
                if ($number === null || $number < 1 || $number > 365) {
                    throw ValidationException::withMessages([$class->value => __('privacy.error_export_days')]);
                }
            } elseif ($number !== null && ($number < $min || $number > 3650)) {
                throw ValidationException::withMessages([$class->value => __('privacy.error_days_range', ['min' => $min, 'max' => 3650])]);
            }
            $clean[$class->value] = $number;
        }

        DB::transaction(function () use ($actor, $clean): void {
            foreach ($clean as $key => $number) {
                $class = RetentionClass::from($key);
                $before = $this->policies->days($class);
                if ($before === $number) {
                    continue;
                }
                $row = RetentionPolicy::query()->updateOrCreate(['data_class' => $key], ['overridden' => true, 'retention_days' => $number, 'updated_by' => $actor->getKey()]);
                if ($this->audit->record('privacy.retention_updated', $row, (int) $actor->getKey(), ['data_class' => $key, 'retention_days' => $before], ['data_class' => $key, 'retention_days' => $number]) === null) {
                    throw new RuntimeException('Unable to record retention audit.');
                }
            }
        });
    }
}
