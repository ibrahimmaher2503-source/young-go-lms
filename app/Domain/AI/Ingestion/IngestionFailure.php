<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

/** failure_reason is stored as "code" or "code:detail" and turned into a readable sentence only when shown. */
final class IngestionFailure
{
    public static function describe(?string $reason): string
    {
        if ($reason === null || $reason === '') {
            return '';
        }

        [$code, $detail] = array_pad(explode(':', $reason, 2), 2, '');
        $key = 'media.ingest_failure.'.$code;
        $text = __($key, ['pages' => $detail]);

        return $text === $key ? __('media.ingest_failure.unknown') : $text;
    }

    /** @param  list<int>  $pages  @return string like "4-6, 9" */
    public static function ranges(array $pages): string
    {
        sort($pages);
        $parts = [];
        $start = $end = null;
        foreach ($pages as $page) {
            if ($start === null) {
                $start = $end = $page;
            } elseif ($page === $end + 1) {
                $end = $page;
            } else {
                $parts[] = $start === $end ? (string) $start : $start.'-'.$end;
                $start = $end = $page;
            }
        }
        if ($start !== null) {
            $parts[] = $start === $end ? (string) $start : $start.'-'.$end;
        }

        return implode(', ', $parts);
    }
}
