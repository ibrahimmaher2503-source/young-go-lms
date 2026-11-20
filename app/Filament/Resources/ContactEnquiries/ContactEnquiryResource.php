<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactEnquiries;

use App\Domain\Content\Actions\AssignContactEnquiry;
use App\Domain\Content\Actions\ChangeContactEnquiryStatus;
use App\Domain\Content\ContactEnquiryStatus;
use App\Domain\Content\Queries\LeadHandlers;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\ContactEnquiry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** T-P2-08 (YG-WEB-007): inbox of public contact enquiries with status, assignee, search and CSV export. */
final class ContactEnquiryResource extends Resource
{
    protected static ?string $model = ContactEnquiry::class;

    protected static ?string $slug = 'contact-enquiries';

    public static function getModelLabel(): string
    {
        return __('contact.admin.enquiry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contact.admin.enquiries');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-envelope';
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
        $colors = [];
        foreach (ContactEnquiryStatus::cases() as $status) {
            $colors[$status->value] = $status->tone();
        }

        return $table->defaultSort('id', 'desc')->modifyQueryUsing(static fn ($query) => $query->with('assignee'))->columns([
            TextColumn::make('name')->label(__('contact.fields.name'))->searchable(),
            TextColumn::make('email')->label(__('contact.fields.email'))->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('message')->label(__('contact.fields.message'))->limit(60)->searchable(),
            StatusBadgeColumn::make('status', $colors)->label(__('contact.admin.status'))
                ->formatStateUsing(static fn (mixed $state): string => $state instanceof ContactEnquiryStatus ? $state->label() : (string) $state),
            TextColumn::make('assignee.name')->label(__('contact.admin.assignee'))->placeholder(__('contact.admin.unassigned')),
            LocalizedDateColumn::make('created_at')->label(__('contact.admin.received')),
        ])->filters([
            SelectFilter::make('status')->label(__('contact.admin.status'))
                ->options(collect(ContactEnquiryStatus::cases())->mapWithKeys(static fn (ContactEnquiryStatus $s): array => [$s->value => $s->label()])->all()),
            SelectFilter::make('assigned_to_id')->label(__('contact.admin.assignee'))
                ->options(static fn (): array => app(LeadHandlers::class)->options()),
        ])->recordActions(CompactActions::group([
            ViewAction::make(),
            Action::make('assign')->label(__('contact.admin.assign'))->icon('heroicon-o-user-plus')
                ->fillForm(static fn (ContactEnquiry $record): array => ['assignee' => $record->assigned_to_id])
                ->schema([
                    Select::make('assignee')->label(__('contact.admin.assignee'))->placeholder(__('contact.admin.unassigned'))
                        ->options(static fn (): array => app(LeadHandlers::class)->options()),
                ])
                ->action(static function (ContactEnquiry $record, array $data): void {
                    $actor = self::actor();
                    $assignee = filled($data['assignee'] ?? null) ? User::query()->find((int) $data['assignee']) : null;
                    try {
                        app(AssignContactEnquiry::class)($actor, $record, $assignee);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                        return;
                    }
                    Notification::make()->success()->title(__('contact.admin.assigned'))->send();
                }),
            Action::make('status')->label(__('contact.admin.change_status'))->icon('heroicon-o-arrow-path')
                ->fillForm(static fn (ContactEnquiry $record): array => ['status' => $record->status->value])
                ->schema([
                    Select::make('status')->label(__('contact.admin.status'))->required()->native(false)
                        ->options(collect(ContactEnquiryStatus::cases())->mapWithKeys(static fn (ContactEnquiryStatus $s): array => [$s->value => $s->label()])->all()),
                ])
                ->action(static function (ContactEnquiry $record, array $data): void {
                    app(ChangeContactEnquiryStatus::class)(self::actor(), $record, ContactEnquiryStatus::from((string) $data['status']));
                    Notification::make()->success()->title(__('contact.admin.status_changed'))->send();
                }),
        ]), CompactActions::POSITION);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('contact.fields.name')),
            TextEntry::make('email')->label(__('contact.fields.email'))->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('phone')->label(__('contact.fields.phone'))->placeholder('-')->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('status')->label(__('contact.admin.status'))
                ->formatStateUsing(static fn (mixed $state): string => $state instanceof ContactEnquiryStatus ? $state->label() : (string) $state),
            TextEntry::make('assignee.name')->label(__('contact.admin.assignee'))->placeholder(__('contact.admin.unassigned')),
            TextEntry::make('created_at')->label(__('contact.admin.received'))->dateTime(),
            TextEntry::make('message')->label(__('contact.fields.message'))->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line break-words']),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactEnquiries::route('/')];
    }

    private static function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
