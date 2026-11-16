<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;

final class UpdatePreferredLocale
{
    public function handle(User $user, string $locale): void
    {
        $user->setAttribute('locale', $locale)->save();
    }
}
