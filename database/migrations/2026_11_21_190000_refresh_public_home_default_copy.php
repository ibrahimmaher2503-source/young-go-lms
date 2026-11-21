<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array ORIGINAL_SECTIONS = ['hero', 'categories', 'featured_courses', 'how_it_works'];

    private const array NEW_SECTIONS = [
        'free_courses' => ['ar' => 'دورة مجانية', 'en' => 'Free course'],
        'instructors' => ['ar' => 'تعرّف على مدرّبينا', 'en' => 'Meet the instructors'],
        'blog' => ['ar' => 'أفكار وأدلة', 'en' => 'Ideas and guides'],
        'newsletter' => ['ar' => 'تابع الدورات الجديدة أولًا', 'en' => 'Hear about new courses'],
    ];

    private const array COPY = [
        'hero' => [
            'title' => ['ar' => ['مساحة تتعلّم فيها وتبدع', 'مساحة تتعلّم فيها وتبدع بمهاراتك']],
            'body' => [
                'ar' => ['طوّر مهاراتك في الرسم والتمثيل والصوت بخطوات واضحة ومع مدرّبين متخصصين.', 'طوّر مهاراتك في الرسم، التمثيل، الصوت، والمونتاج مع مدرّبين محترفين ودورات عملية وتطبيقية.'],
                'en' => ['Build your skills in drawing, acting and voice with clear steps and specialist instructors.', 'Build your skills in drawing, acting, voice and video editing with expert instructors and hands-on courses.'],
            ],
        ],
        'featured_courses' => ['title' => [
            'ar' => ['دورات مميزة', 'دورات مختارة لك'],
            'en' => ['Featured courses', 'Courses picked for you'],
        ]],
        'how_it_works' => ['title' => [
            'ar' => ['التعلّم أوضح خطوة بخطوة', 'تعلّم بوضوح خطوة بخطوة'],
            'en' => ['Learning made clearer, step by step', 'Learn clearly, step by step'],
        ]],
        'free_courses' => ['title' => [
            'ar' => ['ابدأ بدورة مجانية', 'دورة مجانية'],
            'en' => ['Start with a free course', 'Free course'],
        ]],
        'instructors' => ['title' => ['ar' => ['تعرّف على المدرّبين', 'تعرّف على مدرّبينا']]],
        'newsletter' => ['title' => ['ar' => ['تابع الدورات الجديدة', 'تابع الدورات الجديدة أولًا']]],
    ];

    public function up(): void
    {
        $this->replaceDefaults(from: 0, to: 1);
        $this->addMissingSectionsToLegacyHome();
    }

    public function down(): void
    {
        // Keep added records because staff may have edited them after upgrading.
        $this->replaceDefaults(from: 1, to: 0);
    }

    private function addMissingSectionsToLegacyHome(): void
    {
        $blocks = DB::table('homepage_blocks');
        $types = $blocks->distinct()->pluck('type');
        $upgradeMarkers = array_keys(Arr::except(self::NEW_SECTIONS, 'instructors'));
        if ($types->intersect(self::ORIGINAL_SECTIONS)->count() !== count(self::ORIGINAL_SECTIONS)
            || $types->intersect($upgradeMarkers)->isNotEmpty()) {
            return;
        }

        $sort = (int) DB::table('homepage_blocks')->max('sort');
        $timestamp = now();
        foreach (Arr::except(self::NEW_SECTIONS, $types->all()) as $type => $title) {
            $sort += 10;
            DB::table('homepage_blocks')->insert([
                'type' => $type,
                'title' => json_encode($title, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'sort' => $sort,
                'visible' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function replaceDefaults(int $from, int $to): void
    {
        foreach (DB::table('homepage_blocks')->whereIn('type', array_keys(self::COPY))->lazyById() as $block) {
            $updates = [];
            foreach (self::COPY[$block->type] as $field => $defaults) {
                $translations = json_decode((string) $block->{$field}, true) ?? [];
                $revised = $translations;
                foreach ($defaults as $locale => $copy) {
                    if (($translations[$locale] ?? null) === $copy[$from]) {
                        $revised[$locale] = $copy[$to];
                    }
                }
                if ($revised !== $translations) {
                    $updates[$field] = json_encode($revised, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
            }
            if ($updates !== []) {
                DB::table('homepage_blocks')->where('id', $block->id)->update($updates);
            }
        }
    }
};
