<?php

declare(strict_types=1);

use App\Filament\Shared\Forms\TranslatableTabs;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\MoneyColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User as StaffUser;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

enum SharedPresentationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';

    public function getColor(): ?string
    {
        return $this === self::Pending ? 'warning' : null;
    }

    public function getLabel(): string
    {
        return $this === self::Pending ? 'Awaiting review' : 'Cancelled';
    }
}

it('builds one translatable form tab per supported locale', function () {
    $locales = [];
    $tabs = TranslatableTabs::make(static function (string $locale) use (&$locales): array {
        $locales[] = $locale;

        return [TextInput::make("title.{$locale}")];
    });

    expect($tabs)->toBeInstanceOf(Tabs::class)
        ->and($locales)->toBe(['ar', 'en'])
        ->and($tabs->getDefaultChildComponents())->toHaveCount(2)
        ->and($tabs->getDefaultChildComponents()[0])->toBeInstanceOf(Tab::class)
        ->and($tabs->getDefaultChildComponents()[0]->getLabel())->toBe('AR')
        ->and($tabs->getDefaultChildComponents()[0]->getDefaultChildComponents()[0]->getName())->toBe('title.ar')
        ->and($tabs->getDefaultChildComponents()[1]->getLabel())->toBe('EN')
        ->and($tabs->getDefaultChildComponents()[1]->getDefaultChildComponents()[0]->getName())->toBe('title.en');
})->group('T-P1-08', 'A1');

it('renders enum labels and uses enum or supplied colors for status badges', function () {
    $column = StatusBadgeColumn::make('status', ['cancelled' => 'danger']);

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->formatState(SharedPresentationStatus::Pending))->toBe('Awaiting review')
        ->and($column->formatState('pending'))->toBe('Pending');

    $color = (new ReflectionProperty($column, 'color'))->getValue($column);

    expect($color(SharedPresentationStatus::Pending))->toBe('warning')
        ->and($color(SharedPresentationStatus::Cancelled))->toBe('danger')
        ->and($color('cancelled'))->toBe('danger')
        ->and($color('unknown'))->toBe('gray');
})->group('T-P1-08', 'A1');

it('formats minor-unit prices with the shared formatter and each row currency', function () {
    app()->setLocale('en');
    $record = new StaffUser;
    $record->setAttribute('currency', 'EGP');

    expect(MoneyColumn::make('amount_minor')->formatState(123450, $record))
        ->toContain('1,234.50')
        ->toContain('EGP');
})->group('T-P1-08', 'A1');

it('formats dates with the shared helper in the signed-in user timezone', function () {
    app()->setLocale('en');
    $user = new StaffUser;
    $user->setAttribute('timezone', 'Asia/Riyadh');
    Auth::setUser($user);

    $formatted = LocalizedDateColumn::make('starts_at')->formatState(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

    expect($formatted)->toContain('11:00');
})->group('T-P1-08', 'A1');

it('leaves an empty localized date empty', function () {
    expect(LocalizedDateColumn::make('starts_at')->formatState(null))->toBeNull();
})->group('T-P1-08', 'A1');
