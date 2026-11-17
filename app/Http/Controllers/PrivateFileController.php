<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Media\PrivateFileAccess;
use App\Domain\Media\SignedFileUrl;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Delivers one private file per signed link. The link is tied to the signed-in account that asked for it and the
 * account's access is checked again here, so a forwarded or stale link opens nothing.
 */
final class PrivateFileController
{
    public function __invoke(Request $request, string $type, int $id, PrivateFileAccess $access): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless((int) $request->query('u') === (int) $user->getKey(), 403);

        $class = SignedFileUrl::TYPES[$type] ?? null;
        abort_if($class === null, 404);

        $file = $class::query()->find($id);
        abort_if($file === null, 404);
        abort_unless($access->allows($user, $file), 403);

        $path = $file->path;
        $disk = Storage::disk(Config::string('media.disk'));
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->download($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
