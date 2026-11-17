<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

final class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => ['عن المنصة', 'About'],
            'contact' => ['اتصل بنا', 'Contact'],
            'faq' => ['الأسئلة الشائعة', 'FAQ'],
            'privacy' => ['سياسة الخصوصية', 'Privacy'],
            'terms' => ['الشروط والأحكام', 'Terms'],
            'refund' => ['سياسة الاسترداد', 'Refund'],
        ];

        foreach ($pages as $slug => [$ar, $en]) {
            CmsPage::query()->firstOrCreate(['seed_key' => $slug], [
                'slug' => $slug,
                'template' => in_array($slug, ['privacy', 'terms', 'refund'], true) ? 'policy' : 'standard',
                'title' => ['ar' => $ar, 'en' => $en],
                'body' => [
                    'ar' => __('cms.placeholder', [], 'ar'),
                    'en' => __('cms.placeholder', [], 'en'),
                ],
            ]);
        }
    }
}
