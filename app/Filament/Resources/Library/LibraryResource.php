<?php

declare(strict_types=1);

namespace App\Filament\Resources\Library;

use App\Domain\Library\Actions\DeleteLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Filament\Resources\Library\Pages\ListLibraryItems;
use App\Models\LibraryItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/** The content library (ED-026): reusable files that lessons attach by reference. Everyone sees only what they may use. */
final class LibraryResource extends Resource
{
    protected static ?string $model = LibraryItem::class;

    protected static ?string $slug = 'library';

    public static function getModelLabel(): string
    {
        return __('library.item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('library.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && LibraryAccess::mayUseLibrary($user);
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

    /** @return Builder<LibraryItem> */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        return $user instanceof User ? LibraryAccess::visibleTo($user) : LibraryItem::query()->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('library.name'))->state(static fn (LibraryItem $r): string => (string) $r->title)->searchable(query: static fn (Builder $q, string $s): Builder => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%')),
                TextColumn::make('kind')->label(__('library.kind'))->badge()->color('gray')->formatStateUsing(static fn (string $state): string => __('library.kinds.'.$state)),
                TextColumn::make('owner_type')->label(__('library.scope'))->badge()->formatStateUsing(static fn (string $state): string => __('library.scopes.'.$state)),
                TextColumn::make('size')->label(__('library.size'))->formatStateUsing(static fn (int $state): string => Number::fileSize($state, 1)),
                TextColumn::make('tags')->label(__('library.tags'))->state(static fn (LibraryItem $r): string => implode(', ', (array) $r->tags))->placeholder('-'),
                TextColumn::make('uses')->label(__('library.uses'))->state(static fn (LibraryItem $r): int => $r->referenceCount()),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('library.kind'))->options(collect(['video', 'audio', 'document', 'image', 'file'])->mapWithKeys(static fn (string $k): array => [$k => __('library.kinds.'.$k)])->all()),
                SelectFilter::make('owner_type')->label(__('library.scope'))->options(['academy' => __('library.scopes.academy'), 'instructor' => __('library.scopes.instructor')]),
            ])
            ->recordActions([
                Action::make('delete')->label(__('library.delete'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->visible(static fn (LibraryItem $r): bool => ($user = Auth::user()) instanceof User && LibraryAccess::canDelete($user, $r))
                    ->action(static function (LibraryItem $record): void {
                        $user = Auth::user();
                        abort_unless($user instanceof User, 401);
                        try {
                            app(DeleteLibraryItem::class)($user, $record);
                            Notification::make()->success()->title(__('library.deleted'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('library.not_allowed'))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLibraryItems::route('/')];
    }
}
