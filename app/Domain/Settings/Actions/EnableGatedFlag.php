<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\PlatformFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

final class EnableGatedFlag
{
    public const string ABILITY = 'enable-gated-platform-flag';

    public function handle(PlatformFlag $flag, User $actor, string $approvalReference): void
    {
        Gate::forUser($actor)->authorize(self::ABILITY);
        $approvalReference = trim($approvalReference);

        if ($approvalReference === '' || mb_strlen($approvalReference) > 255) {
            throw new InvalidArgumentException('Provide an approval reference of up to 255 characters.');
        }

        DB::transaction(function () use ($flag, $actor, $approvalReference): void {
            DB::table('flag_approvals')->insert([
                'flag' => $flag->value,
                'approval_ref' => $approvalReference,
                'approved_by' => $actor->getKey(),
                'at' => now(),
            ]);

            Feature::for(null)->activate($flag->value);
        });

        Log::notice('Gated platform flag enabled.', [
            'flag' => $flag->value,
            'actor_id' => $actor->getKey(),
        ]);
    }
}
