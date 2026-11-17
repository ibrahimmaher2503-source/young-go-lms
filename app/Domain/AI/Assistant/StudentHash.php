<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** Opaque per-student identity for the provider and usage rows (AI spec 8.1): stable, not reversible without the app key. */
final class StudentHash
{
    public static function of(int $studentId): string
    {
        return hash_hmac('sha256', 'ai-student:'.$studentId, (string) config('app.key'));
    }
}
