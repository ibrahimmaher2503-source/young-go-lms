<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use App\Domain\Settings\AiSettings;

/** Effective retention per class: the AI class reads the AI settings, every other class reads its override row or the default. */
final class RetentionPolicies
{
    public function __construct(private readonly AiSettings $ai) {}

    public function days(RetentionClass $class): ?int
    {
        if ($class === RetentionClass::AiMessages) {
            return $this->ai->message_retention_days;
        }

        $row = RetentionPolicy::query()->where('data_class', $class->value)->first();

        return $row instanceof RetentionPolicy && $row->overridden ? $row->retention_days : $class->defaultDays();
    }

    /** @return array<string, array{class: RetentionClass, days: int|null, default: int|null, last_pruned_at: string|null, last_pruned_count: int}> */
    public function overview(): array
    {
        $rows = RetentionPolicy::query()->get()->keyBy('data_class');
        $out = [];
        foreach (RetentionClass::cases() as $class) {
            $row = $rows->get($class->value);
            $out[$class->value] = [
                'class' => $class,
                'days' => $this->days($class),
                'default' => $class->defaultDays(),
                'last_pruned_at' => $row instanceof RetentionPolicy ? $row->last_pruned_at?->toDateTimeString() : null,
                'last_pruned_count' => $row instanceof RetentionPolicy ? $row->last_pruned_count : 0,
            ];
        }

        return $out;
    }
}
