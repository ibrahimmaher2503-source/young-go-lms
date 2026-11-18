<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * The event to template matrix for PRD §20 (T-P9-05). Every event has an AR/EN default in the language files; admins can
 * override the wording in `notification_templates`. Default (documented in docs/NOTIFICATION_MATRIX.md): only reminders
 * and course announcements are optional; account, access, payment, result, certificate, help and settlement notices are
 * mandatory and cannot be opted out of.
 */
final class NotificationMatrix
{
    public const string ANNOUNCEMENT = 'course.announcement';

    /** @var array<string, NotificationEventDefinition>|null */
    private static ?array $rows = null;

    /** @return array<string, NotificationEventDefinition> */
    public static function all(): array
    {
        return self::$rows ??= self::build();
    }

    public static function find(?string $key): ?NotificationEventDefinition
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    public static function fromFormKey(string $formKey): ?NotificationEventDefinition
    {
        foreach (self::all() as $row) {
            if ($row->formKey() === $formKey) {
                return $row;
            }
        }

        return null;
    }

    /** @return list<NotificationEventDefinition> */
    public static function optional(): array
    {
        return array_values(array_filter(self::all(), static fn (NotificationEventDefinition $row): bool => $row->optional()));
    }

    /** @return list<NotificationEventDefinition> */
    public static function mandatory(): array
    {
        return array_values(array_filter(self::all(), static fn (NotificationEventDefinition $row): bool => $row->mandatory));
    }

    /** @return array<string, NotificationEventDefinition> */
    private static function build(): array
    {
        $rows = [
            self::framework('account.verify', 'account'),
            self::framework('account.password_reset', 'account'),

            self::fresh('access.granted', 'access', true, ['course'], 'T-P4-02'),
            self::fresh('access.expiring', 'access', true, ['course', 'date'], 'T-P4-02'),
            self::fresh('access.revoked', 'access', true, ['course'], 'T-P4-02'),

            self::fresh('payment.pending', 'payment', true, ['order'], 'T-P8-12'),
            self::fresh('payment.review', 'payment', true, ['order']),
            self::fresh('payment.approved', 'payment', true, ['order']),
            self::fresh('payment.review_rejected', 'payment', true, ['order'], 'T-P8-04'),
            self::fresh('payment.failed', 'payment', true, ['order'], 'T-P8-12'),
            self::fresh('payment.refunded', 'payment', true, ['order'], 'T-P8-06'),

            self::fresh('subscription.renewal_due', 'subscription', false, ['plan', 'date', 'days']),

            self::existing('live.scheduled', 'live', true, ['title', 'time'], 'live.notice.scheduled'),
            self::existing('live.reminder', 'live', false, ['title', 'time'], 'live.notice.reminder'),
            self::existing('live.rescheduled', 'live', true, ['title', 'time'], 'live.notice.rescheduled'),
            self::existing('live.cancelled', 'live', true, ['title', 'time'], 'live.notice.cancelled'),

            self::fresh('assignment.published', 'assignment', true, ['title'], 'T-P6-03'),
            self::fresh('assignment.due', 'assignment', false, ['title', 'date'], 'T-P6-03'),
            self::fresh('assignment.submitted', 'assignment', true, ['title'], 'T-P6-03'),
            self::existing('assignment.graded', 'assignment', true, ['title', 'score'], 'review.notice.graded'),
            self::existing('assignment.accepted', 'assignment', true, ['title'], 'review.notice.accepted'),
            self::existing('assignment.rejected', 'assignment', true, ['title'], 'review.notice.rejected'),
            self::existing('assignment.revision', 'assignment', true, ['title'], 'review.notice.revision_requested'),
            self::existing('quiz.result', 'assignment', true, ['title'], 'review.notice.essay_reviewed'),

            self::existing('certificate.issued', 'certificate', true, ['title'], 'certificates.notice'),
            self::fresh('certificate.revoked', 'certificate', true, ['title'], 'T-P6-06'),

            self::fresh(self::ANNOUNCEMENT, 'announcement', false, ['course', 'title', 'message']),

            self::existing('help.created', 'help', true, ['lesson'], 'help.notice.created'),
            self::existing('help.student_replied', 'help', true, ['lesson'], 'help.notice.student_replied'),
            self::existing('help.overdue', 'help', true, ['lesson'], 'help.notice.overdue'),
            self::existing('help.reply', 'help', true, ['lesson'], 'help.notice.reply'),

            self::fresh('settlement.statement', 'settlement', true, ['period']),
            self::fresh('settlement.status', 'settlement', true, ['period', 'status']),

            self::fresh('course.approved', 'course_review', true, ['course']),
            self::fresh('course.rejected', 'course_review', true, ['course', 'reason']),
            self::fresh('course.revision_submitted', 'course_review', true, ['course', 'revision']),
            self::fresh('course.revision_approved', 'course_review', true, ['course', 'revision']),
            self::fresh('course.revision_rejected', 'course_review', true, ['course', 'revision', 'reason']),
        ];

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->key] = $row;
        }

        return $indexed;
    }

    /** Sent by the framework (AuthMail), outside the dispatcher: listed for completeness, wording not editable here. */
    private static function framework(string $key, string $group): NotificationEventDefinition
    {
        return new NotificationEventDefinition($key, $group, true, NotificationEventDefinition::FRAMEWORK, [], null, null);
    }

    /**
     * An event whose AR/EN default lives in notification_events.templates.*; wired unless a task owns the missing source event.
     *
     * @param  list<string>  $placeholders
     */
    private static function fresh(string $key, string $group, bool $mandatory, array $placeholders, ?string $pendingTask = null): NotificationEventDefinition
    {
        $base = 'notification_events.templates.'.str_replace('.', '_', $key);

        return new NotificationEventDefinition(
            $key, $group, $mandatory, $pendingTask === null ? NotificationEventDefinition::WIRED : NotificationEventDefinition::PENDING,
            $placeholders, $base.'.title', $base.'.body', $pendingTask,
        );
    }

    /**
     * An event already sent by an earlier task; its default text stays in that task's language file.
     *
     * @param  list<string>  $placeholders
     */
    private static function existing(string $key, string $group, bool $mandatory, array $placeholders, string $langBase): NotificationEventDefinition
    {
        return new NotificationEventDefinition(
            $key, $group, $mandatory, NotificationEventDefinition::WIRED, $placeholders, $langBase.'.title', $langBase.'.body',
        );
    }
}
