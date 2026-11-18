<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorAgreements;

use App\Domain\Instructors\Actions\PublishInstructorAgreement;
use App\Domain\Instructors\Actions\SaveInstructorAgreement;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Filament\Resources\InstructorAgreements\Pages\ListInstructorAgreements;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-01: versioned instructor agreements (YG-GAP-INS-002). Needs `instructor.manage`. Drafts are editable; publishing makes the
 * version immutable and requires every independent instructor to accept it before creating new courses. The legal wording is OPEN:
 * the seeded text is a draft placeholder and must be replaced by the approved wording as a new version.
 */
final class InstructorAgreementResource extends Resource
{
    protected static ?string $model = InstructorAgreement::class;

    protected static ?string $slug = 'instructor-agreements';

    public static function getModelLabel(): string
    {
        return __('instructor_onboarding.agreement.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('instructor_onboarding.agreement.plural');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('instructor.manage');
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

    /** @return list<Component|Field> */
    public static function agreementFields(bool $withVersion): array
    {
        $fields = [];
        if ($withVersion) {
            $fields[] = TextInput::make('version')->label(__('instructor_onboarding.agreement.version'))->helperText(__('instructor_onboarding.agreement.version_help'))
                ->required()->maxLength(64)->regex('/^[a-z0-9][a-z0-9._-]{0,62}$/');
        }
        $fields[] = TranslatableTabs::make(static fn (string $locale): array => [
            TextInput::make('title.'.$locale)->label(__('instructor_onboarding.agreement.title'))->required()->maxLength(160),
            Textarea::make('body.'.$locale)->label(__('instructor_onboarding.agreement.body'))->required()->rows(14)->maxLength(20000),
        ]);

        return $fields;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action. */
    public static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('instructor_onboarding.agreement.'.$successKey))->send();
    }

    public static function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public static function newAction(): Action
    {
        return Action::make('new_version')->label(__('instructor_onboarding.agreement.new'))->icon('heroicon-o-plus')
            ->visible(static fn (): bool => Gate::allows('instructor.manage'))
            ->modalHeading(__('instructor_onboarding.agreement.new'))
            ->schema(self::agreementFields(true))
            ->action(static fn (array $data) => self::guarded(static fn () => app(SaveInstructorAgreement::class)(self::actor(), null, $data), 'saved'));
    }

    public static function table(Table $table): Table
    {
        $draft = static fn (InstructorAgreement $r): bool => ! $r->isPublished() && Gate::allows('instructor.manage');

        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('version')->label(__('instructor_onboarding.agreement.version'))->fontFamily('mono')->searchable(),
                TextColumn::make('title')->label(__('instructor_onboarding.agreement.title'))->state(static fn (InstructorAgreement $r): string => (string) $r->getTranslation('title', app()->getLocale())),
                TextColumn::make('status')->label(__('instructor_onboarding.col_status'))->badge()
                    ->formatStateUsing(static fn (string $state): string => __('instructor_onboarding.agreement.state_'.$state))
                    ->color(static fn (string $state): string => $state === InstructorAgreement::PUBLISHED ? 'success' : 'gray'),
                LocalizedDateColumn::make('published_at')->label(__('instructor_onboarding.agreement.published_at')),
            ])
            ->recordActions([
                Action::make('edit')->label(__('instructor_onboarding.agreement.edit'))->icon('heroicon-o-pencil-square')->visible($draft)
                    ->modalHeading(__('instructor_onboarding.agreement.edit'))
                    ->schema(self::agreementFields(false))
                    ->fillForm(static fn (InstructorAgreement $record): array => ['title' => $record->getTranslations('title'), 'body' => $record->getTranslations('body')])
                    ->action(static fn (InstructorAgreement $record, array $data) => self::guarded(static fn () => app(SaveInstructorAgreement::class)(self::actor(), $record, $data), 'saved')),
                Action::make('publish')->label(__('instructor_onboarding.agreement.publish'))->icon('heroicon-o-check')->color('success')->visible($draft)
                    ->requiresConfirmation()->modalDescription(__('instructor_onboarding.agreement.publish_warning'))
                    ->action(static fn (InstructorAgreement $record) => self::guarded(static fn () => app(PublishInstructorAgreement::class)(self::actor(), $record), 'published_ok')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInstructorAgreements::route('/')];
    }
}
