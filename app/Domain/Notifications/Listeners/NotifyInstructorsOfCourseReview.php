<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Catalog\Events\CourseReviewed;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\User;
use Throwable;

/** T-P3-03: tells the course's instructors the review outcome (reason included on rejection). A failure never undoes the decision. */
final class NotifyInstructorsOfCourseReview
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(CourseReviewed $event): void
    {
        $course = $event->course;
        $ids = $course->instructors()->pluck('users.id')->push($course->owner_instructor_id)->filter()->unique();
        $key = $event->approved ? 'course.approved' : 'course.rejected';
        $stamp = $course->reviewed_at->timestamp;

        foreach (User::query()->whereKey($ids)->get() as $instructor) {
            try {
                $this->dispatcher->send($instructor, new TemplatedNotice(
                    $key, ['course' => $course->title, 'reason' => (string) $event->reason], 'course-review:'.$course->getKey().':'.$stamp.':'.$instructor->getKey(),
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
