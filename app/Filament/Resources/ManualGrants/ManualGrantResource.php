<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualGrants;

use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Actions\UpdateEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Staff view of every course access grant with manual grant, revoke, filters and export. It lives inside the
 * Entitlements module because only that module may touch the entitlements table (I01).
 */
final class ManualGrantResource extends Resource
{
    protected static ?string $model = Entitlement::class;

    protected static ?string $slug = 'manual-grants';

    public static function getModelLabel(): string
    {
        return __('entitlements.grant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('entitlements.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-key';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('manual_entitlement.grant');
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
        return parent::getEloquentQuery()->with(['learner', 'course']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('learner.name')->label(__('entitlements.learner'))->searchable(),
                TextColumn::make('learner.email')->label(__('entitlements.email'))->searchable(),
                TextColumn::make('course.title')->label(__('entitlements.course'))->state(static fn (Entitlement $r): string => (string) $r->course->title),
                TextColumn::make('source_type')->label(__('entitlements.source'))->badge()->color('gray')->formatStateUsing(static fn (EntitlementSource $state): string => __('entitlements.sources.'.$state->value)),
                TextColumn::make('status')->label(__('entitlements.status'))->badge()->state(static fn (Entitlement $r): string => self::state($r))
                    ->color(static fn (string $state): string => $state === 'active' ? 'success' : 'gray')->formatStateUsing(static fn (string $state): string => __('entitlements.states.'.$state)),
                TextColumn::make('starts_at')->label(__('entitlements.starts'))->dateTime()->sortable(),
                TextColumn::make('ends_at')->label(__('entitlements.ends'))->state(static fn (Entitlement $r): string => $r->ends_at === null ? __('entitlements.lifetime') : $r->ends_at->isoFormat('LLL')),
                TextColumn::make('reason')->label(__('entitlements.reason'))->limit(40)->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('entitlements.status'))->options(__('entitlements.states'))
                    ->query(self::filterByAccessState(...)),
                SelectFilter::make('source_type')->label(__('entitlements.source'))->options(collect(EntitlementSource::cases())->mapWithKeys(static fn (EntitlementSource $s): array => [$s->value => __('entitlements.sources.'.$s->value)])->all()),
                SelectFilter::make('course_id')->label(__('entitlements.course'))->searchable()
                    ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(200)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
            ])
            ->recordActions([
                Action::make('edit')->label(__('entitlements.edit'))->icon('heroicon-o-pencil-square')
                    ->visible(static fn (Entitlement $r): bool => $r->status !== Entitlement::REVOKED)
                    ->modalHeading(__('entitlements.edit'))
                    ->fillForm(static fn (Entitlement $r): array => [
                        'starts_at' => $r->starts_at->toDateString(),
                        'lifetime' => $r->ends_at === null,
                        'ends_at' => $r->ends_at?->toDateString(),
                        'reason' => (string) ($r->reason ?? ''),
                    ])
                    ->schema([
                        DatePicker::make('starts_at')->label(__('entitlements.starts'))->native(false)->required(),
                        Toggle::make('lifetime')->label(__('entitlements.lifetime'))->default(true)->live(),
                        DatePicker::make('ends_at')->label(__('entitlements.ends'))->native(false)->afterOrEqual('starts_at')
                            ->visible(static fn ($get): bool => ! $get('lifetime'))
                            ->required(static fn ($get): bool => ! $get('lifetime')),
                        Textarea::make('reason')->label(__('entitlements.reason'))->required()->minLength(3)->maxLength(500),
                    ])
                    ->action(static function (Entitlement $record, array $data, Action $action): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            app(UpdateEntitlement::class)(
                                $actor,
                                $record,
                                Carbon::parse($data['starts_at'])->startOfDay(),
                                ($data['lifetime'] ?? true) ? null : Carbon::parse($data['ends_at'])->endOfDay()->setMicrosecond(0),
                                (string) $data['reason'],
                            );
                            Notification::make()->success()->title(__('entitlements.updated'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                            $action->halt();
                        }
                    }),
                Action::make('revoke')->label(__('entitlements.revoke'))->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(static fn (Entitlement $r): bool => $r->status === Entitlement::ACTIVE)
                    ->modalHeading(__('entitlements.revoke'))
                    ->schema([Textarea::make('reason')->label(__('entitlements.reason'))->required()->minLength(3)->maxLength(500)])
                    ->action(static function (Entitlement $record, array $data): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            app(RevokeEntitlement::class)($actor, $record, (string) $data['reason']);
                            Notification::make()->success()->title(__('entitlements.revoked'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListManualGrants::route('/')];
    }

    /**
     * @param  Builder<Entitlement>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Entitlement>
     */
    private static function filterByAccessState(Builder $query, array $data): Builder
    {
        $state = $data['value'] ?? null;

        return $query->withAccessState(is_string($state) ? $state : null);
    }

    public static function state(Entitlement $entitlement): string
    {
        return $entitlement->accessState();
    }
}
