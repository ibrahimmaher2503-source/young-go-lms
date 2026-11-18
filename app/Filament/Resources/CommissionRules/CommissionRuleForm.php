<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionRules;

use App\Domain\InstructorFinance\Actions\SaveCommissionRule;
use App\Domain\InstructorFinance\SaleType;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** The "add rule" modal. Every write goes through SaveCommissionRule, which authorizes, validates and audits. */
final class CommissionRuleForm
{
    public static function configure(Action $action): Action
    {
        $currency = Market::Egypt->currency();

        return $action->schema([
            Select::make('sale_type')->label(__('ledger.sale_type'))->required()->native(false)->live()
                ->options(collect(SaleType::cases())->mapWithKeys(static fn (SaleType $t): array => [$t->value => __('ledger.sale_'.$t->value)])->all()),
            Select::make('instructor_id')->label(__('ledger.instructor'))->searchable()->native(false)->helperText(__('ledger.scope_help'))
                ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
            Select::make('course_id')->label(__('ledger.course'))->searchable()->native(false)
                ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(300)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
            Select::make('calc')->label(__('ledger.calc'))->required()->native(false)->live()
                ->options(['percentage' => __('ledger.calc_percentage'), 'fixed' => __('ledger.calc_fixed')]),
            TextInput::make('percent')->label(__('ledger.percent'))->numeric()->minValue(0)->maxValue(100)->step(0.01)
                ->visible(static fn ($get): bool => $get('calc') === 'percentage' && $get('sale_type') !== SaleType::Subscription->value),
            TextInput::make('amount')->label(__('ledger.fixed_amount', ['currency' => $currency]))->numeric()->minValue(0)
                ->visible(static fn ($get): bool => $get('calc') === 'fixed' || $get('sale_type') === SaleType::Subscription->value),
            DatePicker::make('effective_from')->label(__('ledger.effective_from'))->required(),
            DatePicker::make('effective_to')->label(__('ledger.effective_to')),
            TextInput::make('note')->label(__('ledger.note'))->maxLength(255),
        ])->fillForm(static fn (): array => ['calc' => 'percentage', 'effective_from' => now()->toDateString()])
            ->action(static function (array $data, Action $action) use ($currency): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 401);

                $fixed = ($data['calc'] ?? null) === 'fixed' || ($data['sale_type'] ?? null) === SaleType::Subscription->value;
                try {
                    app(SaveCommissionRule::class)($actor, [
                        'sale_type' => (string) ($data['sale_type'] ?? ''),
                        'instructor_id' => filled($data['instructor_id'] ?? null) ? (int) $data['instructor_id'] : null,
                        'course_id' => filled($data['course_id'] ?? null) ? (int) $data['course_id'] : null,
                        'rate_bps' => ! $fixed && filled($data['percent'] ?? null) ? (int) round(((float) $data['percent']) * 100) : null,
                        'fixed_amount_minor' => $fixed && filled($data['amount'] ?? null) ? BrickMoney::of((string) $data['amount'], $currency, roundingMode: RoundingMode::Down)->getMinorAmount()->toInt() : null,
                        'currency' => $fixed ? $currency : null,
                        'effective_from' => filled($data['effective_from'] ?? null) ? substr((string) $data['effective_from'], 0, 10) : null,
                        'effective_to' => filled($data['effective_to'] ?? null) ? substr((string) $data['effective_to'], 0, 10) : null,
                        'note' => $data['note'] ?? null,
                    ]);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('ledger.rule_saved'))->send();
            });
    }
}
