<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Shared\Markets\Market;
use InvalidArgumentException;

/**
 * Splits a bundle price across its member courses in proportion to their weights (largest remainder, so the shares add
 * up to the price exactly; ties go to the lower course id). The weight of a course is the admin-set allocation weight;
 * when no member has one it is the course list price in the market (1 if it has none). The final accounting formula is
 * still open (O-009), so this is the documented default. Pure: it writes nothing.
 */
final class AllocateBundlePrice
{
    /**
     * @return array<int, array{weight: int, allocated_minor: int}> keyed by course id, in bundle order
     */
    public function __invoke(Bundle $bundle, Market $market, int $totalMinor): array
    {
        $items = $bundle->items()->get();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('A bundle needs at least one course.');
        }

        $explicit = $items->every(static fn ($i): bool => $i->allocation_weight !== null);
        $prices = $explicit ? [] : CoursePrice::query()->whereIn('course_id', $items->pluck('course_id'))
            ->where('market', $market->value)->pluck('amount_minor', 'course_id')->all();

        $weights = [];
        foreach ($items as $item) {
            $weights[$item->course_id] = $explicit ? (int) $item->allocation_weight : max(1, (int) ($prices[$item->course_id] ?? 1));
        }

        $out = [];
        foreach ($this->split($totalMinor, $weights) as $courseId => $share) {
            $out[$courseId] = ['weight' => $weights[$courseId], 'allocated_minor' => $share];
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $weights  positive weights keyed by id
     * @return array<int, int> shares keyed by id, summing to $totalMinor, in the order of $weights
     */
    public function split(int $totalMinor, array $weights): array
    {
        $sum = array_sum($weights);
        if ($totalMinor < 0 || $weights === [] || $sum <= 0 || min($weights) < 1) {
            throw new InvalidArgumentException('Allocation needs a non-negative total and positive weights.');
        }

        $shares = [];
        $remainders = [];
        $given = 0;
        foreach ($weights as $id => $weight) {
            $product = $totalMinor * $weight;
            $shares[$id] = intdiv($product, $sum);
            $remainders[$id] = $product % $sum;
            $given += $shares[$id];
        }

        $ids = array_keys($weights);
        usort($ids, static fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        for ($left = $totalMinor - $given, $i = 0; $left > 0; $left--, $i++) {
            $shares[$ids[$i % count($ids)]]++;
        }

        return $shares;
    }
}
