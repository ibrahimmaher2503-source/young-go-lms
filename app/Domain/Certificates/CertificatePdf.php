<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use App\Domain\Settings\CertificateSettings;
use App\Support\Brand;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Builds the branded certificate PDF (R-030: headless Chromium through spatie/laravel-pdf, so Arabic shapes correctly).
 * The view is standalone: tokens from config/brand.php, Noto Sans Arabic embedded, the QR drawn as inline SVG that points to the verify URL.
 */
final class CertificatePdf
{
    public const array LOCALES = ['ar', 'en'];

    public function __construct(private readonly CertificateSettings $settings) {}

    public function build(Certificate $certificate, string $locale): PdfBuilder
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        $template = $this->template($locale);

        return Pdf::view($template->view(), $this->data($certificate, $locale, $template))
            ->format('a4')->landscape()->margins(0, 0, 0, 0)
            ->download($certificate->number.'.pdf')
            ->headers(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    public function data(Certificate $certificate, string $locale, ?CertificateTemplate $template = null): array
    {
        $other = $locale === 'ar' ? 'en' : 'ar';
        $template ??= $this->template($locale);
        $issuer = $template->issuer_text ?? ($locale === 'ar' ? $this->settings->issuer_text_ar : $this->settings->issuer_text_en);
        $title = $certificate->titleIn($locale);
        $otherTitle = $certificate->titleIn($other);

        return [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'name' => $certificate->nameIn($locale),
            'courseTitle' => $title,
            'otherTitle' => $otherTitle !== $title ? $otherTitle : null,
            'otherDir' => $other === 'ar' ? 'rtl' : 'ltr',
            'otherLocale' => $other,
            'date' => $certificate->completed_on->locale($locale)->isoFormat('LL'),
            'number' => $certificate->number,
            'issuer' => filled($issuer) ? $issuer : __('certificates.pdf.issuer_placeholder', [], $locale),
            'verifyUrl' => $certificate->verifyUrl(),
            'qr' => $this->verificationQr($certificate),
            'font' => Brand::fontDataUri(),
        ];
    }

    private function template(string $locale): CertificateTemplate
    {
        return CertificateTemplate::query()->firstWhere('locale', $locale)
            ?? new CertificateTemplate(['locale' => $locale, 'layout' => CertificateTemplate::DEFAULT_LAYOUT]);
    }

    public function verificationQr(Certificate $certificate): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($certificate->verifyUrl(), Encoder::DEFAULT_BYTE_MODE_ENCODING));
    }
}
