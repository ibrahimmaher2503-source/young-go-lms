<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Contracts\ModerationClassifier;
use App\Domain\AI\Evaluation\Actions\RunAssistantEvaluation;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\KeywordModerationClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

/**
 * AI spec 13.4: score the golden and red-team dataset and exit non-zero below the launch thresholds, so CI blocks the change.
 * The default is the deterministic fake provider. The configured real provider needs --provider=configured and the explicit
 * opt-in (--allow-real or AI_EVAL_ALLOW_REAL), and is never used by CI.
 */
final class EvaluateAssistantCommand extends Command
{
    protected $signature = 'ai:evaluate
        {--suite=all : all, en, ar, golden, red-team or one case category}
        {--provider=fake : fake (deterministic, default) or configured (real provider, opt-in)}
        {--allow-real : Opt in to a run against the configured real provider}
        {--threshold=* : Override a threshold for this run, e.g. grounded_rate=90}
        {--report= : Write the JSON report to this path (default storage/app/ai-eval/report.json)}
        {--json : Print the JSON report instead of the summary}
        {--no-store : Do not store the run in ai_eval_runs}';

    protected $aliases = ['ai:eval'];

    protected $description = 'Evaluate the lesson assistant against the golden and red-team dataset and fail below the thresholds.';

    public function handle(RunAssistantEvaluation $evaluate): int
    {
        try {
            [$provider, $moderation, $label] = $this->provider();
            $suite = (string) $this->option('suite');
            $report = $evaluate($provider, $moderation, $label, $suite, $this->thresholds(), ! (bool) $this->option('no-store'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $path = (string) ($this->option('report') ?: storage_path('app/ai-eval/report.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $report->toJson());

        if ($this->option('json')) {
            $this->line($report->toJson());
        } else {
            $this->summary($report->toArray(), $path);
        }

        return $report->passed() ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: AiProviderInterface, 1: ModerationClassifier, 2: string} */
    private function provider(): array
    {
        $choice = (string) $this->option('provider');
        if ($choice === 'fake') {
            // Explicit instances: the container binding depends on the environment, and a fixed provider is what makes the gate deterministic.
            return [new FakeAiProvider, new KeywordModerationClassifier, 'fake'];
        }
        if ($choice !== 'configured') {
            throw new InvalidArgumentException('Unknown provider "'.$choice.'". Use fake or configured.');
        }
        if (! (bool) $this->option('allow-real') && ! (bool) config('ai.eval.allow_real')) {
            throw new RuntimeException('A real-provider run is opt-in: pass --allow-real or set AI_EVAL_ALLOW_REAL (it is never used in CI).');
        }

        $provider = app(AiProviderInterface::class);
        $health = $provider->health();
        if (! $health->healthy) {
            throw new RuntimeException('The configured provider is not usable: '.$health->message);
        }

        return [$provider, app(ModerationClassifier::class), (string) config('ai.provider', 'configured')];
    }

    /** @return array<string, float> */
    private function thresholds(): array
    {
        /** @var array<string, float> $thresholds */
        $thresholds = (array) config('ai.eval.thresholds', []);
        foreach ((array) $this->option('threshold') as $override) {
            [$name, $value] = array_pad(explode('=', (string) $override, 2), 2, '');
            if (! array_key_exists($name, $thresholds) || ! is_numeric($value)) {
                throw new InvalidArgumentException('Invalid --threshold "'.$override.'". Use name=value with one of: '.implode(', ', array_keys($thresholds)));
            }
            $thresholds[$name] = (float) $value;
        }

        return $thresholds;
    }

    /** @param  array<string, mixed>  $report */
    private function summary(array $report, string $path): void
    {
        $this->line('AI evaluation: provider '.$report['provider'].', prompt v'.$report['prompt_version'].', suite '.$report['suite'].', '.$report['cases'].' cases');
        $rows = [];
        foreach ((array) $report['metrics'] as $name => $metric) {
            $rows[] = [$name, $metric['value'] === null ? 'n/a' : $metric['value'].'%', '>= '.$metric['threshold'].'%', $metric['passed_cases'].'/'.$metric['cases'], strtoupper($metric['status'])];
        }
        $latency = (array) $report['latency'];
        $rows[] = ['p95_first_token_ms', ($latency['p95_ms'] ?? 'n/a').' ms', '<= '.$latency['threshold_ms'].' ms', $latency['source'], $latency['passed'] === null ? 'NOT GATED' : ($latency['passed'] ? 'PASS' : 'FAIL')];
        $rows[] = ['arabic_answer_quality', 'manual', '>= 4 of 5', 'human review', 'MANUAL'];
        $this->table(['Metric', 'Score', 'Threshold', 'Cases', 'Status'], $rows);

        foreach ((array) $report['failed_cases'] as $case) {
            $this->warn('Failed case '.$case['id'].': '.implode(', ', (array) $case['failures']));
        }
        $this->line('Report: '.$path);
        $report['result'] === 'pass' ? $this->info('PASS') : $this->error('FAIL: below threshold');
    }
}
