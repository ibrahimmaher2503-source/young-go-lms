<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans;

use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\PlanTerm;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The create/edit modal for subscription plans (one form for the header "create" action and the row "edit" action). Every
 * write goes through SavePlan, which re-validates, authorizes and audits. Only the Egypt price is entered for now.
 */
final class PlanForm
{
    public static function configure(Action $action): Action
    {
        $currency = Market::Egypt->currency();

        return $action->schema([
            TextInput::make('name_ar')->label(__('subscriptions.name_ar'))->required()->maxLength(120),
            TextInput::make('name_en')->label(__('subscriptions.name_en'))->required()->maxLength(120),
            Select::make('term')->label(__('subscriptions.term'))->required()->native(false)
                ->options([PlanTerm::Monthly->value => __('subscriptions.term_monthly'), PlanTerm::Annual->value => __('subscriptions.term_annual')]),
            TextInput::make('price')->label(__('subscriptions.price', ['currency' => $currency]))->numeric()->minValue(0.01),
            Select::make('course_ids')->label(__('subscriptions.courses'))->multiple()->searchable()
                ->options(static fn (): array => Course::query()->whereIn('access_mode', [Course::ACCESS_SUBSCRIPTION, Course::ACCESS_BOTH])->orderByDesc('id')->limit(300)->get()
                    ->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
            Select::make('category_ids')->label(__('subscriptions.categories'))->multiple()->searchable()->helperText(__('subscriptions.categories_help'))
                ->options(static fn (): array => Category::query()->orderBy('id')->limit(300)->get()->mapWithKeys(static fn (Category $c): array => [$c->getKey() => (string) $c->name])->all()),
            Toggle::make('is_active')->label(__('subscriptions.active')),
        ])->fillForm(static function (?SubscriptionPlan $record): array {
            if ($record === null) {
                return ['is_active' => false, 'term' => PlanTerm::Monthly->value, 'course_ids' => [], 'category_ids' => []];
            }
            $price = $record->prices()->where('market', Market::Egypt->value)->first();

            return [
                'name_ar' => $record->getTranslation('name', 'ar', false), 'name_en' => $record->getTranslation('name', 'en', false),
                'term' => $record->term->value,
                'price' => $price === null ? null : BrickMoney::ofMinor($price->amount_minor, $price->currency)->getAmount()->toFloat(),
                'is_active' => $record->is_active,
                'course_ids' => $record->courses()->pluck('course_id')->map(static fn ($id): int => (int) $id)->all(),
                'category_ids' => [],
            ];
        })->action(static function (array $data, Action $action, ?SubscriptionPlan $record): void {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 401);
            try {
                $prices = [];
                if (filled($data['price'] ?? null)) {
                    $prices[Market::Egypt->value] = BrickMoney::of((string) $data['price'], Market::Egypt->currency(), roundingMode: RoundingMode::Down)->getMinorAmount()->toInt();
                }
                app(SavePlan::class)($actor, [
                    'name_ar' => (string) $data['name_ar'], 'name_en' => (string) $data['name_en'], 'term' => (string) $data['term'],
                    'is_active' => (bool) ($data['is_active'] ?? false), 'prices' => $prices,
                    'course_ids' => array_values(array_map('intval', $data['course_ids'] ?? [])),
                    'category_ids' => array_values(array_map('intval', $data['category_ids'] ?? [])),
                ], $record);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                $action->halt();

                return;
            }
            Notification::make()->success()->title(__('subscriptions.saved'))->send();
        });
    }
}
