<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageBlock;
use Illuminate\Database\Seeder;

final class HomepageBlockSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['hero', 'categories', 'featured_courses', 'how_it_works', 'free_courses', 'instructors', 'blog', 'newsletter'];

        foreach ($types as $index => $type) {
            HomepageBlock::query()->firstOrCreate(['type' => $type], [
                'title' => ['ar' => __('public-site.'.$type, [], 'ar'), 'en' => __('public-site.'.$type, [], 'en')],
                'body' => $type === 'hero' ? ['ar' => __('public-site.hero_body', [], 'ar'), 'en' => __('public-site.hero_body', [], 'en')] : null,
                'sort' => ($index + 1) * 10,
                'visible' => true,
            ]);
        }
    }
}
