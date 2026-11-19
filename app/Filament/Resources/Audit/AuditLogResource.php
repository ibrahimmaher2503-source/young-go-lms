<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit;

use App\Domain\Audit\AuditLogger;
use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

final class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    public static function getModelLabel(): string
    {
        return __('audit.log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit.logs');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('audit.view');
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('log_name', AuditLogger::LOG_NAME)->with('causer');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')->label(__('audit.event'))->badge()->color('gray')->formatStateUsing(static fn (?string $state): string => self::eventLabel($state)),
                TextColumn::make('causer_id')->label(__('audit.actor'))->formatStateUsing(static fn ($state, Activity $record): string => $record->causer instanceof User ? (string) $record->causer->display_name : __('staff.system_actor')),
                TextColumn::make('subject_type')->label(__('audit.subject'))->formatStateUsing(static fn (?string $state): string => self::subjectLabel($state)),
                TextColumn::make('subject_id')->label(__('audit.subject_id')),
                TextColumn::make('created_at')->label(__('audit.date'))->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('criteria')->label(__('audit.logs'))->schema([
                    TextInput::make('actor')->label(__('audit.actor_id'))->integer()->minValue(1),
                    Select::make('subject')->label(__('audit.subject'))->options(static fn (): array => self::getEloquentQuery()
                        ->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')
                        ->mapWithKeys(static fn (string $type): array => [$type => self::subjectLabel($type)])->all()),
                    Select::make('event')->label(__('audit.event'))->options(static fn (): array => Activity::query()->where('log_name', AuditLogger::LOG_NAME)->whereNotNull('event')->distinct()->limit(200)->pluck('event')->mapWithKeys(static fn (string $event): array => [$event => self::eventLabel($event)])->all()),
                    DatePicker::make('from')->label(__('audit.from')),
                    DatePicker::make('until')->label(__('audit.until')),
                ])->indicateUsing(static function (array $data): array {
                    $indicators = [];

                    foreach (['actor' => 'actor_id', 'subject' => 'subject', 'event' => 'event', 'from' => 'from', 'until' => 'until'] as $field => $label) {
                        $value = $data[$field] ?? null;
                        if (is_scalar($value) && filled($value)) {
                            $display = match ($field) {
                                'subject' => self::subjectLabel((string) $value),
                                'event' => self::eventLabel((string) $value),
                                default => (string) $value,
                            };
                            $indicators[] = Indicator::make(__('audit.'.$label).' · '.$display)->removeField($field);
                        }
                    }

                    return $indicators;
                })->query(static function (Builder $query, array $data): Builder {
                    foreach (['actor' => 'causer_id', 'subject' => 'subject_type', 'event' => 'event'] as $key => $column) {
                        if (filled($data[$key] ?? null)) {
                            $query->where($column, $data[$key]);
                        }
                    }

                    if (filled($data['from'] ?? null)) {
                        $query->whereDate('created_at', '>=', $data['from']);
                    }
                    if (filled($data['until'] ?? null)) {
                        $query->whereDate('created_at', '<=', $data['until']);
                    }

                    return $query;
                }),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(static fn (Action $action): Action => $action->button()->outlined()->label(__('staff.filter')))
            ->filtersResetAction(static fn (Action $action): Action => $action->color('gray')->outlined())
            ->emptyStateHeading(__('audit.empty'))
            ->emptyStateDescription(static fn (ListAuditLogs $livewire): ?string => array_filter($livewire->getTableFilterState('criteria') ?? [], filled(...)) !== []
                ? __('audit.empty_filtered_hint') : null);
    }

    private static function subjectLabel(?string $type): string
    {
        return match ($type) {
            User::class => __('staff.user'),
            Role::class => __('staff.roles'),
            Course::class => __('courses.course'),
            null => __('audit.record'),
            default => __('audit.typed_record', ['type' => class_basename($type)]),
        };
    }

    private static function eventLabel(?string $event): string
    {
        return Lang::has('audit-events.'.$event) ? __('audit-events.'.$event) : __('audit.other_event');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
