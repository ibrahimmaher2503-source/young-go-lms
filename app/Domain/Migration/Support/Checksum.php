<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use JsonException;

/**
 * Deterministic SHA-256 checksum over canonicalized legacy source payloads.
 */
final class Checksum
{
    /** @param array<array-key, mixed> $payload */
    public static function of(array $payload): string
    {
        $normalized = self::sortRecursive($payload);

        try {
            $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $encoded = serialize($normalized);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortRecursive(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursive($value);
            }
        }

        return $data;
    }
}
