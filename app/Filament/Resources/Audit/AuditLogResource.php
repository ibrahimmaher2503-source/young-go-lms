<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit;

use App\Domain\Audit\AuditLogger;
use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

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
        return parent::getEloquentQuery()->where('log_name', AuditLogger::LOG_NAME);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')->label(__('audit.event')),
                TextColumn::make('causer_id')->label(__('audit.actor')),
                TextColumn::make('subject_type')->label(__('audit.subject')),
                TextColumn::make('subject_id')->label(__('audit.subject_id')),
                TextColumn::make('created_at')->label(__('audit.date'))->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('criteria')->label(__('audit.logs'))->schema([
                    TextInput::make('actor')->label(__('audit.actor'))->integer()->minValue(1),
                    TextInput::make('subject')->label(__('audit.subject'))->maxLength(255),
                    TextInput::make('event')->label(__('audit.event'))->maxLength(255),
                    DatePicker::make('from')->label(__('audit.from')),
                    DatePicker::make('until')->label(__('audit.until')),
                ])->query(static function (Builder $query, array $data): Builder {
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
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
