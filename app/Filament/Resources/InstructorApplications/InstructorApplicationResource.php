<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorApplications;

use App\Domain\Instructors\Actions\ApproveInstructorApplication;
use App\Domain\Instructors\Actions\RejectInstructorApplication;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Filament\Resources\InstructorApplications\Pages\ListInstructorApplications;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-01: admin review queue for "Teach with Young Go" applications (YG-GAP-INS-001). Needs `instructor.manage`. Lists show no
 * phone or ID document; the details modal and the private document link are for reviewers only. Approve and reject go through the
 * shared ApproveRejectAction into the domain Actions (role grant + draft profile on approval; written reason on rejection).
 */
final class InstructorApplicationResource extends Resource
{
    protected static ?string $model = InstructorApplication::class;

    protected static ?string $slug = 'instructor-applications';

    public static function getModelLabel(): string
    {
        return __('instructor_onboarding.application');
    }

    public static function getPluralModelLabel(): string
    {
        return __('instructor_onboarding.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-academic-cap';
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

    public static function table(Table $table): Table
    {
        $colors = ['pending' => 'info', 'approved' => 'success', 'rejected' => 'danger'];

        [$approve, $reject] = ApproveRejectAction::make(
            'instructor.manage',
            'instructor_application_review',
            static fn (Model $record, User $actor, ?string $note) => self::guarded(static fn () => app(ApproveInstructorApplication::class)($actor, self::application($record), $note), 'approved_ok'),
            static fn (Model $record, User $actor, ?string $reason) => self::guarded(static fn () => app(RejectInstructorApplication::class)($actor, self::application($record), (string) $reason), 'rejected_ok'),
        );
        $pending = static fn (InstructorApplication $r): bool => $r->isPending() && Gate::allows('instructor.manage');

        return $table->defaultSort('created_at')
            ->columns([
                TextColumn::make('full_name')->label(__('instructor_onboarding.fields.full_name'))->searchable(),
                TextColumn::make('discipline')->label(__('instructor_onboarding.fields.discipline'))->searchable(),
                TextColumn::make('experience_years')->label(__('instructor_onboarding.fields.experience_years'))->placeholder('-'),
                LocalizedDateColumn::make('created_at')->label(__('instructor_onboarding.col_submitted')),
                StatusBadgeColumn::make('status', $colors)->label(__('instructor_onboarding.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('instructor_onboarding.state_'.($state instanceof ApplicationStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('instructor_onboarding.col_status'))->default(ApplicationStatus::Pending->value)
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(static fn (ApplicationStatus $s): array => [$s->value => __('instructor_onboarding.state_'.$s->value)])->all()),
            ])
            ->recordActions([
                Action::make('details')->label(__('instructor_onboarding.details'))->icon('heroicon-o-eye')
                    ->modalHeading(__('instructor_onboarding.application'))->modalSubmitAction(false)
                    ->modalContent(static fn (InstructorApplication $record): HtmlString => self::details($record)),
                Action::make('document')->label(__('instructor_onboarding.view_id'))->icon('heroicon-o-document-magnifying-glass')
                    ->url(static fn (InstructorApplication $record): string => route('instructor-applications.document', $record), shouldOpenInNewTab: true),
                $approve->visible($pending),
                $reject->visible($pending),
            ]);
    }

    private static function application(Model $record): InstructorApplication
    {
        abort_unless($record instanceof InstructorApplication, 404);

        return $record;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action (the transaction rolls back). */
    private static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('instructor_onboarding.'.$successKey))->send();
    }

    private static function details(InstructorApplication $r): HtmlString
    {
        $rows = [
            __('instructor_onboarding.fields.full_name') => $r->full_name,
            __('instructor_onboarding.fields.phone') => $r->phone ?? '-',
            __('instructor_onboarding.fields.discipline') => $r->discipline,
            __('instructor_onboarding.fields.experience_years') => (string) ($r->experience_years ?? '-'),
            __('instructor_onboarding.fields.bio') => $r->bio,
        ];
        $html = '<dl class="space-y-2">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt class="text-sm text-gray-500">'.e($label).'</dt><dd class="font-semibold whitespace-pre-line" dir="auto">'.e($value).'</dd></div>';
        }
        $html .= '<div><dt class="text-sm text-gray-500">'.e(__('instructor_onboarding.fields.portfolio_links')).'</dt><dd><ul class="list-disc ps-5">';
        foreach ($r->portfolio_links as $link) {
            $html .= '<li dir="ltr"><a class="underline" href="'.e($link).'" target="_blank" rel="noopener noreferrer nofollow">'.e($link).'</a></li>';
        }

        return new HtmlString($html.'</ul></dd></div></dl>');
    }

    public static function getPages(): array
    {
        return ['index' => ListInstructorApplications::route('/')];
    }
}
