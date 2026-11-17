<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use App\Domain\Settings\CertificateSettings;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Builds the branded certificate PDF (R-030: headless Chromium through spatie/laravel-pdf, so Arabic shapes correctly).
 * The view is standalone: tokens inlined, Noto Sans Arabic embedded, the QR drawn as inline SVG that points to the verify URL.
 */
final class CertificatePdf
{
    public const array LOCALES = ['ar', 'en'];

    public function __construct(private readonly CertificateSettings $settings) {}

    public function build(Certificate $certificate, string $locale): PdfBuilder
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        return Pdf::view('certificates.pdf', $this->data($certificate, $locale))
            ->format('a4')->landscape()->margins(0, 0, 0, 0)
            ->download($certificate->number.'.pdf')
            ->headers(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    public function data(Certificate $certificate, string $locale): array
    {
        $other = $locale === 'ar' ? 'en' : 'ar';
        $issuer = $locale === 'ar' ? $this->settings->issuer_text_ar : $this->settings->issuer_text_en;
        $title = $certificate->titleIn($locale);
        $otherTitle = $certificate->titleIn($other);

        return [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'name' => $certificate->learner_name,
            'courseTitle' => $title,
            'otherTitle' => $otherTitle !== $title ? $otherTitle : null,
            'otherDir' => $other === 'ar' ? 'rtl' : 'ltr',
            'otherLocale' => $other,
            'date' => $certificate->completed_on->locale($locale)->isoFormat('LL'),
            'number' => $certificate->number,
            'issuer' => filled($issuer) ? $issuer : __('certificates.pdf.issuer_placeholder', [], $locale),
            'verifyUrl' => $certificate->verifyUrl(),
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($certificate->verifyUrl())),
            'font' => 'data:font/ttf;base64,'.base64_encode((string) file_get_contents(resource_path('fonts/noto-sans-arabic/NotoSansArabic-Variable.ttf'))),
        ];
    }

    private function qrSvg(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd));

        return $writer->writeString($url, Encoder::DEFAULT_BYTE_MODE_ENCODING);
    }
}
