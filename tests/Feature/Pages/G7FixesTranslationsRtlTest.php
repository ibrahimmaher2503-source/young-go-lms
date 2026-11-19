<?php

declare(strict_types=1);

// T-G7-FIXES-F | F12 app:check-translations and app:check-rtl pass, and the new keys exist in AR and EN with matching placeholders

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('F12 app:check-translations passes', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
});

it('F12 app:check-rtl passes', function (): void {
    $this->artisan('app:check-rtl')->assertExitCode(0);
});

it('F12 app:check-brand-tokens still passes after the font and token changes', function (): void {
    $this->artisan('app:check-brand-tokens')->assertExitCode(0);
});

it('F12 the agreement empty-state keys exist in English and Arabic with the same placeholders', function (): void {
    foreach (['none_published', 'none_published_help', 'none_published_contact', 'version', 'draft_notice', 'already_accepted', 'required_notice', 'accept', 'accept_confirm'] as $key) {
        $en = __('instructor_onboarding.agreement.'.$key, [], 'en');
        $ar = __('instructor_onboarding.agreement.'.$key, [], 'ar');

        expect($en)->not->toBe('instructor_onboarding.agreement.'.$key)
            ->and($ar)->not->toBe('instructor_onboarding.agreement.'.$key);

        preg_match_all('/:\w+/', $en, $enPlaceholders);
        preg_match_all('/:\w+/', $ar, $arPlaceholders);
        expect($arPlaceholders[0])->toEqualCanonicalizing($enPlaceholders[0]);
    }
});

it('F12 the teach attribute names used by the validation messages exist in English and Arabic', function (): void {
    foreach (['full_name', 'phone', 'discipline', 'experience_years', 'bio', 'portfolio_links', 'id_document', 'privacy'] as $attribute) {
        expect(__('validation.attributes.'.$attribute, [], 'en'))->not->toBe('validation.attributes.'.$attribute)
            ->and(__('validation.attributes.'.$attribute, [], 'ar'))->not->toBe('validation.attributes.'.$attribute);
    }
});
