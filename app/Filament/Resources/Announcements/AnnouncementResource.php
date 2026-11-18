<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements;

use App\Domain\Notifications\Actions\PublishAnnouncement;
use App\Domain\Notifications\Announcement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\Course;
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

/**
 * T-P9-05: course and cohort announcements. Admins see every course; an instructor sees only the courses they teach.
 * Writes and the publish (which notifies the audience once) go through SaveAnnouncement / PublishAnnouncement, which
 * re-check CoursePolicy::update. A published announcement is read-only.
 */
final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $slug = 'announcements';

    public static function getModelLabel(): string
    {
        return __('notification_events.announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notification_events.announcements_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->can('panel.admin.access') || $user->can('panel.instructor.access'));
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
        $user = Auth::user();
        $query = parent::getEloquentQuery()->with(['course', 'cohort', 'author']);
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $user->can('panel.admin.access') ? $query : $query->whereIn('course_id', Course::query()->whereHas('instructors', static fn (Builder $q) => $q->whereKey($user->getKey()))->select('courses.id'));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('notification_events.announcement_title'))->state(static fn (Announcement $a): string => $a->titleIn(app()->getLocale()))->searchable(['title_ar', 'title_en']),
                TextColumn::make('course.title')->label(__('notification_events.announcement_course')),
                TextColumn::make('audience')->label(__('notification_events.announcement_audience'))
                    ->state(static fn (Announcement $a): string => $a->cohort === null ? __('notification_events.audience_course') : $a->cohort->localizedName()),
                StatusBadgeColumn::make('status', ['draft' => 'gray', 'published' => 'success'])->label(__('notification_events.announcement_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('notification_events.announcement_state_'.$state)),
                TextColumn::make('recipients_count')->label(__('notification_events.announcement_recipients')),
                LocalizedDateColumn::make('published_at')->label(__('notification_events.announcement_published_at'))->placeholder('-'),
                TextColumn::make('author.name')->label(__('notification_events.announcement_author'))->placeholder('-'),
            ])
            ->filters([SelectFilter::make('status')->label(__('notification_events.announcement_status'))
                ->options([Announcement::DRAFT => __('notification_events.announcement_state_draft'), Announcement::PUBLISHED => __('notification_events.announcement_state_published')])])
            ->recordActions([
                AnnouncementForm::configure(Action::make('edit')->label(__('notification_events.announcement_edit'))->icon('heroicon-o-pencil-square')
                    ->visible(static fn (Announcement $a): bool => ! $a->isPublished())
                    ->modalHeading(__('notification_events.announcement_edit'))),
                Action::make('publish')->label(__('notification_events.announcement_publish'))->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(static fn (Announcement $a): bool => ! $a->isPublished())
                    ->requiresConfirmation()->modalHeading(__('notification_events.announcement_publish'))->modalDescription(__('notification_events.announcement_publish_confirm'))
                    ->action(static function (Announcement $record): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            $published = app(PublishAnnouncement::class)($actor, $record);
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('notification_events.announcement_forbidden'))->send();

                            return;
                        }
                        Notification::make()->success()->title(__('notification_events.announcement_published', ['count' => $published->recipients_count]))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAnnouncements::route('/')];
    }
}
