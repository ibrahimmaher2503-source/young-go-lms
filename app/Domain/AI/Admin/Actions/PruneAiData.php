<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\AiAnswerCache;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiQuotaCounter;
use App\Models\AiSafeguardingFlag;
use App\Models\AiUsage;
use App\Models\HelpRequest;

/**
 * Retention pruning for AI data (AI spec 18): messages older than the configured days (default 180), usage metrics older than
 * the configured months (default 24), expired cache rows and old quota counters. A message that a help request points at stays
 * until the request is handled by privacy tooling (T-P9-08 may replace this job), and a conversation that a safeguarding flag
 * points at is never deleted: flags follow their own policy (O-028). Idempotent; safe to run daily.
 */
final class PruneAiData
{
    public function __construct(private readonly AiSettings $settings, private readonly AuditLogger $audit) {}

    /** @return array{messages: int, conversations: int, usage: int, cache: int, counters: int} */
    public function __invoke(): array
    {
        $messageCutoff = now()->subDays(max(1, $this->settings->message_retention_days));
        $usageCutoff = now()->subMonths(max(1, $this->settings->usage_retention_months));

        $messages = 0;
        $kept = HelpRequest::query()->select('ai_message_id');
        do {
            $ids = AiMessage::query()->where('created_at', '<', $messageCutoff)->whereNotIn('id', $kept)->orderBy('id')->limit(500)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            AiFeedback::query()->whereIn('message_id', $ids)->delete();
            $messages += AiMessage::query()->whereIn('id', $ids)->delete();
        } while (true);

        $flagged = AiSafeguardingFlag::query()->whereNotNull('conversation_id')->select('conversation_id');
        $conversations = AiConversation::query()->where('updated_at', '<', $messageCutoff)->whereDoesntHave('messages')
            ->whereNotIn('id', $flagged)->whereNotIn('id', HelpRequest::query()->select('conversation_id'))->delete();

        $report = [
            'messages' => $messages,
            'conversations' => $conversations,
            'usage' => AiUsage::query()->where('created_at', '<', $usageCutoff)->delete(),
            'cache' => AiAnswerCache::query()->where('expires_at', '<', now())->delete(),
            'counters' => AiQuotaCounter::query()->where('period', '<', now()->subDays(7)->toDateString())->delete(),
        ];

        $subject = AiPromptVersion::current();
        if ($subject !== null && array_sum($report) > 0) {
            $this->audit->record('ai.retention_pruned', $subject, null, null, $report, [
                'message_retention_days' => $this->settings->message_retention_days, 'usage_retention_months' => $this->settings->usage_retention_months,
            ]);
        }

        return $report;
    }
}
