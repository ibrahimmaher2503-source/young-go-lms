<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Staff (privacy.manage) finish an approved request: export builds the private archive, deletion anonymizes the account,
 * correction records what was changed (the admin edits the data first; a note is required). Completing an already completed
 * request is a no-op. Deletion runs AnonymizeAccount (own transaction, idempotent) before the request is marked completed.
 */
final class CompleteDataRequest
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BuildDataExport $export,
        private readonly AnonymizeAccount $anonymize,
    ) {}

    public function __invoke(User $actor, DataRequest $request, ?string $note = null): DataRequest
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('privacy.manage')) {
            throw new AuthorizationException;
        }
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 1000);

        $current = DataRequest::query()->findOrFail($request->getKey());
        if ($current->status === DataRequestStatus::Completed) {
            return $current;
        }
        if ($current->status !== DataRequestStatus::Approved) {
            throw ValidationException::withMessages(['request' => __('privacy.error_not_approved')]);
        }
        if ($current->type === DataRequestType::Correction && ($note === null || mb_strlen($note) < 10)) {
            throw ValidationException::withMessages(['note' => __('privacy.error_correction_note')]);
        }

        $subject = User::query()->findOrFail($current->subject_id);
        $archive = null;
        $counts = [];

        if ($current->type === DataRequestType::Export) {
            $archive = ($this->export)($subject);
            $counts = $archive['counts'];
        } elseif ($current->type === DataRequestType::Deletion) {
            $counts = ($this->anonymize)($actor, $subject);
        }

        try {
            return DB::transaction(function () use ($actor, $current, $note, $archive, $counts): DataRequest {
                $locked = DataRequest::query()->lockForUpdate()->findOrFail($current->getKey());
                if ($locked->status !== DataRequestStatus::Approved) {
                    throw ValidationException::withMessages(['request' => __('privacy.error_not_approved')]);
                }

                $attributes = ['status' => DataRequestStatus::Completed->value, 'completed_by' => $actor->getKey(), 'completed_at' => now(), 'completion_note' => $note];
                if ($archive !== null) {
                    $attributes += [
                        'archive_path' => $archive['path'], 'archive_sha256' => $archive['sha256'],
                        'archive_size' => $archive['size'], 'archive_expires_at' => $archive['expires_at'],
                    ];
                }
                $locked->forceFill($attributes)->save();

                if ($this->audit->record('privacy.request_completed', $locked, (int) $actor->getKey(), ['status' => 'approved'], ['status' => 'completed'], [
                    'type' => $locked->type->value, 'note' => $note, 'counts' => $counts,
                    'archive_sha256' => $archive['sha256'] ?? null,
                ]) === null) {
                    throw new RuntimeException('Unable to record privacy request audit.');
                }

                return $locked;
            });
        } catch (Throwable $exception) {
            if ($archive !== null) {
                Storage::disk(Config::string('media.disk'))->delete($archive['path']);
            }

            throw $exception;
        }
    }
}
