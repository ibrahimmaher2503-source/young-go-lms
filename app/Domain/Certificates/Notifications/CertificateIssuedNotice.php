<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Notifications;

use App\Domain\Certificates\Certificate;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;

/** Tells a learner (or, through the router, the guardians of a child without email) that a certificate is ready. Once per completion. */
final class CertificateIssuedNotice extends LocalizedNotification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function event(): string
    {
        return 'certificate.issued';
    }

    public function dedupeKey(): string
    {
        return 'certificate-issued:'.$this->certificate->course_completion_id;
    }

    protected function title(User $recipient): string
    {
        return NotificationTemplates::render('certificate.issued', 'title', ['title' => $this->certificate->titleIn(app()->getLocale())], __('certificates.notice.title'));
    }

    protected function body(User $recipient): string
    {
        $values = ['title' => $this->certificate->titleIn(app()->getLocale())];

        return NotificationTemplates::render('certificate.issued', 'body', $values, __('certificates.notice.body', $values));
    }

    protected function url(User $recipient): string
    {
        return route('certificates.download', ['certificate' => $this->certificate->getKey()]);
    }
}
