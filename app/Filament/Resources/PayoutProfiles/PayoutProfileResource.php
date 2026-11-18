<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutProfiles;

use App\Domain\Instructors\Actions\RevealPayoutProfile;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\PayoutMask;
use App\Filament\Resources\PayoutProfiles\Pages\ListPayoutProfiles;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * T-P9-01: finance view of instructor payout profiles (YG-GAP-INS-003). Needs `payout_profile.view`: lists show the instructor,
 * the method and the identifier MASKED. Full details need `payout_profile.reveal`; each reveal is audited (who and which profile, never
 * the values) and is shown once in a notification, never placed in form or table state.
 */
final class PayoutProfileResource extends Resource
{
    protected static ?string $model = PayoutProfile::class;

    protected static ?string $slug = 'payout-profiles';

    public static function getModelLabel(): string
    {
        return __('instructor_onboarding.payout.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('instructor_onboarding.payout.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-building-library';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('payout_profile.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('instructor')->label(__('instructor_onboarding.payout.col_instructor'))->state(static fn (PayoutProfile $r): string => (string) $r->user?->display_name),
                TextColumn::make('method')->label(__('instructor_onboarding.payout.method'))->badge()
                    ->formatStateUsing(static fn (string $state): string => __('instructor_onboarding.payout.method_'.$state)),
                TextColumn::make('identifier')->label(__('instructor_onboarding.payout.col_identifier'))->fontFamily('mono')->state(static fn (PayoutProfile $r): string => PayoutMask::identifier($r)),
                LocalizedDateColumn::make('updated_at')->label(__('instructor_onboarding.payout.col_updated')),
            ])
            ->recordActions([
                Action::make('reveal')->label(__('instructor_onboarding.payout.reveal'))->icon('heroicon-o-eye')
                    ->visible(static fn (): bool => Gate::allows('payout_profile.reveal'))
                    ->requiresConfirmation()->modalDescription(__('instructor_onboarding.payout.reveal_warning'))
                    ->action(static function (PayoutProfile $record): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 403);

                        try {
                            $details = app(RevealPayoutProfile::class)($actor, $record);
                        } catch (RuntimeException) {
                            Notification::make()->danger()->title(__('instructor_onboarding.payout.reveal_failed'))->send();

                            return;
                        }

                        $lines = [];
                        foreach ($details as $key => $value) {
                            $lines[] = __('instructor_onboarding.payout.field_'.$key).': '.$value;
                        }
                        Notification::make()->warning()->persistent()->title(__('instructor_onboarding.payout.revealed'))->body(implode(' | ', $lines))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayoutProfiles::route('/')];
    }
}
