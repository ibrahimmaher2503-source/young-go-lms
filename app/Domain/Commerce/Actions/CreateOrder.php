<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderItemAllocation;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates a pending order priced entirely on the server (PRD 17.3). Callers pass products and learners, never amounts.
 * The payer is the signed-in account; a parent may buy for a verified child (YG-GAP-FAM-005). Only active markets sell
 * (Saudi Arabia is off). The same payer and idempotency key return the same order; a different request under that key
 * is refused. Courses, bundles (a bundle line stores its per-course allocation) and subscription plans (one period per line, T-P8-08) are sellable.
 */
final class CreateOrder
{
    private const int MAX_LINES = 20;

    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $entitlements, private readonly AllocateBundlePrice $allocate) {}

    /** @param  list<OrderLine>  $lines */
    public function __invoke(User $payer, Market $market, array $lines, string $idempotencyKey): Order
    {
        if (preg_match('/^[A-Za-z0-9_\-:.]{8,64}$/', $idempotencyKey) !== 1) {
            $this->refuse('idempotency_key_invalid');
        }
        if ($payer->getAttribute('status') !== 'active') {
            $this->refuse('payer_inactive');
        }
        if (! $market->isActive()) {
            $this->refuse('market_unavailable');
        }
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            $this->refuse('lines_invalid');
        }

        $hash = $this->fingerprint($market, $lines);
        $existing = $this->existing($payer, $idempotencyKey, $hash);
        if ($existing !== null) {
            return $existing;
        }

        $priced = $this->price($payer, $market, $lines);

        try {
            return DB::transaction(function () use ($payer, $market, $priced, $idempotencyKey, $hash): Order {
                $subtotal = array_sum(array_column($priced, 'price'));
                $order = Order::query()->create([
                    'reference' => 'YG-ORD-'.Str::upper(Str::random(10)),
                    'payer_id' => $payer->getKey(),
                    'market' => $market->value,
                    'currency' => $market->currency(),
                    'subtotal_minor' => $subtotal,
                    'discount_minor' => 0,
                    'total_minor' => $subtotal,
                    'status' => OrderStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $hash,
                ]);

                foreach ($priced as $row) {
                    $field = $row['type'] === ProductType::Course ? 'title' : 'name';
                    $item = OrderItem::query()->create([
                        'order_id' => $order->getKey(),
                        'product_type' => $row['type'],
                        'product_id' => $row['product']->getKey(),
                        'learner_id' => $row['learner']->getKey(),
                        'unit_price_minor' => $row['price'],
                        'currency' => $market->currency(),
                        'allocated_minor' => $row['price'],
                        'title_ar' => $row['product']->getTranslation($field, 'ar', false) ?: null,
                        'title_en' => $row['product']->getTranslation($field, 'en', false) ?: null,
                    ]);
                    foreach ($row['allocations'] as $courseId => $share) {
                        OrderItemAllocation::query()->create([
                            'order_item_id' => $item->getKey(), 'course_id' => $courseId,
                            'weight' => $share['weight'], 'allocated_minor' => $share['allocated_minor'],
                        ]);
                    }
                }

                $recorded = $this->audit->record('order.created', $order, (int) $payer->getKey(), null, [
                    'reference' => $order->reference, 'market' => $market->value, 'currency' => $market->currency(),
                    'total_minor' => $subtotal, 'lines' => count($priced),
                ]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record order audit.');
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            $existing = $this->existing($payer, $idempotencyKey, $hash);
            if ($existing === null) {
                throw new RuntimeException('Order could not be created.');
            }

            return $existing;
        }
    }

    /**
     * @param  list<OrderLine>  $lines
     * @return list<array{type: ProductType, product: Course|Bundle|SubscriptionPlan, learner: User, price: int, allocations: array<int, array{weight: int, allocated_minor: int}>}>
     */
    private function price(User $payer, Market $market, array $lines): array
    {
        $seen = [];
        $priced = [];

        foreach ($lines as $line) {
            if (! $line->type->isSellable()) {
                $this->refuse('product_unavailable');
            }
            $learner = $line->learner;
            $isSelf = $learner->getKey() === $payer->getKey();
            if (! $isSelf && ! GuardianAccess::parentCanAccessChild($payer, $learner)->allowed()) {
                $this->refuse('learner_not_allowed');
            }
            if ($learner->getAttribute('status') !== 'active') {
                $this->refuse('learner_inactive');
            }

            $key = $line->type->value.':'.$line->productId.':'.$learner->getKey();
            if (isset($seen[$key])) {
                $this->refuse('duplicate_line');
            }
            $seen[$key] = true;

            if ($line->type === ProductType::Bundle) {
                $priced[] = $this->priceBundle($market, $line, $learner);

                continue;
            }
            if ($line->type === ProductType::Plan) {
                $priced[] = $this->pricePlan($market, $line, $learner);

                continue;
            }

            $course = Course::query()->find($line->productId);
            if (! $course instanceof Course || $course->status !== CourseStatus::Published || ! in_array($course->access_mode, Course::INDIVIDUAL_ACCESS_MODES, true)) {
                $this->refuse('product_unavailable');
            }
            $price = CoursePrice::query()->where('course_id', $course->getKey())->where('market', $market->value)->first();
            if (! $price instanceof CoursePrice || ! $price->forMarket($market) || $price->amount_minor <= 0) {
                $this->refuse('price_unavailable');
            }
            if ($this->entitlements->currentFor($learner, $course) !== null) {
                $this->refuse('already_enrolled');
            }

            $priced[] = ['type' => ProductType::Course, 'product' => $course, 'learner' => $learner, 'price' => $price->amount_minor, 'allocations' => []];
        }

        return $priced;
    }

    /** @return array{type: ProductType, product: Bundle, learner: User, price: int, allocations: array<int, array{weight: int, allocated_minor: int}>} */
    private function priceBundle(Market $market, OrderLine $line, User $learner): array
    {
        $bundle = Bundle::query()->with(['prices', 'items.course'])->find($line->productId);
        if (! $bundle instanceof Bundle || ! $bundle->is_active || $bundle->items->isEmpty()
            || $bundle->items->contains(static fn ($i): bool => $i->course?->status !== CourseStatus::Published)) {
            $this->refuse('product_unavailable');
        }
        $price = $bundle->priceFor($market);
        if ($price === null || $price->amount_minor <= 0) {
            $this->refuse('price_unavailable');
        }
        // Courses the learner already has are allowed (recorded and skipped at grant); a bundle with nothing new is refused.
        $owned = $bundle->items->filter(fn ($i): bool => $i->course !== null && $this->entitlements->currentFor($learner, $i->course) !== null);
        if ($owned->count() === $bundle->items->count()) {
            $this->refuse('already_enrolled');
        }

        return [
            'type' => ProductType::Bundle, 'product' => $bundle, 'learner' => $learner, 'price' => $price->amount_minor,
            'allocations' => ($this->allocate)($bundle, $market, $price->amount_minor),
        ];
    }

    /**
     * A plan line is a first purchase or a renewal. A renewal is accepted once the paid period ends within the renewal
     * window (or has ended); an earlier one is refused so nobody pays twice for the same days (no proration, R-017).
     *
     * @return array{type: ProductType, product: SubscriptionPlan, learner: User, price: int, allocations: array<int, array{weight: int, allocated_minor: int}>}
     */
    private function pricePlan(Market $market, OrderLine $line, User $learner): array
    {
        $plan = SubscriptionPlan::query()->with(['prices', 'courses.course'])->find($line->productId);
        if (! $plan instanceof SubscriptionPlan || ! $plan->is_active || $plan->courses->isEmpty()
            || ! $plan->courses->contains(static fn ($c): bool => $c->course?->status === CourseStatus::Published)) {
            $this->refuse('product_unavailable');
        }
        $price = $plan->priceFor($market);
        if ($price === null || $price->amount_minor <= 0) {
            $this->refuse('price_unavailable');
        }
        $subscription = Subscription::query()->where(['plan_id' => $plan->getKey(), 'learner_id' => $learner->getKey()])->first();
        $latest = $subscription?->latestPeriod();
        if ($latest !== null && $latest->ends_at->gt(now()->addDays((int) config('subscriptions.renewal_window_days', 7)))) {
            $this->refuse('already_subscribed');
        }

        return ['type' => ProductType::Plan, 'product' => $plan, 'learner' => $learner, 'price' => $price->amount_minor, 'allocations' => []];
    }

    private function existing(User $payer, string $key, string $hash): ?Order
    {
        $order = Order::query()->where('payer_id', $payer->getKey())->where('idempotency_key', $key)->first();
        if ($order === null) {
            return null;
        }
        if (! hash_equals($order->request_hash, $hash)) {
            $this->refuse('idempotency_conflict');
        }

        return $order;
    }

    /** @param  list<OrderLine>  $lines */
    private function fingerprint(Market $market, array $lines): string
    {
        $parts = array_map(static fn (OrderLine $l): string => $l->type->value.':'.$l->productId.':'.$l->learner->getKey(), $lines);
        sort($parts);

        return hash('sha256', $market->value.'|'.implode(',', $parts));
    }

    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages(['order' => __('commerce.'.$reason)]);
    }
}
