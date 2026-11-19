<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Notifications;

use App\Domain\Certificates\Certificate;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;

final class CertificateRevokedNotice extends LocalizedNotification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function dedupeKey(): string
    {
        return 'certificate.revoked:'.$this->certificate->getKey();
    }

    public function event(): string
    {
        return 'certificate.revoked';
    }

    protected function title(User $recipient): string
    {
        return $this->text('title');
    }

    protected function body(User $recipient): string
    {
        return $this->text('body');
    }

    protected function url(User $recipient): string
    {
        return $this->certificate->verifyUrl();
    }

    private function text(string $field): string
    {
        $values = ['title' => $this->certificate->titleIn(app()->getLocale())];

        return NotificationTemplates::render($this->event(), $field, $values, __('notification_events.templates.certificate_revoked.'.$field, $values));
    }
}
