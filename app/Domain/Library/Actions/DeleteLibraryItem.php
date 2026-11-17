<?php

declare(strict_types=1);

namespace App\Domain\Library\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Library\LibraryAccess;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Removes an item and its file, but never while a lesson still uses it. */
final class DeleteLibraryItem
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, LibraryItem $item): void
    {
        if (! LibraryAccess::canUse($actor, $item)) {
            abort(404); // someone else's private item does not exist for this person
        }
        if (! LibraryAccess::canDelete($actor, $item)) {
            throw new AuthorizationException;
        }

        $path = DB::transaction(function () use ($actor, $item): string {
            $current = LibraryItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $references = $current->referenceCount();
            if ($references > 0) {
                throw ValidationException::withMessages(['item' => __('library.in_use', ['count' => $references])]);
            }

            if ($this->audit->record('library.item_deleted', $current, (int) $actor->getKey(), ['kind' => $current->kind, 'scope' => $current->owner_type]) === null) {
                throw new RuntimeException('Unable to record library audit.');
            }
            $current->delete();

            return $current->path;
        });

        Storage::disk(Config::string('media.disk'))->delete($path);
    }
}
