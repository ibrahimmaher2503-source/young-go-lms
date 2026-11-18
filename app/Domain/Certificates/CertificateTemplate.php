<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $locale
 * @property string $layout
 * @property string|null $issuer_text
 */
final class CertificateTemplate extends Model
{
    public const string DEFAULT_LAYOUT = 'standard';

    private const array VIEWS = [self::DEFAULT_LAYOUT => 'certificates.pdf'];

    protected $fillable = ['locale', 'layout', 'issuer_text'];

    public function view(): string
    {
        // Only shipped Blade layouts may execute when rendering a certificate.
        return self::VIEWS[$this->layout] ?? self::VIEWS[self::DEFAULT_LAYOUT];
    }
}
