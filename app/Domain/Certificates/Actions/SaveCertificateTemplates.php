<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Certificates\CertificatePdf;
use App\Domain\Certificates\CertificateTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class SaveCertificateTemplates
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function __invoke(User $actor, array $data): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('system.settings.manage')) {
            throw new AuthorizationException;
        }

        $clean = Validator::make($data, ['ar' => ['sometimes', 'nullable', 'string', 'max:500'], 'en' => ['sometimes', 'nullable', 'string', 'max:500']])->validate();

        DB::transaction(function () use ($actor, $clean): void {
            foreach (CertificatePdf::LOCALES as $locale) {
                if (! array_key_exists($locale, $clean)) {
                    continue;
                }

                $template = CertificateTemplate::query()->lockForUpdate()->where('locale', $locale)->firstOrFail();
                $before = ['issuer_text' => $template->issuer_text];
                $template->update(['issuer_text' => filled($clean[$locale]) ? trim($clean[$locale]) : '']);
                if ($this->audit->record('certificate.template_updated', $template, (int) $actor->getKey(), $before, ['issuer_text' => $template->issuer_text]) === null) {
                    throw new RuntimeException('Unable to record certificate template audit.');
                }
            }
        });
    }
}
