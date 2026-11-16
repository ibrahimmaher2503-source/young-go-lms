<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

final class AuditLogger
{
    public const string LOG_NAME = 'audit';

    private const string REDACTED = '[REDACTED]';

    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     * @param  array<array-key, mixed>  $context
     */
    public function record(
        string $event,
        Model $subject,
        ?int $actorId,
        ?array $before = null,
        ?array $after = null,
        array $context = [],
    ): ?ActivityContract {
        $activity = activity(self::LOG_NAME)
            ->event($event)
            ->performedOn($subject)
            ->withProperties([
                'before' => $this->redact($before),
                'after' => $this->redact($after),
                'context' => $this->redact($context),
            ]);

        if ($actorId === null) {
            $activity->causedByAnonymous();
        } else {
            $activity->causedBy($actorId);
        }

        return $activity->log($event);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value)
                ? $this->redact($value)
                : (is_scalar($value) || $value === null ? $value : self::REDACTED);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = preg_replace('/[^a-z0-9]/', '', strtolower($key));

        if ($normalizedKey === null) {
            return false;
        }

        if (in_array($normalizedKey, ['account', 'card', 'iddoc', 'idphoto'], true)) {
            return true;
        }

        return preg_match(
            '/password|passphrase|token|secret|credential|authorization|apikey|privatekey|payout|bank|iban|swift|routing|account(?:number|no)|cardnumber|creditcard|cvv|cvc|securitycode|national(?:id|document)|governmentid|identity(?:document|number|card)|iddocument|idnumber|idcard|idfront|idback|idimage|idscan|passport|ssn|taxid/',
            $normalizedKey,
        ) === 1;
    }
}
