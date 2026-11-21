<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DemoCourseContentSeeder extends Seeder
{
    public const array FEATURED = ['drawing-basics', 'voice-dubbing', 'animation-lab'];

    private const array CONTENT = [
        'drawing-basics' => ['drawing',
            ['ارسم الأشكال الأساسية بالنسب الصحيحة.', 'Draw basic shapes in proportion.'],
            ['استخدم الضوء والظل لتكوين رسم بسيط.', 'Use light and shade to compose a simple still life.'],
            ['قلم رصاص وممحاة وورق للرسم.', 'A pencil, eraser and drawing paper.'],
        ],
        'kids-art' => ['drawing',
            ['اخلط الألوان الأساسية لتحصل على درجات جديدة.', 'Mix primary colours to make new shades.'],
            ['ارسم مشهدًا ملوّنًا باستخدام أشكال بسيطة.', 'Paint a colourful scene using simple shapes.'],
            ['ألوان قابلة للغسل وورق سميك وفرشاة.', 'Washable paints, thick paper and a brush.'],
        ],
        'acting-101' => ['acting',
            ['حلّل مشهدًا قصيرًا وحدّد هدف الشخصية فيه.', 'Read a short scene and identify what the character wants.'],
            ['سجّل أداءً قصيرًا وراجع اختياراتك في الصوت والحركة.', 'Record a short performance and review your voice and movement choices.'],
            ['هاتف للتصوير ومساحة هادئة للتدريب.', 'A phone for recording and a quiet space to practise.'],
        ],
        'voice-dubbing' => ['voice-and-dubbing',
            ['غيّر النبرة والإيقاع مع الحفاظ على وضوح الكلام.', 'Vary tone and pacing while keeping the words clear.'],
            ['سجّل حوارًا قصيرًا متزامنًا مع الصورة.', 'Record a short line of dialogue in sync with the picture.'],
            ['هاتف أو ميكروفون وسماعات للتسجيل والمراجعة.', 'A phone or microphone and headphones to record and review your audio.'],
        ],
        'stage-workshop' => ['theatre',
            ['استخدم الحركة والصوت لتوضيح هدف الشخصية على المسرح.', 'Use movement and voice to communicate a character’s objective on stage.'],
            ['قدّم مشهدًا قصيرًا وطبّق ملاحظات المدرّب عليه.', 'Perform a short scene and apply feedback from the instructor.'],
            ['مكان هادئ ومساحة للحركة وميكروفون للجلسة المباشرة.', 'A quiet space with room to move and a microphone for the live session.'],
        ],
        'animation-lab' => ['animation',
            ['حوّل فكرة حركة إلى لوحة قصة قصيرة.', 'Turn an idea for a movement into a short storyboard.'],
            ['حرّك شخصية باستخدام أوضاع رئيسية وتوقيت واضح.', 'Animate a character with key poses and clear timing.'],
            ['أداة رسم أو تحريك تدعم العمل إطارًا بإطار.', 'A drawing or animation tool that supports frame-by-frame editing.'],
        ],
        'creative-sub' => ['creative-skills',
            ['استكشف الفكرة نفسها من خلال الرسم والسرد البصري.', 'Explore one idea through drawing and visual storytelling.'],
            ['حوّل ملاحظاتك ورسوماتك الأولية إلى مشروع قصير.', 'Turn notes and rough sketches into a short creative project.'],
            ['دفتر رسم وأقلام أو أداة رسم رقمية.', 'A sketchbook and pencils or a digital drawing tool.'],
        ],
        'indie-comics' => ['creative-skills',
            ['قسّم قصة قصيرة إلى لوحات يسهل تتبّعها.', 'Break a short story into panels that are easy to follow.'],
            ['أنهِ صفحة قصص مصورة بتكوين واضح وحوار موجز.', 'Finish a comic page with clear composition and concise dialogue.'],
            ['ورق وأقلام أو تطبيق رسم يدعم ترتيب اللوحات.', 'Paper and pens or a drawing app for laying out panels.'],
        ],
    ];

    public function run(): void
    {
        DemoGuard::assertAllowed();

        $courses = Course::query()->whereKey(DemoAnchors::ids('courses'))->get();
        if ($courses->isEmpty()) {
            return;
        }

        $admin = User::query()->whereKey(DemoAnchors::ids('users'))
            ->where('email', 'admin@'.config('demo.email_domain'))->firstOrFail();
        $save = app(SaveCourseWizard::class);

        foreach (DemoSeeder::blueprint() as $key => [, $englishTitle]) {
            $path = 'courses/demo-'.$key;
            $course = $courses->first(static fn (Course $candidate): bool => in_array($candidate->cover_image, [$path.'.webp', $path.'.png'], true)
                || ($candidate->cover_image === null && $candidate->getTranslation('title', 'en', false) === $englishTitle));
            if ($course === null) {
                continue;
            }

            $cover = $this->storeCover($key);
            $save($admin, $this->details($key) + ['featured' => in_array($key, self::FEATURED, true)], $course)
                ->update(['cover_image' => $cover]);
        }
    }

    /** @return array<string, mixed> */
    public function details(string $key): array
    {
        [$category, $first, $second, $materials] = self::CONTENT[$key];
        $text = static fn (array $pair): array => ['ar' => $pair[0], 'en' => $pair[1]];
        $categoryId = Category::query()->where('seed_key', $category)->orWhere('slug_en', $category)->value('id');

        return [
            ...($categoryId === null ? [] : ['category_id' => $categoryId]),
            'outcomes' => [['text' => $text($first)], ['text' => $text($second)]],
            'requirements' => [['text' => $text($materials)]],
            'faqs' => [[
                'question' => $text(['ما التطبيق العملي في هذه الدورة؟', 'What will I work on in this course?']),
                'answer' => $text($second),
            ]],
        ];
    }

    public function storeCover(string $key): string
    {
        $path = 'courses/demo-'.$key.'.webp';
        $bytes = file_get_contents(database_path('seeders/fixtures/covers/'.$key.'.webp'));
        if ($bytes === false || ! Storage::disk('public')->put($path, $bytes, 'public')) {
            throw new RuntimeException('Unable to store the demo cover for '.$key.'.');
        }

        return $path;
    }
}
