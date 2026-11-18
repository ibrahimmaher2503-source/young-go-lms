<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

/**
 * One dashboard tile or queue row. `kind` decides how the value reads: count, percent, or money (`money` = minor units per
 * currency, never added across currencies). The label is the lang key `dashboard.kpi.<key>`; `secondary` is an optional second
 * count (for a queue: how many are overdue, lang `dashboard.kpi.<key>_secondary`).
 */
final readonly class DashboardKpi
{
    public const string COUNT = 'count';

    public const string PERCENT = 'percent';

    public const string MONEY = 'money';

    /**
     * @param  array<string, int>  $money  currency => minor units (money kind only)
     */
    public function __construct(
        public string $key,
        public string $kind,
        public int|float $value = 0,
        public array $money = [],
        public ?int $secondary = null,
        public string $icon = 'heroicon-o-chart-bar',
        public string $tone = 'primary',
    ) {}

    public static function count(string $key, int $value, string $icon, string $tone = 'primary', ?int $secondary = null): self
    {
        return new self($key, self::COUNT, $value, [], $secondary, $icon, $tone);
    }

    public static function percent(string $key, float $value, string $icon, string $tone = 'primary'): self
    {
        return new self($key, self::PERCENT, $value, [], null, $icon, $tone);
    }

    /** @param  array<string, int>  $money */
    public static function money(string $key, array $money, string $icon, string $tone = 'primary', ?int $secondary = null): self
    {
        ksort($money);

        return new self($key, self::MONEY, array_sum($money), $money, $secondary, $icon, $tone);
    }

    /**
     * Plain-array form for the cache: object payloads come back as `__PHP_Incomplete_Class` when `cache.serializable_classes` is false.
     *
     * @return array{key: string, kind: string, value: int|float, money: array<string, int>, secondary: int|null, icon: string, tone: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'kind' => $this->kind, 'value' => $this->value, 'money' => $this->money, 'secondary' => $this->secondary, 'icon' => $this->icon, 'tone' => $this->tone];
    }

    /** Rebuild from `toArray()`; null when the payload is not the expected shape (treated as a cache miss by the caller). */
    public static function fromArray(mixed $row): ?self
    {
        if (! is_array($row)) {
            return null;
        }
        $money = $row['money'] ?? null;
        if (! isset($row['key'], $row['kind'], $row['icon'], $row['tone']) || ! is_string($row['key']) || ! is_string($row['kind']) || ! is_string($row['icon']) || ! is_string($row['tone'])
            || ! (is_int($row['value'] ?? null) || is_float($row['value'] ?? null)) || ! is_array($money) || ! (($row['secondary'] ?? null) === null || is_int($row['secondary']))) {
            return null;
        }
        $clean = [];
        foreach ($money as $currency => $minor) {
            if (! is_string($currency) || ! is_int($minor)) {
                return null;
            }
            $clean[$currency] = $minor;
        }

        return new self($row['key'], $row['kind'], $row['value'], $clean, $row['secondary'] ?? null, $row['icon'], $row['tone']);
    }

    /** True when there is nothing to show yet (no value in any currency). */
    public function isEmpty(): bool
    {
        return $this->kind === self::MONEY ? array_filter($this->money) === [] : $this->value === 0 || $this->value === 0.0;
    }
}
