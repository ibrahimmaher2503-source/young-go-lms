<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Builds the statement PDF with headless Chromium through spatie/laravel-pdf (same pattern as ReceiptPdf, R-030). Everything
 * printed comes from the frozen statement snapshot; no student identity and no payout details appear.
 */
final class StatementPdf
{
    public const array LOCALES = ['ar', 'en'];

    public function build(InstructorStatement $statement, string $locale): PdfBuilder
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        return Pdf::view('statements.pdf', $this->data($statement, $locale))
            ->format('a4')->margins(0, 0, 0, 0)
            ->download($statement->number.'.pdf')
            ->headers(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    public function data(InstructorStatement $statement, string $locale): array
    {
        $market = collect(Market::cases())->first(static fn (Market $m): bool => $m->currency() === $statement->currency) ?? Market::Egypt;
        $format = static fn (int $minor): string => (new PriceFormatter)->format(Money::ofMinor($minor, $statement->currency), $market, $locale);

        $lines = [];
        foreach ((array) ($statement->snapshot['lines'] ?? []) as $line) {
            $line = (array) $line;
            $course = (string) ($line['course_'.$locale] ?? $line['course_en'] ?? $line['course_ar'] ?? '');
            $lines[] = [
                'date' => (string) ($line['date'] ?? ''),
                'course' => $course === '' ? '-' : $course,
                'type' => (string) __('settlements.type_'.(string) ($line['type'] ?? 'earning'), [], $locale),
                'order' => (string) ($line['order'] ?? ''),
                'amount' => $format((int) ($line['amount_minor'] ?? 0)),
            ];
        }

        return [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'number' => $statement->number,
            'period' => $statement->period_start->locale($locale)->isoFormat('MMMM YYYY'),
            'instructor' => (string) $statement->instructor?->getAttribute('name'),
            'generatedOn' => $statement->generated_at->locale($locale)->isoFormat('LL'),
            'lines' => $lines,
            'earnings' => $format($statement->earnings_minor),
            'reversals' => $format($statement->reversals_minor),
            'adjustments' => $format($statement->adjustments_minor),
            'net' => $format($statement->net_minor),
            'settled' => $format($statement->settled_minor),
            'forward' => $format($statement->balance_forward_minor),
            'closing' => $format($statement->closing_balance_minor),
            'payoutMethods' => implode(' / ', array_map(static fn (PayoutMethod $m): string => (string) __('settlements.method_'.$m->value, [], $locale), PayoutMethod::allowedFor($statement->currency))),
            'currency' => $statement->currency,
            'closingNegative' => $statement->closing_balance_minor < 0,
            'font' => 'data:font/ttf;base64,'.base64_encode((string) file_get_contents(resource_path('fonts/noto-sans-arabic/NotoSansArabic-Variable.ttf'))),
        ];
    }
}
