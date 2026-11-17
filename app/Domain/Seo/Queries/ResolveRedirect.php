<?php

declare(strict_types=1);

namespace App\Domain\Seo\Queries;

use App\Models\SeoRedirect;

/** Finds the legacy-URL redirect for a request path (ED-018). Only same-site targets are ever returned (no open redirect). */
final class ResolveRedirect
{
    private const STATUSES = [301, 302, 307, 308];

    /** @return array{to: string, status: int}|null */
    public function for(string $path): ?array
    {
        $normalized = '/'.trim($path, '/');
        $candidates = array_values(array_unique([$normalized, rawurldecode($normalized)]));
        $row = SeoRedirect::query()->whereIn('from_path', $candidates)->first();

        if ($row === null || ! preg_match('#^/(?!/)#', $row->to_path) || rawurldecode($row->to_path) === rawurldecode($normalized)) {
            return null;
        }

        return ['to' => $row->to_path, 'status' => in_array($row->status_code, self::STATUSES, true) ? $row->status_code : 301];
    }
}
