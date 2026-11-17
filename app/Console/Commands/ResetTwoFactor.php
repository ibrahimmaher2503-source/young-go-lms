<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Models\User;
use Illuminate\Console\Command;

/** Operator recovery for a staff member who lost both their device and recovery codes. */
final class ResetTwoFactor extends Command
{
    protected $signature = 'app:two-factor-reset {email : Email of the staff account}';

    protected $description = 'Remove two-factor authentication from an account (operator recovery, audited)';

    public function handle(DisableTwoFactor $disable): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $disable->handle($user, null);
        $this->info('Two-factor authentication removed. The user must enroll again at next sign-in.');

        return self::SUCCESS;
    }
}
