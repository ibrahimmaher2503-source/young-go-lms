<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Listeners;

use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Certificates\Notifications\CertificateIssuedNotice;
use App\Domain\Completion\Events\CourseCompleted;
use App\Domain\Notifications\NotificationDispatcher;
use Throwable;

/**
 * Issues the certificate when a course is completed. Synchronous but never breaks the caller: the completion is already
 * saved, so a failure here is reported and staff can re-issue from the completion page. The PDF is rendered on download, not here.
 */
final class IssueCertificateOnCompletion
{
    public function __construct(private readonly IssueCertificate $issue, private readonly NotificationDispatcher $notifications) {}

    public function handle(CourseCompleted $event): void
    {
        try {
            $certificate = ($this->issue)($event->completion);
            if ($certificate !== null) {
                $this->notifications->send($event->completion->learner, new CertificateIssuedNotice($certificate));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
