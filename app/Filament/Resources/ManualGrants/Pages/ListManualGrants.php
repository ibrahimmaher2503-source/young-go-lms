<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualGrants\Pages;

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\CourseAccessSummary;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Filament\Resources\ManualGrants\ManualGrantResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListManualGrants extends WorkspaceListRecords
{
    protected static string $resource = ManualGrantResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->grantAction(), $this->exportAction()];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $courseId = $this->tableFilters['course_id']['value'] ?? null;
        $summary = CourseAccessSummary::for(filled($courseId) ? (int) $courseId : null);
        $items = [
            ['label' => __('entitlements.states.active'), 'value' => $summary['active'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('entitlements.states.expired'), 'value' => $summary['expired'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('entitlements.states.revoked'), 'value' => $summary['revoked'], 'icon' => 'heroicon-o-no-symbol', 'tone' => 'info'],
        ];
        foreach ($summary['by_source'] as $source => $counts) {
            $items[] = ['label' => __('entitlements.sources.'.$source), 'value' => $counts['active'].' / '.$counts['expired'].' / '.$counts['revoked'], 'icon' => 'heroicon-o-key'];
        }

        return $items;
    }

    private function grantAction(): Action
    {
        return Action::make('grant')->label(__('entitlements.grant'))->icon('heroicon-o-plus')
            ->schema([
                Select::make('learner_id')->label(__('entitlements.learner'))->required()->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => User::query()->where('status', 'active')->where(fn ($q) => $q->where('email', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')->orWhere('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))->limit(20)->get()->mapWithKeys(static fn (User $u): array => [$u->getKey() => $u->name.' ('.$u->email.')'])->all())
                    ->getOptionLabelUsing(static fn ($value): ?string => User::query()->find($value)?->email),
                Select::make('course_id')->label(__('entitlements.course'))->required()->searchable()
                    ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(200)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
                Toggle::make('lifetime')->label(__('entitlements.lifetime'))->default(true)->live(),
                DatePicker::make('ends_at')->label(__('entitlements.ends'))->native(false)->minDate(now()->addDay())->visible(static fn ($get): bool => ! $get('lifetime'))->required(static fn ($get): bool => ! $get('lifetime')),
                Textarea::make('reason')->label(__('entitlements.reason'))->required()->minLength(3)->maxLength(500),
            ])
            ->action(function (array $data, Action $action): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 401);
                try {
                    app(GrantEntitlement::class)(
                        User::query()->findOrFail($data['learner_id']), Course::query()->findOrFail($data['course_id']), EntitlementSource::ManualGrant,
                        'manual-'.Str::uuid()->toString(), null, ($data['lifetime'] ?? true) ? null : Carbon::parse($data['ends_at'])->endOfDay(), $actor, (string) $data['reason'],
                    );
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('entitlements.granted'))->send();
            });
    }

    private function exportAction(): Action
    {
        return Action::make('export')->label(__('entitlements.export'))->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->action(function (): StreamedResponse {
                $query = $this->getFilteredTableQuery()->reorder('id');

                return response()->streamDownload(function () use ($query): void {
                    $out = fopen('php://output', 'wb');
                    fputcsv($out, ['learner_email', 'course', 'source', 'status', 'starts_at', 'ends_at', 'reason']);
                    $query->chunkById(500, function ($rows) use ($out): void {
                        foreach ($rows as $row) {
                            /** @var Entitlement $row */
                            fputcsv($out, array_map(self::safe(...), [
                                $row->learner->email, (string) $row->course->title, $row->source_type->value, ManualGrantResource::state($row),
                                $row->starts_at->toIso8601String(), $row->ends_at?->toIso8601String() ?? '', (string) $row->reason,
                            ]));
                        }
                    });
                    fclose($out);
                }, 'access-grants-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /** A cell that starts like a spreadsheet formula is prefixed so it cannot run when the file is opened. */
    public static function safe(string $cell): string
    {
        return preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
