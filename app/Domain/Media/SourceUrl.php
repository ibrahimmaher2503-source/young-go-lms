<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Illuminate\Validation\ValidationException;

/** Safe parsing of externally supplied media links. Nothing here fetches the URL. */
final class SourceUrl
{
    /**
     * @return array{scheme: string, host: string, path: string, query: string}
     *
     * @throws ValidationException
     */
    public static function https(mixed $value, string $field = 'url'): array
    {
        $fail = static fn () => ValidationException::withMessages([$field => __('media.invalid_url')]);

        if (! is_string($value) || trim($value) === '' || strlen($value) > 2048 || preg_match('/[\s\x00-\x1f]/', $value) === 1) {
            throw $fail();
        }

        $parts = parse_url(trim($value));
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw $fail();
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')
            || (! $isIp && ! str_contains($host, '.'))
            || ($isIp && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw $fail();
        }

        return ['scheme' => 'https', 'host' => $host, 'path' => (string) ($parts['path'] ?? ''), 'query' => (string) ($parts['query'] ?? '')];
    }
}
