<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\Ingestion\DocumentReadiness;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiEvalRun;
use App\Models\AiPromptVersion;
use App\Models\AiSafeguardingFlag;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use Laravel\Pennant\Feature;

/**
 * The go-live checklist of the assistant (AI spec 14, 20 M7.7) and the lesson-PDF readiness totals. Every line is a fact read from
 * the system, with a state: ok, todo (the owner or an operator still has to act) or info. Open owner decisions (O-014 provider,
 * O-015 age tiers, O-028 safeguarding recipients) are reported as todo and are never guessed or closed here.
 */
final class AssistantReadiness
{
    public function __construct(private readonly AiSettings $settings, private readonly ProviderRegistry $registry) {}

    /**
     * @return array{
     *     checks: list<array{key: string, state: string, params: array<string, scalar|null>}>,
     *     lessons: array{total: int, none: int, processing: int, awaiting_approval: int, failed: int, ready: int}
     * }
     */
    public function __invoke(): array
    {
        $eval = AiEvalRun::query()->latest('id')->first();
        $prompt = AiPromptVersion::current();
        $provider = $this->registry->activeKey();
        $adultAge = config('ai.adult_age');
        $budgets = $this->settings->monthly_budget_minor !== null;
        $openFlags = AiSafeguardingFlag::query()->where('status', 'open')->count();

        $checks = [
            ['key' => 'global', 'state' => Feature::for(null)->active(PlatformFlag::AiAssistant->value) ? 'ok' : 'todo', 'params' => []],
            ['key' => 'provider', 'state' => $provider === ProviderRegistry::UNCONFIGURED ? 'todo' : 'ok', 'params' => ['provider' => $provider]], // O-014
            ['key' => 'prompt', 'state' => $prompt === null ? 'todo' : 'ok', 'params' => ['version' => $prompt?->version]],
            ['key' => 'eval', 'state' => $eval === null ? 'todo' : ($eval->result === 'pass' ? 'ok' : 'todo'), 'params' => [
                'result' => $eval?->result, 'provider' => $eval?->provider, 'version' => $eval?->prompt_version, 'at' => $eval?->created_at?->toDateTimeString(),
            ]],
            ['key' => 'budget', 'state' => $budgets ? 'ok' : 'todo', 'params' => []],
            ['key' => 'ages', 'state' => is_numeric($adultAge) ? 'ok' : 'todo', 'params' => ['age' => is_numeric($adultAge) ? (int) $adultAge : null]], // O-015
            ['key' => 'minors', 'state' => 'info', 'params' => ['on' => Feature::for(null)->active(PlatformFlag::AiMinors->value) ? 1 : 0]],
            ['key' => 'safeguarding', 'state' => $openFlags > 0 ? 'todo' : 'info', 'params' => ['open' => $openFlags]], // O-028
            ['key' => 'retention', 'state' => 'ok', 'params' => ['days' => $this->settings->message_retention_days, 'months' => $this->settings->usage_retention_months]],
        ];

        return ['checks' => $checks, 'lessons' => $this->lessons()];
    }

    /** @return array{total: int, none: int, processing: int, awaiting_approval: int, failed: int, ready: int} */
    private function lessons(): array
    {
        $recorded = Lesson::query()->whereIn('type', ['video', 'audio'])->whereHas('item');
        $total = (clone $recorded)->count();

        $newest = LessonCurriculumDocument::query()->selectRaw('max(id)')->whereIn('lesson_id', (clone $recorded)->select('id'))->groupBy('lesson_id');
        $counts = array_fill_keys(array_map(static fn (DocumentReadiness $state): string => $state->value, DocumentReadiness::cases()), 0);
        foreach (LessonCurriculumDocument::query()->whereIn('id', $newest)->get(['status']) as $document) {
            $counts[DocumentReadiness::fromStatus($document->status)->value]++;
        }
        $counts['none'] = max(0, $total - array_sum(array_diff_key($counts, ['none' => 0])));

        return ['total' => $total] + $counts;
    }
}
