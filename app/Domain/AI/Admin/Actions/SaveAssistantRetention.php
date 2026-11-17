<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Retention of AI data (AI spec 18, values OPEN): messages in days, usage metrics in months. Super admin only. */
final class SaveAssistantRetention
{
    public function __construct(private readonly AiSettings $settings, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        $clean = Validator::make($data, [
            'message_retention_days' => ['required', 'integer', 'min:7', 'max:1095'],
            'usage_retention_months' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], (array) __('ai_admin.fields'))->validate();

        $before = ['message_retention_days' => $this->settings->message_retention_days, 'usage_retention_months' => $this->settings->usage_retention_months];

        DB::transaction(function () use ($actor, $clean, $before): void {
            $this->settings->message_retention_days = (int) $clean['message_retention_days'];
            $this->settings->usage_retention_months = (int) $clean['usage_retention_months'];
            $this->settings->save();

            if ($this->audit->record('ai.retention_saved', $actor, (int) $actor->getKey(), $before, [
                'message_retention_days' => $this->settings->message_retention_days, 'usage_retention_months' => $this->settings->usage_retention_months,
            ]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }
}
