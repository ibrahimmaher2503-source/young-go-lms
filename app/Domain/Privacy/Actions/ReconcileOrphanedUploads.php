<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\ProtectedData;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reconciles unreferenced upload files left behind if a storage adapter failed during post-commit cleanup
 * (for example in AnonymizeAccount) or an upload was abandoned before DB commit. Dry run by default.
 */
final class ReconcileOrphanedUploads
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, int> count of orphaned files found (dry run) or deleted (apply), keyed by category */
    public function __invoke(?User $actor = null, bool $apply = false, ?int $graceMinutes = null): array
    {
        $minutes = max(0, $graceMinutes ?? Config::integer('privacy.orphan_grace_minutes', 60));
        $cutoff = now()->subMinutes($minutes)->getTimestamp();
        $placeholder = ltrim(str_replace('\\', '/', Config::string('privacy.placeholder_file', '')), '/');

        $counts = [];
        foreach ($this->targets() as $category => $target) {
            ProtectedData::assertNotProtected([$target['table']]);

            $disk = $target['disk'];
            $directory = $target['directory'];
            if ($directory === '' || ! $disk->directoryExists($directory)) {
                $counts[$category] = 0;

                continue;
            }

            $referenced = $this->referencedPaths($target['table'], $target['column']);
            $counts[$category] = $this->reconcileDirectory($disk, $directory, $referenced, $placeholder, $cutoff, $minutes, $apply);
        }

        $total = array_sum($counts);
        Log::info('privacy.storage_reconciled', [
            'mode' => $apply ? 'apply' : 'dry_run',
            'grace_minutes' => $minutes,
            'total' => $total,
            'counts' => $counts,
        ]);

        if ($apply && $total > 0 && $actor instanceof User) {
            $this->audit->record(
                'privacy.retention_pruned',
                $actor,
                (int) $actor->getKey(),
                null,
                ['data_class' => 'orphaned_uploads', 'removed' => $total],
                $counts,
            );
        }

        return $counts;
    }

    /** @return array<string, array{disk: Filesystem, directory: string, table: string, column: string}> */
    private function targets(): array
    {
        $media = Config::string('media.disk');
        $mediaDisk = Storage::disk($media);
        $publicDisk = Storage::disk('public');
        $applicationsDir = trim(str_replace('\\', '/', Config::string('instructor_onboarding.id_document_directory', 'instructor-applications')), '/');

        return [
            'submission_files' => [
                'disk' => $mediaDisk,
                'directory' => 'submissions',
                'table' => 'submission_files',
                'column' => 'path',
            ],
            'instructor_applications' => [
                'disk' => $mediaDisk,
                'directory' => $applicationsDir !== '' ? $applicationsDir : 'instructor-applications',
                'table' => 'instructor_applications',
                'column' => 'id_document_path',
            ],
            'avatars_private' => [
                'disk' => $mediaDisk,
                'directory' => 'avatars',
                'table' => 'users',
                'column' => 'avatar_path',
            ],
            'avatars_public' => [
                'disk' => $publicDisk,
                'directory' => 'avatars',
                'table' => 'users',
                'column' => 'avatar_path',
            ],
        ];
    }

    /** @return array<string, int> */
    private function referencedPaths(string $table, string $column): array
    {
        $paths = [];
        foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column) as $raw) {
            $normalized = ltrim(str_replace('\\', '/', (string) $raw), '/');
            if ($normalized !== '') {
                $paths[$normalized] = 1;
            }
        }

        return $paths;
    }

    /** @param array<string, int> $referenced */
    private function reconcileDirectory(
        Filesystem $disk,
        string $directory,
        array $referenced,
        string $placeholder,
        int $cutoff,
        int $graceMinutes,
        bool $apply,
    ): int {
        $count = 0;
        foreach ($disk->allFiles($directory) as $file) {
            $normalized = ltrim(str_replace('\\', '/', $file), '/');
            $base = basename($normalized);
            if ($normalized === '' || $base === '.gitignore' || $base === '.DS_Store' || $normalized === $placeholder) {
                continue;
            }
            if (isset($referenced[$normalized])) {
                continue;
            }
            if ($graceMinutes > 0 && $disk->lastModified($file) > $cutoff) {
                continue;
            }

            if (! $apply) {
                $count++;

                continue;
            }

            try {
                if ($disk->delete($file)) {
                    $count++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $count;
    }
}
