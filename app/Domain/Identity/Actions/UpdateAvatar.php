<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class UpdateAvatar
{
    public function handle(User $user, string $bytes): void
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['avatar' => __('profile.invalid_avatar')]);
        }

        $path = 'avatars/'.Str::uuid().'.jpg';
        $local = Storage::disk('local');
        $public = Storage::disk('public');
        try {
            if (! $local->put($path, $this->jpeg($image, 1024))
                || ! $public->put($path, $this->jpeg($image, 256))) {
                throw new RuntimeException('Unable to store avatar.');
            }

            $oldPath = DB::transaction(function () use ($user, $path): mixed {
                $profile = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $oldPath = $profile->getAttribute('avatar_path');
                $profile->setAttribute('avatar_path', $path)->save();

                return $oldPath;
            });
        } catch (Throwable $exception) {
            $local->delete($path);
            $public->delete($path);
            throw $exception;
        }

        if (is_string($oldPath) && str_starts_with($oldPath, 'avatars/')) {
            $local->delete($oldPath);
            $public->delete($oldPath);
        }
    }

    private function jpeg(GdImage $source, int $maxEdge): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxEdge / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        if ($target === false) {
            throw new RuntimeException('Unable to create avatar image.');
        }

        imagefill($target, 0, 0, 0xFFFFFF);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        ob_start();
        try {
            if (! imagejpeg($target, quality: 85)) {
                throw new RuntimeException('Unable to encode avatar image.');
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
