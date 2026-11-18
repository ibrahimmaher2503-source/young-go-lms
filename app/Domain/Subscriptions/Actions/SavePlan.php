<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\PlanTerm;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates or edits a subscription plan for staff with `payment.approve_manual` (finance, admin). A plan has an Arabic and
 * an English name, a term (monthly or annual), a price per market in minor units and 1 to 100 included courses. Courses
 * are chosen directly and/or through categories (a category is expanded to its current subscription courses when the plan
 * is saved). Only courses sold by subscription (`subscription_only` or `both`) can be included. An active plan needs a price
 * in Egypt. Editing a plan never changes a paid period: periods and their entitlements keep what was granted. Every save is audited.
 */
final class SavePlan
{
    private const int MAX_COURSES = 100;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name_ar: string, name_en: string, term: string, is_active?: bool, prices: array<string, int>, course_ids?: list<int>, category_ids?: list<int>}  $data
     */
    public function __invoke(User $actor, array $data, ?SubscriptionPlan $plan = null): SubscriptionPlan
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual')) {
            throw new AuthorizationException;
        }

        $name = ['ar' => trim($data['name_ar']), 'en' => trim($data['name_en'])];
        foreach ($name as $text) {
            if (mb_strlen($text) < 2 || mb_strlen($text) > 120) {
                $this->refuse('name', 'name_invalid');
            }
        }
        $term = PlanTerm::tryFrom($data['term']) ?? $this->refuse('term', 'term_invalid');
        $courseIds = $this->courseIds($data['course_ids'] ?? [], $data['category_ids'] ?? []);
        $prices = $this->prices($data['prices']);
        $active = (bool) ($data['is_active'] ?? false);
        if ($active && ! array_key_exists(Market::Egypt->value, $prices)) {
            $this->refuse('prices', 'price_required');
        }

        return DB::transaction(function () use ($actor, $plan, $name, $term, $courseIds, $prices, $active): SubscriptionPlan {
            $current = $plan === null ? null : SubscriptionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $before = $current === null ? null : $this->snapshot($current);

            $saved = $current ?? new SubscriptionPlan;
            $saved->setTranslations('name', $name);
            $saved->term = $term;
            $saved->is_active = $active;
            if ($current === null) {
                $saved->created_by = $actor->getKey();
                $saved->grace_days = max(0, (int) config('subscriptions.grace_days', 3));
            }
            $saved->save();

            $saved->courses()->delete();
            foreach ($courseIds as $position => $courseId) {
                $saved->courses()->create(['course_id' => $courseId, 'position' => $position]);
            }
            $saved->prices()->delete();
            foreach ($prices as $market => $minor) {
                $saved->prices()->create(['market' => $market, 'currency' => Market::from($market)->currency(), 'amount_minor' => $minor]);
            }

            $recorded = $this->audit->record($current === null ? 'plan.created' : 'plan.updated', $saved, (int) $actor->getKey(), $before, $this->snapshot($saved));
            if ($recorded === null) {
                throw new RuntimeException('Unable to record plan audit.');
            }

            return $saved;
        });
    }

    /**
     * @param  list<int>  $courseIds
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function courseIds(array $courseIds, array $categoryIds): array
    {
        $direct = array_values(array_unique(array_map('intval', $courseIds)));
        if ($direct !== [] && Course::query()->whereIn('id', $direct)->whereIn('access_mode', [Course::ACCESS_SUBSCRIPTION, Course::ACCESS_BOTH])->count() !== count($direct)) {
            $this->refuse('courses', 'courses_not_subscribable');
        }

        $fromCategories = $categoryIds === [] ? [] : Course::query()->whereIn('category_id', array_map('intval', $categoryIds))
            ->whereIn('access_mode', [Course::ACCESS_SUBSCRIPTION, Course::ACCESS_BOTH])->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $ids = array_values(array_unique([...$direct, ...$fromCategories]));
        if ($ids === [] || count($ids) > self::MAX_COURSES) {
            $this->refuse('courses', 'courses_invalid');
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $prices
     * @return array<string, int>
     */
    private function prices(array $prices): array
    {
        $clean = [];
        foreach ($prices as $market => $minor) {
            if (Market::tryFrom((string) $market) === null || $minor < 1) {
                $this->refuse('prices', 'price_invalid');
            }
            $clean[(string) $market] = (int) $minor;
        }

        return $clean;
    }

    /** @return array<string, mixed> */
    private function snapshot(SubscriptionPlan $plan): array
    {
        return [
            'name' => $plan->getTranslations('name'),
            'term' => $plan->term->value,
            'is_active' => $plan->is_active,
            'prices' => $plan->prices()->pluck('amount_minor', 'market')->all(),
            'course_ids' => $plan->courses()->pluck('course_id')->all(),
        ];
    }

    private function refuse(string $field, string $reason): never
    {
        throw ValidationException::withMessages([$field => __('subscriptions.'.$reason)]);
    }
}
