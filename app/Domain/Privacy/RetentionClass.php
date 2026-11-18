<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

/**
 * Prunable data classes (YG-GAP-PRV-002). Defaults are conservative and OPEN (O-025): only the classes the PRD names a value for
 * (AI messages 180 days, logs 90 days) and short-lived export archives are on by default; the rest keep data until an owner decision.
 * Orders, payments, refunds, receipts, certificates and the audit log are not classes and are never pruned.
 */
enum RetentionClass: string
{
    /** Value is owned by the AI settings page (AiSettings::message_retention_days); shown read-only here. */
    case AiMessages = 'ai_messages';
    case SessionLogs = 'session_logs';
    case OperationalLogs = 'operational_logs';
    case HelpRequests = 'help_requests';
    case SubmissionFiles = 'submission_files';
    case ContactEnquiries = 'contact_enquiries';
    case NewsletterUnsubscribed = 'newsletter_unsubscribed';
    case DataExports = 'data_exports';

    /** Days kept when no override exists; null keeps the data until the owner decides. */
    public function defaultDays(): ?int
    {
        return match ($this) {
            self::AiMessages => 180,
            self::SessionLogs, self::OperationalLogs => 90,
            self::DataExports => 7,
            self::HelpRequests, self::SubmissionFiles, self::ContactEnquiries, self::NewsletterUnsubscribed => null,
        };
    }

    public function isManagedElsewhere(): bool
    {
        return $this === self::AiMessages;
    }

    /** @return list<string> tables this class deletes from; checked against ProtectedData before any pruning. */
    public function tables(): array
    {
        return match ($this) {
            self::AiMessages => ['ai_messages', 'ai_feedback', 'ai_conversations', 'ai_usage', 'ai_answer_cache', 'ai_quota_counters'],
            self::SessionLogs => ['user_sessions'],
            self::OperationalLogs => ['telescope_entries', 'failed_jobs'],
            self::HelpRequests => ['help_requests', 'help_request_messages', 'help_request_events'],
            self::SubmissionFiles => ['submission_files'],
            self::ContactEnquiries => ['contact_enquiries'],
            self::NewsletterUnsubscribed => ['newsletter_subscribers'],
            self::DataExports => ['data_requests'],
        };
    }

    public function label(): string
    {
        return __('privacy.class_'.$this->value);
    }
}
