<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\SubmissionDecision;
use App\Models\User;

/** Tells a learner (or, through the router, the guardians of a child without email) about one reviewer decision. One decision, one message. */
final class SubmissionDecisionNotice extends LocalizedNotification
{
    public function __construct(
        private readonly SubmissionDecision $decision,
        private readonly int $courseId,
        private readonly int $itemId,
        private readonly string $assignmentTitle,
    ) {}

    public function event(): string
    {
        return $this->matrixEvent();
    }

    public function dedupeKey(): string
    {
        return 'assignment-decision:'.$this->decision->getKey();
    }

    protected function title(User $recipient): string
    {
        $values = ['title' => $this->titleText()];

        return NotificationTemplates::render($this->matrixEvent(), 'title', $values, __('review.notice.'.$this->decision->decision.'.title', $values));
    }

    protected function body(User $recipient): string
    {
        $score = $this->decision->final_score === null ? '' : rtrim(rtrim((string) $this->decision->final_score, '0'), '.');

        $values = ['title' => $this->titleText(), 'score' => $score];

        return NotificationTemplates::render($this->matrixEvent(), 'body', $values, __('review.notice.'.$this->decision->decision.'.body', $values));
    }

    /** The matrix event for this decision: graded, accepted, rejected or revision (requested). */
    private function matrixEvent(): string
    {
        return 'assignment.'.($this->decision->decision === 'revision_requested' ? 'revision' : $this->decision->decision);
    }

    private function titleText(): string
    {
        $assignment = $this->decision->submission?->assignment;
        if ($assignment !== null) {
            $translated = (string) $assignment->getTranslation('title', app()->getLocale());
            if ($translated !== '') {
                return $translated;
            }
        }

        return $this->assignmentTitle;
    }

    protected function url(User $recipient): string
    {
        return route('learn.show', ['course' => $this->courseId, 'item' => $this->itemId]);
    }
}
