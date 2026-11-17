<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\SubtitleTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/** Short-lived download links tied to one signed-in account: another account cannot reuse them. */
final class SignedFileUrl
{
    public const array TYPES = ['resource' => LessonResource::class, 'document' => LessonCurriculumDocument::class, 'subtitle' => SubtitleTrack::class];

    public function __construct(private readonly PrivateFileAccess $access) {}

    /** Returns null when the user may not open the file, so callers never hand out a link they would then refuse. */
    public function for(User $user, Model $file): ?string
    {
        $type = array_search($file::class, self::TYPES, true);
        if ($type === false) {
            throw new InvalidArgumentException('Unsupported private file.');
        }

        if (! $this->access->allows($user, $file)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'files.download',
            now()->addSeconds(Config::integer('media.signed_ttl')),
            ['type' => $type, 'id' => $file->getKey(), 'u' => $user->getKey()],
        );
    }
}
