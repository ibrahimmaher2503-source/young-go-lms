# Notification event matrix (T-P9-05)

Source: PRD §20. Code: `App\Domain\Notifications\NotificationMatrix` (the tests iterate it). Every row has an AR and EN default in `lang/{ar,en}` and, when editable, an admin override in `notification_templates` (Filament: Notification templates).

**Mandatory** notices are transactional or safety related and cannot be opted out of. **Optional** notices can be switched off on `/profile/notifications` (table `user_notification_opt_outs`). Default (recorded in `agents/PROGRESS.md` Notes): only reminders and course announcements are optional.

Status: `wired` = a notice is sent today; `pending` = AR/EN template exists but the source event belongs to another task (owner in the last column); `framework` = sent by Laravel/AuthMail, not editable here.

Placeholders are whitelisted `:tokens` only (plain text, no HTML, no Blade).

| Event key | Group | Type | Status | Placeholders | Default title key | Owner if pending |
|---|---|---|---|---|---|---|
| `account.verify` | account | mandatory | framework | - | - |  |
| `account.password_reset` | account | mandatory | framework | - | - |  |
| `access.granted` | access | mandatory | wired | `:course` | `notification_events.templates.access_granted.title` | Effective access granted |
| `access.expiring` | access | mandatory | wired | `:course` `:date` | `notification_events.templates.access_expiring.title` | D-030 (7/3/1 days) |
| `access.revoked` | access | mandatory | wired | `:course` | `notification_events.templates.access_revoked.title` | Loss of the last active grant |
| `payment.pending` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_pending.title` | |
| `payment.review` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_review.title` |  |
| `payment.approved` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_approved.title` |  |
| `payment.review_rejected` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_review_rejected.title` | |
| `payment.failed` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_failed.title` | |
| `payment.refunded` | payment | mandatory | wired | `:order` | `notification_events.templates.payment_refunded.title` | |
| `subscription.renewal_due` | subscription | optional | wired | `:plan` `:date` `:days` | `notification_events.templates.subscription_renewal_due.title` | T-P8-08 (7/3/1 days before period end, sent to the payer) |
| `live.scheduled` | live | mandatory | wired | `:title` `:time` | `live.notice.scheduled.title` |  |
| `live.reminder` | live | optional | wired | `:title` `:time` | `live.notice.reminder.title` |  |
| `live.rescheduled` | live | mandatory | wired | `:title` `:time` | `live.notice.rescheduled.title` |  |
| `live.cancelled` | live | mandatory | wired | `:title` `:time` | `live.notice.cancelled.title` |  |
| `assignment.published` | assignment | mandatory | wired | `:title` | `notification_events.templates.assignment_published.title` | Eligible learners |
| `assignment.due` | assignment | optional | wired | `:title` `:date` | `notification_events.templates.assignment_due.title` | D-030 (7/3/1 days) |
| `assignment.submitted` | assignment | mandatory | wired | `:title` | `notification_events.templates.assignment_submitted.title` | Authorized review queue |
| `assignment.graded` | assignment | mandatory | wired | `:title` `:score` | `review.notice.graded.title` |  |
| `assignment.accepted` | assignment | mandatory | wired | `:title` | `review.notice.accepted.title` |  |
| `assignment.rejected` | assignment | mandatory | wired | `:title` | `review.notice.rejected.title` |  |
| `assignment.revision` | assignment | mandatory | wired | `:title` | `review.notice.revision_requested.title` |  |
| `quiz.result` | assignment | mandatory | wired | `:title` | `review.notice.essay_reviewed.title` |  |
| `certificate.issued` | certificate | mandatory | wired | `:title` | `certificates.notice.title` |  |
| `certificate.revoked` | certificate | mandatory | wired | `:title` | `notification_events.templates.certificate_revoked.title` | Holder |
| `course.announcement` | announcement | optional | wired | `:course` `:title` `:message` | `notification_events.templates.course_announcement.title` |  |
| `help.created` | help | mandatory | wired | `:lesson` | `help.notice.created.title` |  |
| `help.student_replied` | help | mandatory | wired | `:lesson` | `help.notice.student_replied.title` |  |
| `help.overdue` | help | mandatory | wired | `:lesson` | `help.notice.overdue.title` |  |
| `help.reply` | help | mandatory | wired | `:lesson` | `help.notice.reply.title` |  |
| `safeguarding.alert` | safeguarding | mandatory | wired | none | `notification_events.templates.safeguarding_alert.title` | DRAFT wording pending legal review (O-028); admin + support; case link only |
| `settlement.statement` | settlement | mandatory | wired | `:period` | `notification_events.templates.settlement_statement.title` |  |
| `settlement.status` | settlement | mandatory | wired | `:period` `:status` | `notification_events.templates.settlement_status.title` |  |
| `course.approved` | course_review | mandatory | wired | `:course` | `notification_events.templates.course_approved.title` |  |
| `course.rejected` | course_review | mandatory | wired | `:course` `:reason` | `notification_events.templates.course_rejected.title` |  |
| `course.revision_submitted` | course_review | mandatory | wired | `:course` `:revision` | `notification_events.templates.course_revision_submitted.title` |  |
| `course.revision_approved` | course_review | mandatory | wired | `:course` `:revision` | `notification_events.templates.course_revision_approved.title` |  |
| `course.revision_rejected` | course_review | mandatory | wired | `:course` `:revision` `:reason` | `notification_events.templates.course_revision_rejected.title` |  |

## Wiring notes

- Preferences gate in `NotificationDispatcher::send` (via `LocalizedNotification::event()`). Every matrix-backed notice (`QuizResultNotice`, `EssayReviewedNotice`, `SubmissionDecisionNotice`, `CertificateIssuedNotice`, `AccessNotice`, `AnnouncementNotice`, `TemplatedNotice`) now returns its matrix event key so mandatory/optional checks and category opt-outs (`NotificationPreferences`) stay authoritative. The email switch on the profile page (`notification_preferences.email`) affects optional notices; mandatory notices keep their email copy.
- `quiz.result` is wired across all three quiz outcomes with shared per-attempt idempotency (`quiz-review:{attempt_id}`): immediate auto-graded submits (`SubmitQuizAttempt` via `QuizResultNotice` when `!needs_review` and `result_visibility !== 'hidden'`), completed manual essay reviews (`GradeEssayAnswer` via `EssayReviewedNotice` once results are visible or already released), and manual result releases (`ReleaseQuizResult` via `EssayReviewedNotice` or `QuizResultNotice`).
- Payer notices use `NotifyPayerOfPayment` and the editable payment templates. Checkout creation sends pending once per order. Verified declines send once per attempt. Manual review and rejection send once per proof and outcome. Approval sends once per order. An executed refund sends once per refund. Links use the payer's own order, proof form or payment history.
- YG-GAP-FAM-008 applies when the payer is a child without email. The verified guardian receives a localized email copy with the private action link removed.
- D-030 reminders run in the scheduler. Access checks use the effective end across continuous grants, including renewals. Assignment reminders respect optional notification preferences and skip completed submissions. Expiry and due reminders use a one-day delivery window for each offset.
- Course review/revision listeners (`NotifyInstructorsOfCourseReview`, `NotifyOfCourseRevision`), `AccessNotice` (with validated `timezone_identifiers_list()` fallback), `QuizResultNotice`, `EssayReviewedNotice`, and `SubmissionDecisionNotice` resolve model titles in the recipient's locale.
