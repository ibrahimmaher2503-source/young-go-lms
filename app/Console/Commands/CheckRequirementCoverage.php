<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PhpToken;

final class CheckRequirementCoverage extends Command
{
    private const TASK = 'T-P\d+-(?:\d+|MG)';

    private const REQUIREMENT = '(?:YG-[A-Z0-9]+(?:-[A-Z0-9]+)+|(?:ED|D)-\d+)';

    protected $signature = 'app:req-coverage
        {--task= : Check one task ID}
        {--phase= : Check all tasks in a phase}
        {--refresh : Refresh tagged test paths in the traceability matrix}';

    protected $description = 'Check task acceptance and requirement tags against Pest tests and recorded evidence';

    public function handle(Filesystem $files): int
    {
        $task = $this->option('task');
        $phase = $this->option('phase');
        if (($task === null) === ($phase === null)
            || ($task !== null && ! preg_match('/^'.self::TASK.'$/D', $task))
            || ($phase !== null && ! preg_match('/^P\d+$/D', $phase))) {
            $this->error('Provide exactly one valid --task or --phase.');

            return self::INVALID;
        }

        $tasks = $this->tasks($files, $task, $phase);
        if ($tasks === []) {
            $this->error('No matching task found.');

            return self::INVALID;
        }

        $tests = $this->taggedTests($files);
        $progress = base_path('agents/PROGRESS.md');
        $evidence = $files->exists($progress) ? $files->get($progress) : '';
        $missing = false;
        foreach ($tasks as $id => $items) {
            if ($items === []) {
                $this->error("Task has no numbered acceptance criteria: {$id}");
                $missing = true;

                continue;
            }

            foreach ($items as $item) {
                $key = "{$id}:{$item}";
                if (isset($tests[$id][$item])
                    || preg_match('/^\|\h*'.preg_quote($key, '/').'\h*\|\h*evidence:\h*[^\s|][^|\r\n]*\|/m', $evidence)) {
                    continue;
                }

                $this->error("Missing coverage: {$key}");
                $missing = true;
            }
        }

        if ($this->option('refresh') && ! $this->refresh($files, $tasks, $tests)) {
            return self::FAILURE;
        }

        if (! $missing) {
            $this->info('Requirement coverage passed.');
        }

        return $missing ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, list<string>> */
    private function tasks(Filesystem $files, ?string $task, ?string $phase): array
    {
        $tasks = [];
        foreach ($files->glob(base_path('agents/tasks/P*.md')) as $path) {
            $sections = preg_split('/^### ('.self::TASK.')\b[^\r\n]*\R/m', $files->get($path), flags: PREG_SPLIT_DELIM_CAPTURE);
            for ($index = 1; $index < count($sections ?: []); $index += 2) {
                $id = $sections[$index];
                if ($task === $id || ($phase !== null && str_starts_with($id, "T-{$phase}-"))) {
                    $body = $sections[$index + 1];
                    preg_match('/^- refs:\h*(.*)$/m', $body, $refs);
                    preg_match_all('/\b'.self::REQUIREMENT.'\b/', $refs[1] ?? '', $requirements);
                    preg_match('/^\*\*Acceptance\*\*\h*\R(.*?)(?=^\*\*|\z)/ms', $body, $acceptance);
                    preg_match_all('/^-\h+(?:\[[ xX]\]\h+)?(A\d+)\b/m', $acceptance[1] ?? '', $items);
                    $tasks[$id] = $items[1] === [] ? [] : array_values(array_unique([...$items[1], ...$requirements[0]]));
                }
            }
        }

        return $tasks;
    }

    /** @return array<string, array<string, array<string, true>>> */
    private function taggedTests(Filesystem $files): array
    {
        $tests = [];
        foreach ($files->allFiles(base_path('tests')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $path = 'tests/'.str_replace('\\', '/', $file->getRelativePathname());
            $source = '';
            foreach (PhpToken::tokenize($file->getContents()) as $token) {
                $source .= match (true) {
                    $token->is([T_COMMENT, T_DOC_COMMENT]) => ' ',
                    $token->is(T_CONSTANT_ENCAPSED_STRING) && preg_match('/^[\'"][A-Z0-9:-]+[\'"]$/D', $token->text) !== 1 => "''",
                    $token->is([T_INLINE_HTML, T_ENCAPSED_AND_WHITESPACE]) => '',
                    default => $token->text,
                };
            }

            // shortcut: static literal Pest groups only, extend if the project adopts generated tags.
            preg_match_all('/(?:->\s*group\(\s*(?:[\'"][A-Z0-9:-]+[\'"]\s*,?\s*)+\)\s*)+/', $source, $groups);
            foreach ($groups[0] as $group) {
                preg_match_all('/[\'"]([A-Z0-9:-]+)[\'"]/', $group, $tags);
                $taskIds = preg_grep('/^'.self::TASK.'$/D', $tags[1]) ?: [];
                foreach ($taskIds as $task) {
                    foreach ($tags[1] as $tag) {
                        $item = str_starts_with($tag, "{$task}:") ? substr($tag, strlen($task) + 1) : $tag;
                        $tests[$task][$item][$path] = true;
                    }
                }
            }
        }

        return $tests;
    }

    /**
     * @param  array<string, list<string>>  $tasks
     * @param  array<string, array<string, array<string, true>>>  $tests
     */
    private function refresh(Filesystem $files, array $tasks, array $tests): bool
    {
        $path = base_path('docs/TRACEABILITY_MATRIX.md');
        if (! $files->isFile($path)) {
            $this->error('Traceability matrix missing.');

            return false;
        }

        $contents = $files->get($path);
        $updated = preg_replace_callback(
            '/^\|\h*('.self::REQUIREMENT.')\h*\|([^|\r\n]*)\|([^|\r\n]*)\|[^|\r\n]*\|/m',
            function (array $row) use ($tasks, $tests): string {
                $paths = [];
                preg_match_all('/\b'.self::TASK.'\b/', $row[3], $mapped);
                if (array_intersect($mapped[0], array_keys($tasks)) === []) {
                    return $row[0];
                }

                foreach ($mapped[0] as $task) {
                    $paths += $tests[$task][$row[1]] ?? [];
                }

                if ($paths === []) {
                    return $row[0];
                }

                ksort($paths);
                $tagged = implode(', ', array_map(static fn (string $test): string => "`{$test}`", array_keys($paths)));

                return "| {$row[1]} |{$row[2]}|{$row[3]}| {$tagged} |";
            },
            $contents,
        );

        if ($updated === null || ($updated !== $contents && $files->put($path, $updated) === false)) {
            $this->error('Could not refresh traceability matrix.');

            return false;
        }

        return true;
    }
}
