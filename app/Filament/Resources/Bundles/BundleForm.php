<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles;

use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Bundle;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The create/edit modal for bundles (one form for the header "create" action and the row "edit" action). Every write
 * goes through SaveBundle, which re-validates, authorizes and audits. Only the Egypt price is entered for now.
 */
final class BundleForm
{
    public static function configure(Action $action): Action
    {
        $currency = Market::Egypt->currency();

        return $action->schema([
            TextInput::make('name_ar')->label(__('bundles.name_ar'))->required()->maxLength(120),
            TextInput::make('name_en')->label(__('bundles.name_en'))->required()->maxLength(120),
            TextInput::make('price')->label(__('bundles.price', ['currency' => $currency]))->numeric()->minValue(0.01),
            Repeater::make('items')->label(__('bundles.courses'))->minItems(2)->maxItems(20)->defaultItems(2)->reorderable()
                ->schema([
                    Select::make('course_id')->label(__('bundles.course'))->required()->searchable()->distinct()
                        ->options(static fn (): array => Course::query()->orderByDesc('id')->limit(300)->get()->mapWithKeys(static fn (Course $c): array => [$c->getKey() => (string) $c->title])->all()),
                    TextInput::make('weight')->label(__('bundles.weight'))->numeric()->integer()->minValue(1)->maxValue(1_000_000)->helperText(__('bundles.weight_help')),
                ])->columns(2),
            Toggle::make('is_active')->label(__('bundles.active')),
        ])->fillForm(static function (?Bundle $record): array {
            if ($record === null) {
                return ['is_active' => false, 'items' => [[], []]];
            }
            $price = $record->prices()->where('market', Market::Egypt->value)->first();

            return [
                'name_ar' => $record->getTranslation('name', 'ar', false), 'name_en' => $record->getTranslation('name', 'en', false),
                'price' => $price === null ? null : BrickMoney::ofMinor($price->amount_minor, $price->currency)->getAmount()->toFloat(),
                'is_active' => $record->is_active,
                'items' => $record->items()->get()->map(static fn ($i): array => ['course_id' => $i->course_id, 'weight' => $i->allocation_weight])->all(),
            ];
        })->action(static function (array $data, Action $action, ?Bundle $record): void {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 401);
            try {
                $prices = [];
                if (filled($data['price'] ?? null)) {
                    $prices[Market::Egypt->value] = BrickMoney::of((string) $data['price'], Market::Egypt->currency(), roundingMode: RoundingMode::Down)->getMinorAmount()->toInt();
                }
                app(SaveBundle::class)($actor, [
                    'name_ar' => (string) $data['name_ar'], 'name_en' => (string) $data['name_en'],
                    'is_active' => (bool) ($data['is_active'] ?? false), 'prices' => $prices,
                    'items' => array_values(array_map(static fn (array $row): array => [
                        'course_id' => (int) ($row['course_id'] ?? 0), 'weight' => filled($row['weight'] ?? null) ? (int) $row['weight'] : null,
                    ], $data['items'] ?? [])),
                ], $record);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                $action->halt();

                return;
            }
            Notification::make()->success()->title(__('bundles.saved'))->send();
        });
    }
}
