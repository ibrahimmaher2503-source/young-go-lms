<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Domain\Catalog\Revisions\Events\CourseRevisionSubmitted;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * T-P3-07: reviewers hear about a submitted revision; the author and the course's instructors hear the outcome (reason included
 * on rejection). Goes through the notification dispatcher (matrix events course.revision_*); a failure never undoes the decision.
 */
final class NotifyOfCourseRevision
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function submitted(CourseRevisionSubmitted $event): void
    {
        $revision = $event->revision->loadMissing('course');
        $course = $revision->course;
        $values = ['course' => $course->title, 'revision' => (string) $revision->number];

        $reviewers = User::query()->permission('course.publish')->where('status', 'active')->whereKeyNot($revision->author_id)->get()
            ->filter(static fn (User $user): bool => $user->can('panel.admin.access') && Gate::forUser($user)->allows('publish', $course));

        foreach ($reviewers as $reviewer) {
            $this->send($reviewer, new TemplatedNotice(
                'course.revision_submitted', $values, 'course-revision-submitted:'.$revision->getKey().':'.$revision->submitted_at?->timestamp.':'.$reviewer->getKey(), url('/admin/course-revisions'),
            ));
        }
    }

    public function reviewed(CourseRevisionReviewed $event): void
    {
        $revision = $event->revision->loadMissing('course');
        $course = $revision->course;
        $key = $event->approved ? 'course.revision_approved' : 'course.revision_rejected';
        $ids = $course->instructors()->pluck('users.id')->push($course->owner_instructor_id, $revision->author_id)->filter()->unique();

        foreach (User::query()->whereKey($ids)->get() as $user) {
            $panel = $user->can('panel.admin.access') ? 'admin' : 'instructor';
            $this->send($user, new TemplatedNotice(
                $key, ['course' => $course->title, 'revision' => (string) $revision->number, 'reason' => (string) $event->reason],
                'course-revision-review:'.$revision->getKey().':'.$revision->reviewed_at?->timestamp.':'.$user->getKey(), url('/'.$panel.'/courses/'.$course->getKey().'/revision'),
            ));
        }
    }

    private function send(User $recipient, TemplatedNotice $notice): void
    {
        try {
            $this->dispatcher->send($recipient, $notice);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
