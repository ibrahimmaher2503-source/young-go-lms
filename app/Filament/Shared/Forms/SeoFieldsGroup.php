<?php

declare(strict_types=1);

namespace App\Filament\Shared\Forms;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Shared SEO fields for any model with translatable seo_title, seo_description, seo_keywords and a canonical_url column.
 * The course wizard SEO step (T-P3-03) and other public content use it. Saving and validation stay in the owning Action.
 */
final class SeoFieldsGroup
{
    public static function make(bool $canonical = true): Section
    {
        return Section::make(__('course_page.seo.group'))->icon('heroicon-o-magnifying-glass')->schema([
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make("seo_title.{$locale}")->label(__('course_page.seo.title'))->maxLength(160)
                    ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                Textarea::make("seo_description.{$locale}")->label(__('course_page.seo.description'))->maxLength(320)->rows(3)
                    ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                TextInput::make("seo_keywords.{$locale}")->label(__('course_page.seo.keywords'))->helperText(__('course_page.seo.keywords_help'))->maxLength(300)
                    ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
            ]),
            ...($canonical ? [TextInput::make('canonical_url')->label(__('course_page.seo.canonical'))->helperText(__('course_page.seo.canonical_help'))->url()->maxLength(500)
                ->extraInputAttributes(['dir' => 'ltr'])] : []),
        ]);
    }
}
