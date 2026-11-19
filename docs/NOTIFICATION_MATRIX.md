# Notification event matrix (T-P9-05)

Source: PRD §20. Code: `App\Domain\Notifications\NotificationMatrix` (the tests iterate it). Every row has an AR and EN default in `lang/{ar,en}` and, when editable, an admin override in `notification_templates` (Filament: Notification templates).

**Mandatory** notices are transactional or safety related and cannot be opted out of. **Optional** notices can be switched off on `/profile/notifications` (table `user_notification_opt_outs`). Default (recorded in `agents/PROGRESS.md` Notes): only reminders and course announcements are optional.

Status: `wired` = a notice is sent today; `pending` = AR/EN template exists but the source event belongs to another task (owner in the last column); `framework` = sent by Laravel/AuthMail, not editable here.

Placeholders are whitelisted `:tokens` only (plain text, no HTML, no Blade).

| Event key | Group | Type | Status | Placeholders | Default title key | Owner if pending |
|---|---|---|---|---|---|---|
| `account.verify` | account | mandatory | framework | - | - |  |
| `account.password_reset` | account | mandatory | framework | - | - |  |
| `access.granted` | access | mandatory | pending | `:course` | `notification_events.templates.access_granted.title` | T-P4-02 |
| `access.expiring` | access | mandatory | pending | `:course` `:date` | `notification_events.templates.access_expiring.title` | T-P4-02 |
| `access.revoked` | access | mandatory | pending | `:course` | `notification_events.templates.access_revoked.title` | T-P4-02 |
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
| `assignment.published` | assignment | mandatory | pending | `:title` | `notification_events.templates.assignment_published.title` | T-P6-03 |
| `assignment.due` | assignment | optional | pending | `:title` `:date` | `notification_events.templates.assignment_due.title` | T-P6-03 |
| `assignment.submitted` | assignment | mandatory | pending | `:title` | `notification_events.templates.assignment_submitted.title` | T-P6-03 |
| `assignment.graded` | assignment | mandatory | wired | `:title` `:score` | `review.notice.graded.title` |  |
| `assignment.accepted` | assignment | mandatory | wired | `:title` | `review.notice.accepted.title` |  |
| `assignment.rejected` | assignment | mandatory | wired | `:title` | `review.notice.rejected.title` |  |
| `assignment.revision` | assignment | mandatory | wired | `:title` | `review.notice.revision_requested.title` |  |
| `quiz.result` | assignment | mandatory | wired | `:title` | `review.notice.essay_reviewed.title` |  |
| `certificate.issued` | certificate | mandatory | wired | `:title` | `certificates.notice.title` |  |
| `certificate.revoked` | certificate | mandatory | pending | `:title` | `notification_events.templates.certificate_revoked.title` | T-P6-06 |
| `course.announcement` | announcement | optional | wired | `:course` `:title` `:message` | `notification_events.templates.course_announcement.title` |  |
| `help.created` | help | mandatory | wired | `:lesson` | `help.notice.created.title` |  |
| `help.student_replied` | help | mandatory | wired | `:lesson` | `help.notice.student_replied.title` |  |
| `help.overdue` | help | mandatory | wired | `:lesson` | `help.notice.overdue.title` |  |
| `help.reply` | help | mandatory | wired | `:lesson` | `help.notice.reply.title` |  |
| `safeguarding.alert` | safeguarding | mandatory | wired | none | `notification_events.templates.safeguarding_alert.title` | DRAFT wording pending legal review (O-028); admin + support; case link only |
| `settlement.statement` | settlement | mandatory | wired | `:period` | `notification_events.templates.settlement_statement.title` |  |
| `settlement.status` | settlement | mandatory | wired | `:period` `:status` | `notification_events.templates.settlement_status.title` |  |
| `course.revision_submitted` | course_review | mandatory | wired | `:course` `:revision` | `notification_events.templates.course_revision_submitted.title` |  |
| `course.revision_approved` | course_review | mandatory | wired | `:course` `:revision` | `notification_events.templates.course_revision_approved.title` |  |
| `course.revision_rejected` | course_review | mandatory | wired | `:course` `:revision` `:reason` | `notification_events.templates.course_revision_rejected.title` |  |

## Wiring notes

- Preferences gate in `NotificationDispatcher::send` (via `LocalizedNotification::event()`; null = mandatory). The email switch on the profile page (`notification_preferences.email`) now only affects optional notices; mandatory notices keep their email copy.
- Payer notices use `NotifyPayerOfPayment` and the editable payment templates. Checkout creation sends pending once per order. Verified declines send once per attempt. Manual review and rejection send once per proof and outcome. Approval sends once per order. An executed refund sends once per refund. Links use the payer's own order, proof form or payment history.
- YG-GAP-FAM-008 applies when the payer is a child without email. The verified guardian receives a localized email copy with the private action link removed.
- Pending sources: access.*, assignment.published/due/submitted, certificate.revoked.
