<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\Notifications\AssignmentNotice;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Entitlements\CourseLearners;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CurriculumItem;
use App\Models\User;
use Throwable;

final class SendAssignmentNotices
{
    public function __construct(private readonly NotificationDispatcher $dispatcher, private readonly EntitlementService $access) {}

    /** @param list<int> $itemIds */
    public function published(array $itemIds): void
    {
        $assignments = Assignment::query()->whereNull('archived_at')->whereHas('item', fn ($item) => $item->whereKey($itemIds))->with('item.course', 'item.section')->get();
        foreach ($assignments as $assignment) {
            $this->sendToLearners($assignment, AssignmentNotice::PUBLISHED);
        }
    }

    public function due(): int
    {
        $now = now()->utc()->toImmutable();
        $sent = 0;
        // Keep each reminder eligible for a day so a delayed scheduler run can still deliver it.
        foreach ([7, 3, 1] as $days) {
            $assignments = Assignment::query()->whereNull('archived_at')
                ->where('due_at', '>', $now->addDays($days - 1))->where('due_at', '<=', $now->addDays($days))
                ->whereHas('item', fn ($item) => $item->where('status', CurriculumItem::STATUS_PUBLISHED))
                ->with('item.course', 'item.section')->lazyById();

            foreach ($assignments as $assignment) {
                $sent += $this->sendToLearners($assignment, AssignmentNotice::DUE, ':'.$assignment->due_at?->getTimestamp().':'.$days);
            }
        }

        return $sent;
    }

    public function submitted(Assignment $assignment, CurriculumItem $item, int $submissionId, int $revision): void
    {
        $course = $item->course;
        $reviewers = User::query()->permission('assessment.manage')->where('status', 'active')->lazyById();
        foreach ($reviewers as $reviewer) {
            if (! ReviewAccess::allows($reviewer, $course) || ! in_array((int) $course->getKey(), ReviewAccess::courseIds($reviewer), true)) {
                continue;
            }
            $panel = $reviewer->can('panel.admin.access') ? 'admin' : 'instructor';
            $this->send($reviewer, new AssignmentNotice(
                $assignment,
                AssignmentNotice::SUBMITTED,
                'assignment.submitted:'.$submissionId.':'.$revision,
                route('filament.'.$panel.'.resources.submission-review.review', ['record' => $submissionId]),
            ));
        }
    }

    private function sendToLearners(Assignment $assignment, string $event, string $variant = ''): int
    {
        $item = $assignment->item;
        if ($item === null || $item->status !== CurriculumItem::STATUS_PUBLISHED || ! $item->section?->published) {
            return 0;
        }

        $learners = CourseLearners::query($item->course)->where('users.status', 'active');
        if ($event === AssignmentNotice::DUE) {
            $learners->whereNotIn('users.id', $assignment->submissions()
                ->whereNotIn('status', [AssignmentSubmission::DRAFT, AssignmentSubmission::RETURNED])->select('learner_id'));
        }
        $notice = new AssignmentNotice($assignment, $event, $event.':'.$item->getKey().$variant, route('learn.show', ['course' => $item->course_id, 'item' => $item->getKey()]));

        $sent = 0;
        foreach ($learners->lazyById(column: 'users.id', alias: 'id') as $learner) {
            if ($this->access->canAccessItem($learner, $item)->allowed) {
                $sent += $this->send($learner, $notice) ? 1 : 0;
            }
        }

        return $sent;
    }

    private function send(User $recipient, AssignmentNotice $notice): bool
    {
        try {
            return $this->dispatcher->send($recipient, $notice);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
