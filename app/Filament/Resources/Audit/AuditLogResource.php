<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit;

use App\Domain\Audit\AuditLogger;
use App\Domain\Certificates\Certificate;
use App\Domain\Cohorts\Cohort;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\Refund;
use App\Domain\Engagement\CourseReview;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Instructors\Models\InstructorAgreementAcceptance;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\HelpRequest;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
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
                        ->whereNotNull('subject_type')->where('subject_type', '!=', '')->distinct()->orderBy('subject_type')->pluck('subject_type')
                        ->mapWithKeys(static fn (string $type): array => [$type => self::subjectLabel($type)])->all()),
                    Select::make('event')->label(__('audit.event'))->options(static fn (): array => Activity::query()->where('log_name', AuditLogger::LOG_NAME)->whereNotNull('event')->where('event', '!=', '')->distinct()->orderBy('event')->limit(200)->pluck('event')->mapWithKeys(static fn (string $event): array => [$event => self::eventLabel($event)])->all()),
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

    public static function subjectLabel(?string $type): string
    {
        return match ($type) {
            User::class => __('staff.user'),
            Role::class => __('staff.roles'),
            Course::class => __('courses.course'),
            Order::class => __('orders.order'),
            Coupon::class => __('commerce.coupon'),
            Bundle::class => __('bundles.bundle'),
            SubscriptionPlan::class => __('subscriptions.plan'),
            Refund::class => __('refunds.refund'),
            Settlement::class => __('settlements.settlement'),
            CourseReview::class => __('engagement.review'),
            Cohort::class => __('cohorts.cohort'),
            CourseSection::class => __('curriculum.section'),
            CurriculumItem::class => __('curriculum.curriculum'),
            Lesson::class, 'lesson' => __('curriculum.association_lesson'),
            Quiz::class, 'quiz' => __('curriculum.types.quiz'),
            Assignment::class, 'assignment' => __('curriculum.types.assignment'),
            InstructorProfile::class => __('instructors.profile'),
            InstructorAgreement::class => __('instructor_onboarding.agreement.label'),
            InstructorAgreementAcceptance::class => __('audit-events.instructor_agreement.accepted'),
            HelpRequest::class => __('help.queue.request'),
            Certificate::class => __('certificates.staff.column'),
            OrderItem::class => __('orders.lines'),
            Subscription::class => __('subscriptions.subscription'),
            Category::class => __('courses.category'),
            Question::class => __('quiz.prompt'),
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
