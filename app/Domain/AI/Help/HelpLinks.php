<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Models\User;

/** Where a help request opens: the thread for the learner, the queue page for staff (admin panel for admins, instructor panel otherwise). */
final class HelpLinks
{
    public static function learner(int $requestId): string
    {
        return route('help.show', ['helpRequest' => $requestId]);
    }

    public static function staff(User $recipient, int $requestId): string
    {
        $panel = $recipient->can('panel.admin.access') ? 'admin' : 'instructor';

        return url('/'.$panel.'/help-requests/'.$requestId);
    }
}
