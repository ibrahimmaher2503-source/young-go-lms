<?php

declare(strict_types=1);

namespace App\Filament\Shared\Actions;

use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Approve + reject record actions for any review queue. The mutation closure must call
 * the owning feature's Domain Action; reason validation, authorization recheck and audit
 * are handled by RecordReviewDecision.
 */
final class ApproveRejectAction
{
    /**
     * @param  Closure(Model, User, ?string): void  $approve
     * @param  Closure(Model, User, ?string): void  $reject
     * @return array{0: Action, 1: Action}
     */
    public static function make(string $ability, string $eventPrefix, Closure $approve, Closure $reject): array
    {
        if (blank($ability) || blank($eventPrefix)) {
            throw new InvalidArgumentException('A named review ability and audit event prefix are required.');
        }

        return [
            self::build('approve', RecordReviewDecision::APPROVE, $ability, $eventPrefix, $approve)
                ->label(__('common.approve'))->color('success')->icon('heroicon-o-check')
                ->modalHeading(__('common.approve_heading'))
                ->schema([
                    Textarea::make('reason')->label(__('common.review_note'))->maxLength(RecordReviewDecision::REASON_MAX)->rows(3),
                ]),
            self::build('reject', RecordReviewDecision::REJECT, $ability, $eventPrefix, $reject)
                ->label(__('common.reject'))->color('danger')->icon('heroicon-o-x-mark')
                ->modalHeading(__('common.reject_heading'))
                ->schema([
                    Textarea::make('reason')->label(__('common.review_reason'))->required()
                        ->minLength(RecordReviewDecision::REASON_MIN)->maxLength(RecordReviewDecision::REASON_MAX)->rows(4),
                ]),
        ];
    }

    private static function build(string $name, string $decision, string $ability, string $eventPrefix, Closure $mutation): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->authorize(static fn (Model $record): bool => Gate::allows($ability, $record))
            ->action(static function (Model $record, array $data) use ($decision, $ability, $eventPrefix, $mutation): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 403);

                $reason = $data['reason'] ?? null;
                app(RecordReviewDecision::class)->handle(
                    $actor, $record, $ability, $eventPrefix, $decision, is_string($reason) ? $reason : null, $mutation,
                );
            });
    }
}
