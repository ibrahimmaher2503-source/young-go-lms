<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\Guards\GuardKind;
use App\Models\AiUsage;
use App\Models\Lesson;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Usage, cost and error report of the assistant (AI spec 14, 13.6). It reads ai_usage only: counts, tokens, cost, latency and a
 * result code per request. There is no message body, no question, no answer and no student in it (the table holds an opaque hash
 * that this report never selects). Instructor test-console traffic never reaches ai_usage, so it is not in here either.
 */
final class UsageReport
{
    /** Results that mean the learner got a normal outcome. */
    public const array OUTCOMES = ['answered', 'not_in_source', 'out_of_scope', 'cache_hit'];

    /**
     * @return array{
     *     totals: array{requests: int, answered: int, not_in_source: int, out_of_scope: int, cache_hits: int, guard_blocks: int, errors: int, error_rate: float, input_tokens: int, cached_tokens: int, output_tokens: int, cost_minor: int, avg_latency_ms: int, p95_latency_ms: int},
     *     by_result: list<array{result: string, kind: string, requests: int, cost_minor: int}>,
     *     by_day: list<array{day: string, requests: int, errors: int, cost_minor: int}>,
     *     by_model: list<array{provider: string, model: string, requests: int, cost_minor: int, avg_latency_ms: int}>,
     *     by_lesson: list<array{lesson: string, requests: int, errors: int, cost_minor: int}>
     * }
     */
    public function for(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $base = static fn (): Builder => AiUsage::query()->whereBetween('created_at', [$start, $end]);

        $guards = array_map(static fn (GuardKind $kind): string => $kind->value, GuardKind::cases());

        $byResult = [];
        foreach ($base()->select('result', DB::raw('count(*) as requests'), DB::raw('coalesce(sum(cost_minor), 0) as cost'))->groupBy('result')->orderByDesc('requests')->get() as $row) {
            $result = (string) $row->getAttribute('result');
            $byResult[] = [
                'result' => $result,
                'kind' => $this->kind($result, $guards),
                'requests' => (int) $row->getAttribute('requests'),
                'cost_minor' => (int) $row->getAttribute('cost'),
            ];
        }

        $count = static function (array $results) use ($byResult): int {
            return (int) array_sum(array_column(array_filter($byResult, static fn (array $row): bool => in_array($row['result'], $results, true)), 'requests'));
        };
        $requests = (int) array_sum(array_column($byResult, 'requests'));
        $errors = (int) array_sum(array_column(array_filter($byResult, static fn (array $row): bool => $row['kind'] === 'error'), 'requests'));
        $guardBlocks = (int) array_sum(array_column(array_filter($byResult, static fn (array $row): bool => $row['kind'] === 'guard'), 'requests'));

        $sums = $base()->select(
            DB::raw('coalesce(sum(input_tokens), 0) as input_tokens'),
            DB::raw('coalesce(sum(cached_tokens), 0) as cached_tokens'),
            DB::raw('coalesce(sum(output_tokens), 0) as output_tokens'),
            DB::raw('coalesce(sum(cost_minor), 0) as cost'),
        )->first();

        // Latency of real provider calls only: cache hits and blocked inputs have none.
        $latencies = $base()->where('latency_ms', '>', 0)->orderBy('latency_ms')->limit(50000)->pluck('latency_ms')->map(static fn (mixed $value): int => (int) $value)->all();

        return [
            'totals' => [
                'requests' => $requests,
                'answered' => $count(['answered']),
                'not_in_source' => $count(['not_in_source']),
                'out_of_scope' => $count(['out_of_scope']),
                'cache_hits' => $count(['cache_hit']),
                'guard_blocks' => $guardBlocks,
                'errors' => $errors,
                'error_rate' => $requests === 0 ? 0.0 : round($errors * 100 / $requests, 1),
                'input_tokens' => (int) ($sums?->getAttribute('input_tokens') ?? 0),
                'cached_tokens' => (int) ($sums?->getAttribute('cached_tokens') ?? 0),
                'output_tokens' => (int) ($sums?->getAttribute('output_tokens') ?? 0),
                'cost_minor' => (int) ($sums?->getAttribute('cost') ?? 0),
                'avg_latency_ms' => $latencies === [] ? 0 : (int) round(array_sum($latencies) / count($latencies)),
                'p95_latency_ms' => $latencies === [] ? 0 : $latencies[(int) max(0, ceil(count($latencies) * 0.95) - 1)],
            ],
            'by_result' => $byResult,
            'by_day' => $this->byDay($base(), array_merge($guards, self::OUTCOMES)),
            'by_model' => $this->byModel($base()),
            'by_lesson' => $this->byLesson($base(), array_merge($guards, self::OUTCOMES)),
        ];
    }

    /** @param  list<string>  $guards */
    private function kind(string $result, array $guards): string
    {
        return match (true) {
            in_array($result, self::OUTCOMES, true) => 'outcome',
            in_array($result, $guards, true) => 'guard',
            default => 'error',
        };
    }

    /**
     * @param  Builder<AiUsage>  $query
     * @param  list<string>  $notErrors
     * @return list<array{day: string, requests: int, errors: int, cost_minor: int}>
     */
    private function byDay(Builder $query, array $notErrors): array
    {
        $rows = $query->clone()->select(
            DB::raw('date(created_at) as day'),
            DB::raw('count(*) as requests'),
            DB::raw('coalesce(sum(cost_minor), 0) as cost'),
        )->groupBy(DB::raw('date(created_at)'))->orderBy('day')->get();

        $errors = $query->clone()->whereNotIn('result', $notErrors)
            ->select(DB::raw('date(created_at) as day'), DB::raw('count(*) as errors'))->groupBy(DB::raw('date(created_at)'))->pluck('errors', 'day');

        $days = [];
        foreach ($rows as $row) {
            $day = (string) $row->getAttribute('day');
            $days[] = ['day' => $day, 'requests' => (int) $row->getAttribute('requests'), 'errors' => (int) ($errors[$day] ?? 0), 'cost_minor' => (int) $row->getAttribute('cost')];
        }

        return $days;
    }

    /**
     * @param  Builder<AiUsage>  $query
     * @return list<array{provider: string, model: string, requests: int, cost_minor: int, avg_latency_ms: int}>
     */
    private function byModel(Builder $query): array
    {
        $models = [];
        foreach ($query->clone()->select('provider', 'model', DB::raw('count(*) as requests'), DB::raw('coalesce(sum(cost_minor), 0) as cost'), DB::raw('coalesce(avg(nullif(latency_ms, 0)), 0) as latency'))
            ->groupBy('provider', 'model')->orderByDesc('requests')->get() as $row) {
            $models[] = [
                'provider' => (string) $row->getAttribute('provider'), 'model' => (string) ($row->getAttribute('model') ?? ''),
                'requests' => (int) $row->getAttribute('requests'), 'cost_minor' => (int) $row->getAttribute('cost'), 'avg_latency_ms' => (int) round((float) $row->getAttribute('latency')),
            ];
        }

        return $models;
    }

    /**
     * @param  Builder<AiUsage>  $query
     * @param  list<string>  $notErrors
     * @return list<array{lesson: string, requests: int, errors: int, cost_minor: int}>
     */
    private function byLesson(Builder $query, array $notErrors): array
    {
        $rows = $query->clone()->select('lesson_id', DB::raw('count(*) as requests'), DB::raw('coalesce(sum(cost_minor), 0) as cost'))
            ->groupBy('lesson_id')->orderByDesc('cost')->orderByDesc('requests')->limit(10)->get();
        $ids = $rows->pluck('lesson_id')->all();
        $errors = $query->clone()->whereIn('lesson_id', $ids)->whereNotIn('result', $notErrors)
            ->select('lesson_id', DB::raw('count(*) as errors'))->groupBy('lesson_id')->pluck('errors', 'lesson_id');
        $lessons = Lesson::query()->with('item.course')->whereIn('id', $ids)->get()->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->getAttribute('lesson_id');
            $lesson = $lessons->get($id);
            $out[] = [
                'lesson' => $lesson === null ? '#'.$id : trim((string) $lesson->item?->course?->title.' · '.$lesson->title, ' ·'),
                'requests' => (int) $row->getAttribute('requests'),
                'errors' => (int) ($errors[$id] ?? 0),
                'cost_minor' => (int) $row->getAttribute('cost'),
            ];
        }

        return $out;
    }
}
