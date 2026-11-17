<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons;

use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponType;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The create/edit modal for coupons. The same form serves the header "create" action (no record) and the row "edit" action.
 * Every write goes through SaveCoupon, which re-validates and audits.
 */
final class CouponForm
{
    public static function configure(Action $action): Action
    {
        $currencies = collect(Market::cases())->mapWithKeys(static fn (Market $m): array => [$m->currency() => $m->currency()])->all();
        $isType = static fn (string $type): Closure => static fn ($get): bool => $get('discount_type') === $type;

        return $action->schema([
            TextInput::make('code')->label(__('commerce.coupon_code'))->required()->maxLength(32),
            TextInput::make('name')->label(__('commerce.coupon_name'))->required()->maxLength(120),
            Select::make('discount_type')->label(__('commerce.coupon_type'))->required()->live()
                ->options([CouponType::Percent->value => __('commerce.coupon_type_percent'), CouponType::Fixed->value => __('commerce.coupon_type_fixed')]),
            TextInput::make('percent')->label(__('commerce.coupon_percent'))->numeric()->minValue(1)->maxValue(100)
                ->visible($isType('percent'))->required($isType('percent')),
            TextInput::make('amount')->label(__('commerce.coupon_amount'))->numeric()->minValue(0.01)
                ->visible($isType('fixed'))->required($isType('fixed')),
            Select::make('currency')->label(__('commerce.coupon_currency'))->options($currencies)
                ->visible($isType('fixed'))->required($isType('fixed')),
            DateTimePicker::make('starts_at')->label(__('commerce.coupon_starts'))->native(false),
            DateTimePicker::make('ends_at')->label(__('commerce.coupon_ends'))->native(false),
            TextInput::make('max_redemptions')->label(__('commerce.coupon_max'))->numeric()->minValue(1),
            TextInput::make('max_per_user')->label(__('commerce.coupon_max_user'))->numeric()->minValue(1),
            Select::make('course_ids')->label(__('commerce.coupon_courses'))->multiple()->searchable()->helperText(__('commerce.coupon_all_courses'))
                ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(200)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
            Toggle::make('is_active')->label(__('commerce.coupon_active')),
        ])->fillForm(static function (?Coupon $record): array {
            if ($record === null) {
                return ['discount_type' => 'percent', 'max_per_user' => 1, 'is_active' => true, 'course_ids' => []];
            }

            return [
                'code' => $record->code, 'name' => $record->name, 'discount_type' => $record->discount_type->value, 'percent' => $record->percent,
                'amount' => $record->amount_minor === null ? null : BrickMoney::ofMinor($record->amount_minor, (string) $record->currency)->getAmount()->toFloat(),
                'currency' => $record->currency, 'starts_at' => $record->starts_at, 'ends_at' => $record->ends_at, 'max_redemptions' => $record->max_redemptions,
                'max_per_user' => $record->max_per_user, 'is_active' => $record->is_active, 'course_ids' => $record->courses()->pluck('courses.id')->all(),
            ];
        })->action(static function (array $data, Action $action, ?Coupon $record): void {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 401);
            try {
                $minor = null;
                if (($data['discount_type'] ?? null) === 'fixed' && filled($data['amount'] ?? null) && filled($data['currency'] ?? null)) {
                    $minor = BrickMoney::of((string) $data['amount'], (string) $data['currency'], roundingMode: RoundingMode::Down)->getMinorAmount()->toInt();
                }
                app(SaveCoupon::class)($actor, [
                    'code' => (string) $data['code'], 'name' => (string) $data['name'], 'discount_type' => (string) $data['discount_type'],
                    'percent' => filled($data['percent'] ?? null) ? (int) $data['percent'] : null, 'amount_minor' => $minor, 'currency' => $data['currency'] ?? null,
                    'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
                    'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
                    'max_redemptions' => filled($data['max_redemptions'] ?? null) ? (int) $data['max_redemptions'] : null,
                    'max_per_user' => filled($data['max_per_user'] ?? null) ? (int) $data['max_per_user'] : null,
                    'is_active' => (bool) ($data['is_active'] ?? false), 'course_ids' => array_map('intval', $data['course_ids'] ?? []),
                ], $record);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                $action->halt();

                return;
            }
            Notification::make()->success()->title(__('commerce.coupon_saved'))->send();
        });
    }
}
