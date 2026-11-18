<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Shared\Markets\Market;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * The filters of one report run. `from` and `to` are stored as UTC instants (the report range is whole market-local days).
 * `courseId` and `instructorId` are what the caller asked for; `courseIds` is what the caller may read after `ReportCatalog::scope`
 * (null = every course, empty list = none). Query classes read `courseIds` only, so a hand-built request can never widen it.
 */
final class ReportFilters
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    /**
     * @param  list<int>|null  $courseIds
     */
    public function __construct(
        CarbonImmutable $from,
        CarbonImmutable $to,
        public readonly ?int $courseId = null,
        public readonly ?int $instructorId = null,
        public readonly ?string $market = null,
        public readonly ?array $courseIds = null,
    ) {
        $this->from = $from->utc();
        $this->to = $to->utc();
    }

    /**
     * Reads the raw request values. An invalid or reversed range falls back to the last 30 days or is swapped, a range is capped
     * (config `reports.max_range_days`) and an unknown market is dropped.
     *
     * @param  array<array-key, mixed>  $input  keys from, to (Y-m-d), course, instructor, market
     */
    public static function fromInput(array $input): self
    {
        $market = self::market($input['market'] ?? null);
        $zone = ($market === null ? Market::Egypt : Market::from($market))->timezone();
        $max = Config::integer('reports.max_range_days');

        try {
            $from = CarbonImmutable::parse(self::text($input['from'] ?? null), $zone)->startOfDay();
            $to = CarbonImmutable::parse(self::text($input['to'] ?? null), $zone)->endOfDay();
        } catch (Throwable) {
            $to = CarbonImmutable::now($zone)->endOfDay();
            $from = $to->subDays(29)->startOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        if ((int) $from->startOfDay()->diffInDays($to->startOfDay(), true) + 1 > $max) {
            $from = $to->startOfDay()->subDays($max - 1);
        }

        return new self($from, $to, self::id($input['course'] ?? null), self::id($input['instructor'] ?? null), $market);
    }

    /** The market-local calendar day "today" (Egypt unless a market is set), as Y-m-d: the default "To" of the report range. */
    public static function defaultTo(?string $market = null): string
    {
        return CarbonImmutable::now(self::zoneFor(self::market($market)))->toDateString();
    }

    /** The default "From": the first of the last market-local days, ending today (inclusive). */
    public static function defaultFrom(?string $market = null): string
    {
        return CarbonImmutable::now(self::zoneFor(self::market($market)))->subDays(29)->toDateString();
    }

    /** The first day of the range in the market-local calendar (what the "From" input shows), Y-m-d. */
    public function localFromDate(): string
    {
        return $this->from->setTimezone(self::zoneFor($this->market))->toDateString();
    }

    /** The last (inclusive) day of the range in the market-local calendar, Y-m-d. */
    public function localToDate(): string
    {
        return $this->to->setTimezone(self::zoneFor($this->market))->toDateString();
    }

    private static function zoneFor(?string $market): string
    {
        return ($market === null ? Market::Egypt : Market::from($market))->timezone();
    }

    /** @param  list<int>|null  $courseIds */
    public function scoped(?array $courseIds, ?int $courseId, ?int $instructorId, ?string $market): self
    {
        return new self($this->from, $this->to, $courseId, $instructorId, $market, $courseIds);
    }

    /** @return array<string, string|int|list<int>|null> the filters as written to the audit log (no personal data) */
    public function toAudit(): array
    {
        return [
            'from' => $this->from->toDateTimeString(),
            'to' => $this->to->toDateTimeString(),
            'course_id' => $this->courseId,
            'instructor_id' => $this->instructorId,
            'market' => $this->market,
            'course_scope' => $this->courseIds === null ? 'all' : count($this->courseIds),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \InvalidArgumentException('date');
    }

    private static function id(mixed $value): ?int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0 ? (int) $value : null;
    }

    private static function market(mixed $value): ?string
    {
        return is_string($value) && Market::tryFrom($value) !== null ? $value : null;
    }
}
