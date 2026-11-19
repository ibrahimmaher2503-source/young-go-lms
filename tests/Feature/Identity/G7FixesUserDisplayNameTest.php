<?php

declare(strict_types=1);

// T-G7-FIXES-A D8 and T-G7-FIXES-C item 4 | User::display_name follows the UI locale and never writes the raw column

use App\Models\User;

/** An unsaved user with the given name columns; the accessor needs no database. */
function g7User(array $attributes): User
{
    return (new User)->forceFill($attributes);
}

it('uses the explicit display name in every locale', function (string $locale): void {
    app()->setLocale($locale);
    $user = g7User(['name' => 'Account', 'name_ar' => 'ليلى', 'name_en' => 'Layla Demo', 'display_name' => 'Chosen Name']);

    expect($user->display_name)->toBe('Chosen Name');
})->with(['en', 'ar']);

it('shows the English name in English and the Arabic name in Arabic when both are set', function (): void {
    $user = g7User(['name' => 'Account', 'name_ar' => 'ليلى', 'name_en' => 'Layla Demo', 'display_name' => null]);

    app()->setLocale('en');
    expect($user->display_name)->toBe('Layla Demo');

    app()->setLocale('ar');
    expect($user->display_name)->toBe('ليلى');

    app()->setLocale('en');
});

it('falls back to the other locale name when only one language name is set', function (): void {
    $arOnly = g7User(['name' => 'Account', 'name_ar' => 'ليلى', 'name_en' => null, 'display_name' => null]);
    $enOnly = g7User(['name' => 'Account', 'name_ar' => null, 'name_en' => 'Layla Demo', 'display_name' => null]);

    app()->setLocale('en');
    expect($arOnly->display_name)->toBe('ليلى')
        ->and($enOnly->display_name)->toBe('Layla Demo');

    app()->setLocale('ar');
    expect($enOnly->display_name)->toBe('Layla Demo');

    app()->setLocale('en');
});

it('falls back to the account name when both language names are empty', function (): void {
    $user = g7User(['name' => 'Account', 'name_ar' => '   ', 'name_en' => '', 'display_name' => null]);

    expect($user->display_name)->toBe('Account');
});

it('returns null when no name is set at all, and never writes the raw display_name column', function (): void {
    $user = g7User(['name' => null, 'name_ar' => null, 'name_en' => null, 'display_name' => null]);

    expect($user->display_name)->toBeNull()
        ->and($user->getRawOriginal('display_name'))->toBeNull();
});
