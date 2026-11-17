<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $disciplines = [
            ['acting', 'التمثيل', 'Acting'],
            ['theatre', 'المسرح', 'Theatre'],
            ['melodrama', 'الميلودراما', 'Melodrama'],
            ['voice-and-dubbing', 'الأداء-الصوتي-والدبلجة', 'Voice Acting and Dubbing'],
            ['drawing', 'الرسم', 'Drawing'],
            ['animation', 'الرسوم-المتحركة', 'Animation'],
            ['creative-skills', 'المهارات-الإبداعية', 'Creative Skills'],
        ];

        foreach ($disciplines as $position => [$slug, $slugAr, $nameEn]) {
            Category::query()->firstOrCreate(['seed_key' => $slug], [
                'name' => ['ar' => str_replace('-', ' ', $slugAr), 'en' => $nameEn],
                'slug_ar' => $slugAr,
                'slug_en' => $slug,
                'sort' => $position,
            ]);
        }
    }
}
