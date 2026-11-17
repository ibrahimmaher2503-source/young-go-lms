<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;

/** Scheduled sweep: marks active entitlements past their end as expired. Access is already denied at the end time. */
final class ExpireEntitlements
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $service) {}

    public function __invoke(): int
    {
        $count = 0;
        Entitlement::query()->where('status', Entitlement::ACTIVE)->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->chunkById(200, function ($rows) use (&$count): void {
                foreach ($rows as $entitlement) {
                    /** @var Entitlement $entitlement */
                    $entitlement->forceFill(['status' => Entitlement::EXPIRED])->save();
                    $this->audit->record('entitlement.expired', $entitlement->course, null, null, ['learner_id' => $entitlement->learner_id, 'source' => $entitlement->source_type->value]);
                    $count++;
                }
            });

        $this->service->flush();

        return $count;
    }
}
