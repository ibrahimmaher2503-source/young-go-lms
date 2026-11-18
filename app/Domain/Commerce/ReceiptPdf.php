<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Support\Brand;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Builds the receipt PDF (sale receipt or refund credit note) with headless Chromium through spatie/laravel-pdf (R-030), so
 * Arabic shapes correctly. The view is standalone (tokens from config/brand.php, Noto Sans Arabic embedded). Amounts come from the frozen
 * e-receipt snapshot; seller tax data that is still UNKNOWN is simply not printed.
 */
final class ReceiptPdf
{
    public const array LOCALES = ['ar', 'en'];

    public function build(EReceipt $receipt, string $locale): PdfBuilder
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        return Pdf::view('receipts.pdf', $this->data($receipt, $locale))
            ->format('a4')->margins(0, 0, 0, 0)
            ->download($receipt->number.'.pdf')
            ->headers(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    public function data(EReceipt $receipt, string $locale): array
    {
        $snapshot = $receipt->snapshot;
        $market = collect(Market::cases())->first(static fn (Market $m): bool => $m->currency() === $receipt->currency) ?? Market::Egypt;
        $format = static fn (int $minor): string => (new PriceFormatter)->format(Money::ofMinor($minor, $receipt->currency), $market, $locale);
        $titleKey = 'title_'.$locale;

        $lines = [];
        foreach ((array) ($snapshot['lines'] ?? []) as $line) {
            $line = (array) $line;
            $lines[] = [
                'title' => (string) ($line[$titleKey] ?? $line['title_en'] ?? $line['title_ar'] ?? ''),
                'unit' => $format((int) ($line['unit_price_minor'] ?? 0)),
                'discount' => (int) ($line['discount_minor'] ?? 0) > 0 ? $format((int) $line['discount_minor']) : null,
                'net' => $format((int) ($line['net_minor'] ?? 0)),
            ];
        }
        $seller = (array) ($snapshot['seller'] ?? []);
        $buyer = (array) ($snapshot['buyer'] ?? []);

        return [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'number' => $receipt->number,
            'isRefund' => $receipt->kind === ReceiptKind::Refund,
            'originalNumber' => $snapshot['original_receipt_number'] ?? null,
            'orderReference' => (string) (((array) ($snapshot['payment'] ?? []))['order_reference'] ?? ''),
            'date' => $receipt->issued_at->locale($locale)->isoFormat('LL'),
            'sellerName' => filled($seller['name'] ?? null) ? (string) $seller['name'] : null,
            'sellerTaxId' => filled($seller['tax_id'] ?? null) ? (string) $seller['tax_id'] : null,
            'buyerName' => (string) ($buyer['name'] ?? ''),
            'lines' => $lines,
            'subtotal' => $format($receipt->subtotal_minor),
            'discount' => $receipt->discount_minor > 0 ? $format($receipt->discount_minor) : null,
            'total' => $format($receipt->total_minor),
            'font' => Brand::fontDataUri(),
        ];
    }
}
