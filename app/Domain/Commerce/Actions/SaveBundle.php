<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Bundle;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates or edits a bundle for staff with `payment.approve_manual` (finance, admin). A bundle has an Arabic and an
 * English name, 2 to 20 distinct courses, a price per market in minor units and, optionally, an allocation weight per
 * course (all courses or none). An active bundle needs a price in an active market. Orders keep their own snapshot and
 * allocation, so editing never changes a past order. Every save is audited.
 */
final class SaveBundle
{
    private const int MAX_COURSES = 20;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool, prices: array<string, int>, items: list<array{course_id: int, weight?: int|null}>}  $data
     */
    public function __invoke(User $actor, array $data, ?Bundle $bundle = null): Bundle
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
        $items = $this->items($data['items']);
        $prices = $this->prices($data['prices']);
        $active = (bool) ($data['is_active'] ?? false);
        if ($active && ! array_key_exists(Market::Egypt->value, $prices)) {
            $this->refuse('prices', 'price_required');
        }

        return DB::transaction(function () use ($actor, $bundle, $name, $items, $prices, $active): Bundle {
            $current = $bundle === null ? null : Bundle::query()->lockForUpdate()->findOrFail($bundle->getKey());
            $before = $current === null ? null : $this->snapshot($current);

            $saved = $current ?? new Bundle;
            $saved->setTranslations('name', $name);
            $saved->is_active = $active;
            if ($current === null) {
                $saved->created_by = $actor->getKey();
            }
            $saved->save();

            $saved->items()->delete();
            foreach ($items as $position => $item) {
                $saved->items()->create(['course_id' => $item['course_id'], 'allocation_weight' => $item['weight'], 'position' => $position]);
            }
            $saved->prices()->delete();
            foreach ($prices as $market => $minor) {
                $saved->prices()->create(['market' => $market, 'currency' => Market::from($market)->currency(), 'amount_minor' => $minor]);
            }

            $recorded = $this->audit->record($current === null ? 'bundle.created' : 'bundle.updated', $saved, (int) $actor->getKey(), $before, $this->snapshot($saved));
            if ($recorded === null) {
                throw new RuntimeException('Unable to record bundle audit.');
            }

            return $saved;
        });
    }

    /**
     * @param  list<array{course_id: int, weight?: int|null}>  $rows
     * @return list<array{course_id: int, weight: int|null}>
     */
    private function items(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $weight = $row['weight'] ?? null;
            if ($weight !== null && ($weight < 1 || $weight > 1_000_000)) {
                $this->refuse('items', 'weight_invalid');
            }
            $items[] = ['course_id' => (int) $row['course_id'], 'weight' => $weight === null ? null : (int) $weight];
        }

        $ids = array_column($items, 'course_id');
        if (count($ids) < 2 || count($ids) > self::MAX_COURSES || count(array_unique($ids)) !== count($ids)) {
            $this->refuse('items', 'courses_invalid');
        }
        if (Course::query()->whereIn('id', $ids)->count() !== count($ids)) {
            $this->refuse('items', 'courses_missing');
        }
        $weighted = count(array_filter($items, static fn (array $i): bool => $i['weight'] !== null));
        if ($weighted !== 0 && $weighted !== count($items)) {
            $this->refuse('items', 'weights_mixed');
        }

        return $items;
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
    private function snapshot(Bundle $bundle): array
    {
        return [
            'name' => $bundle->getTranslations('name'),
            'is_active' => $bundle->is_active,
            'prices' => $bundle->prices()->pluck('amount_minor', 'market')->all(),
            'items' => $bundle->items()->get(['course_id', 'allocation_weight'])->map(static fn ($i): array => ['course_id' => $i->course_id, 'weight' => $i->allocation_weight])->all(),
        ];
    }

    private function refuse(string $field, string $reason): never
    {
        throw ValidationException::withMessages([$field => __('bundles.'.$reason)]);
    }
}
