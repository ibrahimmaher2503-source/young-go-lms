<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Domain\Content\Actions\UnsubscribeFromNewsletter;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** T-P2-08 (YG-WEB-008): newsletter subscribers with consent version, source and status, CSV export, staff unsubscribe. */
final class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $slug = 'newsletter-subscribers';

    public static function getModelLabel(): string
    {
        return __('contact.admin.subscriber');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contact.admin.subscribers');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('marketing.lead.view');
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

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('email')->label(__('contact.fields.email'))->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('state')->label(__('contact.admin.status'))->badge()
                ->state(static fn (NewsletterSubscriber $record): string => $record->isSubscribed() ? 'subscribed' : 'unsubscribed')
                ->formatStateUsing(static fn (string $state): string => __('contact.subscriber_statuses.'.$state))
                ->color(static fn (string $state): string => $state === 'subscribed' ? 'success' : 'gray'),
            TextColumn::make('source')->label(__('contact.admin.source'))->formatStateUsing(static fn (string $state): string => __('contact.sources.'.$state)),
            TextColumn::make('locale')->label(__('contact.admin.language'))->formatStateUsing(static fn (string $state): string => strtoupper($state)),
            TextColumn::make('consent_version')->label(__('contact.admin.consent_version'))->extraAttributes(['dir' => 'ltr']),
            LocalizedDateColumn::make('consented_at')->label(__('contact.admin.consented')),
            LocalizedDateColumn::make('unsubscribed_at')->label(__('contact.admin.unsubscribed'))->placeholder('-'),
        ])->filters([
            SelectFilter::make('state')->label(__('contact.admin.status'))
                ->options(['subscribed' => __('contact.subscriber_statuses.subscribed'), 'unsubscribed' => __('contact.subscriber_statuses.unsubscribed')])
                ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'subscribed' => $query->whereNull('unsubscribed_at'),
                    'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
                    default => $query,
                }),
            SelectFilter::make('source')->label(__('contact.admin.source'))
                ->options(collect((array) config('contact.newsletter_sources'))->mapWithKeys(static fn (mixed $s): array => [(string) $s => __('contact.sources.'.$s)])->all()),
        ])->recordActions([
            Action::make('unsubscribe')->label(__('contact.admin.unsubscribe'))->icon('heroicon-o-x-circle')->color('gray')->requiresConfirmation()
                ->visible(static fn (NewsletterSubscriber $record): bool => $record->isSubscribed())
                ->action(static function (NewsletterSubscriber $record): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    app(UnsubscribeFromNewsletter::class)($record, $actor);
                    Notification::make()->success()->title(__('contact.admin.unsubscribed_done'))->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
