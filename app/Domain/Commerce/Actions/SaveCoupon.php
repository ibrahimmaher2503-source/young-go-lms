<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponType;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates or edits a coupon for staff with `payment.approve_manual` (finance, admin). Once a coupon has been used its
 * code, type and value are frozen and the total cap cannot drop below the uses so far; the dates, caps, course list and
 * the on/off switch stay editable. Every save is audited.
 */
final class SaveCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{code: string, name: string, discount_type: string, percent?: int|null, amount_minor?: int|null, currency?: string|null, starts_at?: CarbonInterface|null, ends_at?: CarbonInterface|null, max_redemptions?: int|null, max_per_user?: int|null, is_active?: bool, course_ids?: list<int>}  $data
     */
    public function __invoke(User $actor, array $data, ?Coupon $coupon = null): Coupon
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual')) {
            throw new AuthorizationException;
        }

        $values = $this->clean($data);

        return DB::transaction(function () use ($actor, $values, $coupon): Coupon {
            $current = $coupon === null ? null : Coupon::query()->lockForUpdate()->findOrFail($coupon->getKey());
            $codeTaken = Coupon::query()->where('code', $values['code'])->when($current !== null, static fn ($q) => $q->whereKeyNot($current?->getKey()))->exists();
            if ($codeTaken) {
                $this->refuse('code', 'coupon_code_taken');
            }

            if ($current !== null && $current->redemptions_count > 0 || $current !== null && $current->redemptions()->exists()) {
                foreach (['code', 'discount_type', 'percent', 'amount_minor', 'currency'] as $frozen) {
                    $new = $values[$frozen];
                    $old = $current->getAttribute($frozen);
                    if (($new instanceof CouponType ? $new->value : $new) !== ($old instanceof CouponType ? $old->value : $old)) {
                        $this->refuse('code', 'coupon_frozen');
                    }
                }
            }
            if ($current !== null && $values['max_redemptions'] !== null && $values['max_redemptions'] < $current->redemptions_count) {
                $this->refuse('max_redemptions', 'coupon_cap_below_used');
            }

            $courseIds = $values['course_ids'];
            unset($values['course_ids']);
            if ($courseIds !== [] && Course::query()->whereIn('id', $courseIds)->count() !== count($courseIds)) {
                $this->refuse('course_ids', 'coupon_courses_invalid');
            }

            $before = $current?->only(['code', 'discount_type', 'percent', 'amount_minor', 'currency', 'starts_at', 'ends_at', 'max_redemptions', 'max_per_user', 'is_active']);
            $saved = $current ?? new Coupon;
            $saved->fill($values);
            if ($current === null) {
                $saved->created_by = $actor->getKey();
            }
            $saved->save();
            $saved->courses()->sync($courseIds);

            $recorded = $this->audit->record($current === null ? 'coupon.created' : 'coupon.updated', $saved, (int) $actor->getKey(), $before, [
                ...$saved->only(['code', 'discount_type', 'percent', 'amount_minor', 'currency', 'starts_at', 'ends_at', 'max_redemptions', 'max_per_user', 'is_active']),
                'course_ids' => $courseIds,
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record coupon audit.');
            }

            return $saved;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{code: string, name: string, discount_type: CouponType, percent: int|null, amount_minor: int|null, currency: string|null, starts_at: CarbonInterface|null, ends_at: CarbonInterface|null, max_redemptions: int|null, max_per_user: int|null, is_active: bool, course_ids: list<int>}
     */
    private function clean(array $data): array
    {
        $code = Coupon::normalizeCode((string) $data['code']);
        if (preg_match('/^[A-Z0-9_-]{3,32}$/', $code) !== 1) {
            $this->refuse('code', 'coupon_code_format');
        }
        $name = trim((string) $data['name']);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $this->refuse('name', 'coupon_name_invalid');
        }
        $type = CouponType::tryFrom((string) $data['discount_type']);
        if ($type === null) {
            $this->refuse('discount_type', 'coupon_value_invalid');
        }

        $percent = null;
        $amount = null;
        $currency = null;
        if ($type === CouponType::Percent) {
            $percent = (int) ($data['percent'] ?? 0);
            if ($percent < 1 || $percent > 100) {
                $this->refuse('percent', 'coupon_value_invalid');
            }
        } else {
            $amount = (int) ($data['amount_minor'] ?? 0);
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            $currencies = array_map(static fn (Market $m): string => $m->currency(), Market::cases());
            if ($amount < 1 || ! in_array($currency, $currencies, true)) {
                $this->refuse('amount_minor', 'coupon_value_invalid');
            }
        }

        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;
        if ($starts instanceof CarbonInterface && $ends instanceof CarbonInterface && ! $ends->greaterThan($starts)) {
            $this->refuse('ends_at', 'coupon_dates_invalid');
        }
        $max = $data['max_redemptions'] ?? null;
        $perUser = $data['max_per_user'] ?? null;
        if (($max !== null && (int) $max < 1) || ($perUser !== null && (int) $perUser < 1)) {
            $this->refuse('max_redemptions', 'coupon_caps_invalid');
        }

        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));

        return [
            'code' => $code, 'name' => $name, 'discount_type' => $type, 'percent' => $percent, 'amount_minor' => $amount, 'currency' => $currency,
            'starts_at' => $starts instanceof CarbonInterface ? $starts : null, 'ends_at' => $ends instanceof CarbonInterface ? $ends : null,
            'max_redemptions' => $max === null ? null : (int) $max, 'max_per_user' => $perUser === null ? null : (int) $perUser,
            'is_active' => (bool) ($data['is_active'] ?? true), 'course_ids' => $courseIds,
        ];
    }

    private function refuse(string $field, string $reason): never
    {
        throw ValidationException::withMessages([$field => __('commerce.'.$reason)]);
    }
}
