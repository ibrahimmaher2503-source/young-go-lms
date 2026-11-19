<?php

declare(strict_types=1);

namespace App\Domain\Migration\Actions;

use App\Models\SeoRedirect;

/**
 * Persists relative same-site 301 redirects into `seo_redirects` idempotently.
 */
final class SyncLegacyRedirects
{
    private const array ALLOWED_STATUSES = [301, 302, 307, 308];

    /**
     * @param  list<array{from_path: string, to_path: string, status_code?: int}>  $entries
     * @return array{created: int, updated: int, unchanged: int, skipped: int}
     */
    public function __invoke(array $entries, bool $dryRun = true): array
    {
        return $this->handle($entries, $dryRun);
    }

    /**
     * @param  list<array{from_path: string, to_path: string, status_code?: int}>  $entries
     * @return array{created: int, updated: int, unchanged: int, skipped: int}
     */
    public function handle(array $entries, bool $dryRun = true): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $from = $this->normalizePath($entry['from_path'] ?? '');
            $to = $this->normalizePath($entry['to_path'] ?? '');
            $status = in_array((int) ($entry['status_code'] ?? 301), self::ALLOWED_STATUSES, true)
                ? (int) ($entry['status_code'] ?? 301)
                : 301;

            if ($from === null || $to === null || rawurldecode($from) === rawurldecode($to)) {
                $skipped++;

                continue;
            }

            $existing = SeoRedirect::query()->where('from_path', $from)->first();
            if ($existing !== null) {
                if ($existing->to_path === $to && (int) $existing->status_code === $status) {
                    $unchanged++;

                    continue;
                }

                if (! $dryRun) {
                    $existing->forceFill(['to_path' => $to, 'status_code' => $status])->save();
                }
                $updated++;

                continue;
            }

            if (! $dryRun) {
                SeoRedirect::query()->create([
                    'from_path' => $from,
                    'to_path' => $to,
                    'status_code' => $status,
                ]);
            }
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
        ];
    }

    private function normalizePath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $normalized = '/'.trim($trimmed, '/');
        if (preg_match('#^/(?!/)#', $normalized) !== 1 || strlen($normalized) > 500) {
            return null;
        }

        return $normalized;
    }
}
