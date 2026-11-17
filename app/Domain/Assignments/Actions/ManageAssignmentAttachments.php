<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentAccess;
use App\Domain\Assignments\AssignmentFiles;
use App\Domain\Assignments\AssignmentRefusedException;
use App\Domain\Audit\AuditLogger;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

/** The files an instructor attaches to the brief (examples, templates, reference material). Private, opened only through signed links. */
final class ManageAssignmentAttachments
{
    public const int MAX = 10;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, string> $title ar/en title; the file name is used when empty */
    public function add(User $actor, Assignment $assignment, File $file, array $title = []): AssignmentAttachment
    {
        AssignmentAccess::authorize($actor, $assignment);
        if ($assignment->attachments()->count() >= self::MAX) {
            throw ValidationException::withMessages(['file' => __('assignment.errors.too_many_attachments', ['max' => self::MAX])]);
        }

        try {
            $stored = AssignmentFiles::store($file, array_keys((array) Config::get('assignments.kinds')), (int) Config::get('assignments.ceiling_kilobytes'), 'assignments/'.$assignment->getKey());
        } catch (AssignmentRefusedException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $title = array_filter(array_map(static fn ($v): string => trim((string) $v), $title), static fn (string $v): bool => $v !== '');

        return DB::transaction(function () use ($actor, $assignment, $stored, $title, $file): AssignmentAttachment {
            $attachment = AssignmentAttachment::query()->create([
                'assignment_id' => $assignment->getKey(), 'title' => $title === [] ? ['en' => AssignmentFiles::displayName($file->getFilename())] : $title,
                'path' => $stored['path'], 'mime' => $stored['mime'], 'size' => $stored['size'], 'sha256' => $stored['sha256'],
                'sort' => (int) $assignment->attachments()->max('sort') + 1,
            ]);
            if ($this->audit->record('assignment.attachment_added', $assignment, (int) $actor->getKey(), null, ['attachment_id' => $attachment->getKey(), 'mime' => $stored['mime']]) === null) {
                throw new RuntimeException('Unable to record assignment audit.');
            }

            return $attachment;
        });
    }

    public function remove(User $actor, AssignmentAttachment $attachment): void
    {
        AssignmentAccess::authorize($actor, $attachment->assignment);
        $path = $attachment->path;

        DB::transaction(function () use ($actor, $attachment): void {
            $attachment->delete();
            if ($this->audit->record('assignment.attachment_removed', $attachment->assignment, (int) $actor->getKey(), ['attachment_id' => $attachment->getKey()]) === null) {
                throw new RuntimeException('Unable to record assignment audit.');
            }
        });
        Storage::disk(Config::string('media.disk'))->delete($path);
    }
}
