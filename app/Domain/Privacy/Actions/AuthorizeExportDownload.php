<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\DataRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Only the person who made the export request may download its archive, and only while it has not expired. Returns the stored
 * path (or null for anyone else / expired, which callers answer with a 404) and records the download.
 */
final class AuthorizeExportDownload
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $user, DataRequest $request): ?string
    {
        if ($user->getAttribute('status') !== 'active' || (int) $request->requested_by !== (int) $user->getKey() || ! $request->hasDownloadableArchive()) {
            return null;
        }

        DB::transaction(function () use ($user, $request): void {
            $request->forceFill(['archive_downloaded_at' => now()])->save();
            if ($this->audit->record('privacy.export_downloaded', $request, (int) $user->getKey(), null, null, ['subject_id' => $request->subject_id]) === null) {
                throw new RuntimeException('Unable to record export download audit.');
            }
        });

        return $request->archive_path;
    }
}
