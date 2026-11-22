<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates;

use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Filament\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * T-P9-05: the notification template list (event to wording, AR and EN). Admins with `system.settings.manage` edit the
 * wording through SaveNotificationTemplate; nothing else can be changed here (no markup, whitelisted placeholders).
 */
final class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $slug = 'notification-templates';

    public static function getModelLabel(): string
    {
        return __('notification_events.template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notification_events.templates_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-envelope';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('system.settings.manage');
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
        return parent::getEloquentQuery()->whereIn('event_key', array_keys(array_filter(NotificationMatrix::all(), static fn ($row): bool => $row->editable())));
    }

    public static function table(Table $table): Table
    {
        $groups = collect(NotificationMatrix::all())->pluck('group')->unique()->mapWithKeys(static fn (string $g): array => [$g => __('notification_events.groups.'.$g)])->all();
        $eventLabel = static fn (NotificationTemplate $t): string => NotificationMatrix::find($t->event_key)?->label() ?? $t->event_key;
        $groupLabel = static fn (NotificationTemplate $t): string => __('notification_events.groups.'.(NotificationMatrix::find($t->event_key)->group ?? 'account'));
        $category = static fn (NotificationTemplate $t): string => NotificationMatrix::find($t->event_key)?->mandatory === true ? 'mandatory' : 'optional';
        $wording = static fn (NotificationTemplate $t): string => $t->is_active && $t->hasCustomWording() ? 'custom' : 'default';

        return $table->defaultSort('event_key')
            ->columns([
                TextColumn::make('event_key')->label(__('notification_events.col_event'))->state($eventLabel)->wrap()->searchable(['event_key']),
                TextColumn::make('group')->label(__('notification_events.col_group'))->state($groupLabel)->wrap(),
                StatusBadgeColumn::make('category', ['mandatory' => 'warning', 'optional' => 'info'])->label(__('notification_events.col_category'))
                    ->state($category)
                    ->formatStateUsing(static fn (mixed $state): string => __('notification_events.category_'.$state)),
                StatusBadgeColumn::make('source', ['wired' => 'success', 'pending' => 'gray'])->label(__('notification_events.col_source'))
                    ->state(static fn (NotificationTemplate $t): string => NotificationMatrix::find($t->event_key)->status ?? 'wired')
                    ->formatStateUsing(static fn (mixed $state): string => __('notification_events.source_'.$state)),
                StatusBadgeColumn::make('custom', ['custom' => 'success', 'default' => 'gray'])->label(__('notification_events.col_wording'))
                    ->state($wording)
                    ->formatStateUsing(static fn (mixed $state): string => __('notification_events.wording_'.$state)),
            ])
            ->filters([SelectFilter::make('group')->label(__('notification_events.col_group'))->options($groups)
                ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereIn('event_key', array_keys(array_filter(NotificationMatrix::all(), static fn ($row): bool => $row->group === $data['value'])))
                    : $query)])
            ->recordActions([
                NotificationTemplateForm::configure(Action::make('edit')->label(__('notification_events.template_edit'))->icon('heroicon-o-pencil-square')
                    ->modalHeading(static fn (NotificationTemplate $t): string => __('notification_events.template_edit_event', ['event' => $eventLabel($t)]))
                    ->modalDescription(static fn (NotificationTemplate $t): string => __('notification_events.template_edit_context', [
                        'group' => $groupLabel($t),
                        'category' => __('notification_events.category_'.$category($t)),
                        'wording' => __('notification_events.wording_'.$wording($t)),
                    ]))
                    ->modalSubmitActionLabel(__('staff.save_changes'))->stickyModalHeader()->stickyModalFooter()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationTemplates::route('/')];
    }
}
