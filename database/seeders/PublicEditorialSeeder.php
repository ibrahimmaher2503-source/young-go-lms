<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PublicEditorialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsPageSeeder::class);

        DB::transaction(function (): void {
            $disciplineKeys = ['acting', 'theatre', 'melodrama', 'voice-and-dubbing', 'drawing', 'animation', 'creative-skills'];
            $disciplines = Category::query()->whereNull('parent_id')->where(function ($query) use ($disciplineKeys): void {
                $query->whereIn('seed_key', $disciplineKeys)->orWhereIn('slug_en', $disciplineKeys);
            })->lockForUpdate()->get();
            foreach ($disciplines as $category) {
                $key = in_array($category->seed_key, $disciplineKeys, true) ? $category->seed_key : $category->slug_en;
                foreach (['ar', 'en'] as $locale) {
                    if (blank($category->getTranslation('description', $locale, false))) {
                        $category->setTranslation('description', $locale, (string) trans('public-editorial.disciplines.'.$key, [], $locale));
                    }
                }
                if ($category->isDirty()) {
                    $category->save();
                }
            }

            foreach (['about', 'faq', 'contact'] as $key) {
                $page = CmsPage::query()->where('seed_key', $key)->lockForUpdate()->firstOrFail();
                foreach (['ar', 'en'] as $locale) {
                    if ($page->getTranslation('body', $locale, false) !== trans('cms.placeholder', [], $locale)) {
                        continue;
                    }

                    $page->setTranslation('body', $locale, (string) trans('public-editorial.pages.'.$key.'.body', [], $locale));
                    if (blank($page->getTranslation('seo_description', $locale, false))) {
                        $page->setTranslation('seo_description', $locale, (string) trans('public-editorial.pages.'.$key.'.description', [], $locale));
                    }
                }
                if ($page->isDirty()) {
                    $page->save();
                }
            }

            $policies = CmsPage::query()->whereIn('seed_key', ['privacy', 'terms', 'refund'])
                ->where('status', CmsPage::PUBLISHED)->lockForUpdate()->get();
            foreach ($policies as $policy) {
                if ($policy->getTranslation('body', 'ar', false) === trans('cms.placeholder', [], 'ar')
                    && $policy->getTranslation('body', 'en', false) === trans('cms.placeholder', [], 'en')) {
                    $policy->update(['status' => CmsPage::DRAFT]);
                }
            }

            foreach ([
                'choose-a-creative-course' => 'اختيار-دورة-إبداعية',
                'first-sketch-with-simple-shapes' => 'رسم-بالأشكال-البسيطة',
                'voice-reading-practice' => 'تمرين-الإلقاء-الصوتي',
            ] as $english => $arabic) {
                if (BlogPost::query()->where('slug_en', $english)->orWhere('slug_ar', $arabic)->exists()) {
                    continue;
                }

                $copy = [];
                foreach (['title', 'excerpt', 'body'] as $field) {
                    foreach (['ar', 'en'] as $locale) {
                        $copy[$field][$locale] = (string) trans('public-editorial.articles.'.$english.'.'.$field, [], $locale);
                    }
                }

                BlogPost::query()->create($copy + [
                    'slug_en' => $english,
                    'slug_ar' => $arabic,
                    'status' => BlogPostStatus::Draft,
                    'seo_description' => $copy['excerpt'],
                ]);
            }
        });
    }
}
