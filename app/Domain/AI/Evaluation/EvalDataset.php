<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

use App\Domain\AI\Assistant\StyleTier;
use InvalidArgumentException;

/**
 * The versioned golden and red-team dataset (database/ai-eval): fixture lesson texts and the cases that run against them,
 * in English and Arabic. It is product data: a change to guards, prompts or extraction is judged against it (AI spec 13.4).
 */
final readonly class EvalDataset
{
    public const array SUITES = ['all', 'en', 'ar', 'golden', 'red-team'];

    /**
     * @param  array<string, EvalSource>  $sources
     * @param  list<EvalCase>  $cases
     */
    public function __construct(public array $sources, public array $cases) {}

    public static function load(?string $directory = null): self
    {
        $directory ??= database_path('ai-eval');

        $sources = [];
        foreach (self::read($directory.'/sources.json') as $key => $row) {
            if (! is_array($row) || ! is_array($row['pages'] ?? null) || $row['pages'] === []) {
                throw new InvalidArgumentException("Source {$key} has no pages.");
            }
            $pages = [];
            foreach ($row['pages'] as $number => $text) {
                $pages[(int) $number] = (string) $text;
            }
            $sources[(string) $key] = new EvalSource(
                (string) $key,
                (string) ($row['title'] ?? $key),
                (int) ($row['version'] ?? 1),
                $pages,
                array_values(array_map('strval', (array) ($row['quiz_prompts'] ?? []))),
            );
        }

        $cases = [];
        $seen = [];
        foreach (self::read($directory.'/cases.json') as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Case entry is not an object.');
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                throw new InvalidArgumentException("Case id missing or duplicated: {$id}");
            }
            $seen[$id] = true;

            $category = EvalCategory::tryFrom((string) ($row['category'] ?? ''));
            $tier = StyleTier::tryFrom((string) ($row['tier'] ?? 'adults'));
            $lang = (string) ($row['lang'] ?? '');
            $source = (string) ($row['source'] ?? '');
            $expect = $row['expect'] ?? null;
            if ($category === null || $tier === null || ! in_array($lang, ['en', 'ar'], true) || ! isset($sources[$source]) || ! is_array($expect) || ! isset($expect['outcome'])) {
                throw new InvalidArgumentException("Case {$id} is invalid.");
            }

            $cases[] = new EvalCase($id, $lang, $category, $source, (string) ($row['question'] ?? ''), (string) ($row['locale'] ?? $lang), $tier, array_values((array) ($row['responses'] ?? [])), $expect);
        }

        return new self($sources, $cases);
    }

    /** @return list<EvalCase> */
    public function select(string $suite): array
    {
        if (! in_array($suite, self::SUITES, true) && EvalCategory::tryFrom($suite) === null) {
            throw new InvalidArgumentException("Unknown suite {$suite}. Use one of: ".implode(', ', [...self::SUITES, ...array_map(static fn (EvalCategory $c): string => $c->value, EvalCategory::cases())]));
        }

        return array_values(array_filter($this->cases, static fn (EvalCase $case): bool => match ($suite) {
            'all' => true,
            'en', 'ar' => $case->lang === $suite,
            'golden' => in_array($case->category->metric(), [EvalCategory::GROUNDED, EvalCategory::REFUSAL, EvalCategory::AGE_TIER], true),
            'red-team' => $case->category->metric() === EvalCategory::RED_TEAM,
            default => $case->category->value === $suite,
        }));
    }

    /** @return array<array-key, mixed> */
    private static function read(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = $json === false ? null : json_decode($json, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException("Evaluation dataset file missing or not valid JSON: {$path}");
        }

        return $data;
    }
}
