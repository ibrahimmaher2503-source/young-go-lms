<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Instructors\Actions\SaveInstructorProfile;
use App\Models\Category;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DemoInstructorContentSeeder extends Seeder
{
    private const array PROFILES = [
        'karim' => [
            'account' => 'instructor.indie', 'name' => ['ar' => 'كريم', 'en' => 'Karim'], 'discipline' => 'acting',
            'headline' => ['ar' => 'مدرّب تمثيل', 'en' => 'Acting instructor'],
            'bio' => [
                'ar' => 'يدرّب كريم المتعلّمين على تحليل المشهد وبناء الشخصية. يبدأ بتمارين قصيرة على الصوت والحركة، ثم يساعدهم على تسجيل الأداء ومراجعته لتحديد ما يمكن تحسينه.',
                'en' => 'Karim helps learners analyse a scene and build a character. He starts with short voice and movement exercises, then guides learners through recording and reviewing their performance.',
            ],
        ],
        'sara' => [
            'account' => 'instructor.academy', 'name' => ['ar' => 'سارة', 'en' => 'Sara'], 'discipline' => 'acting',
            'headline' => ['ar' => 'مدرّبة تمثيل', 'en' => 'Acting instructor'],
            'bio' => [
                'ar' => 'تعمل سارة مع المتعلّمين على قراءة الحوار وفهم دوافع الشخصية. تركّز تمارينها على الإصغاء والتفاعل مع الشريك وتقديم المشهد بأكثر من طريقة.',
                'en' => 'Sara works with learners on reading dialogue and understanding what motivates a character. Her exercises focus on listening, responding to a scene partner, and trying different interpretations.',
            ],
        ],
        'lina' => [
            'account' => null, 'name' => ['ar' => 'لينا', 'en' => 'Lina'], 'discipline' => 'voice-and-dubbing',
            'headline' => ['ar' => 'مدرّبة أداء صوتي ودبلجة', 'en' => 'Voice acting and dubbing instructor'],
            'bio' => [
                'ar' => 'تساعد لينا المتعلّمين على التحكم في النبرة والإيقاع ووضوح النطق. تتدرّب معهم على قراءة نصوص قصيرة وتسجيل حوار يواكب حركة الشخصية على الشاشة.',
                'en' => 'Lina helps learners work on tone, pacing and clear delivery. Her exercises include reading short scripts and recording dialogue that follows a character’s movement on screen.',
            ],
        ],
        'omar' => [
            'account' => null, 'name' => ['ar' => 'عمر', 'en' => 'Omar'], 'discipline' => 'animation',
            'headline' => ['ar' => 'مدرّب رسوم متحركة', 'en' => 'Animation instructor'],
            'bio' => [
                'ar' => 'يوضّح عمر كيف تتحوّل فكرة الحركة إلى لقطة قصيرة. يبدأ برسم الوضعيات الأساسية، ثم يجرّب مع المتعلّمين التوقيت والمسافات بين الإطارات حتى تبدو الحركة طبيعية.',
                'en' => 'Omar shows how a movement idea becomes a short animated shot. He starts with key poses, then guides learners through timing and frame spacing to make the motion feel natural.',
            ],
        ],
    ];

    public function run(): void
    {
        DemoGuard::assertAllowed();

        $domain = Config::string('demo.email_domain');
        $demoUsers = DemoAnchors::ids('users');
        $admin = User::query()->whereKey($demoUsers)->where('email', 'admin@'.$domain)->first();
        if ($admin === null) {
            return;
        }

        foreach (self::PROFILES as $key => $content) {
            DB::transaction(function () use ($key, $content, $domain, $demoUsers, $admin): void {
                $account = $content['account'] ?? 'instructor.'.$key;
                $user = User::query()->where('email', $account.'@'.$domain)->first();
                if ($user !== null && ! in_array((int) $user->getKey(), $demoUsers, true)) {
                    return;
                }
                $slug = $key.'-demo';
                $routes = [$slug, $slug.'-ar'];
                $collision = InstructorProfile::query()->where('user_id', '!=', $user?->getKey() ?? 0)
                    ->where(static fn ($query) => $query->whereIn('slug_ar', $routes)->orWhereIn('slug_en', $routes))
                    ->exists();
                if ($collision) {
                    return;
                }

                if ($user === null) {
                    if ($content['account'] !== null) {
                        return;
                    }
                    $user = User::factory()->create([
                        'name' => $content['name']['ar'], 'name_ar' => $content['name']['ar'], 'name_en' => $content['name']['en'],
                        'email' => $account.'@'.$domain, 'password' => Config::string('demo.password'), 'locale' => 'ar', 'status' => 'active',
                    ])->assignRole('instructor_academy');
                    DemoAnchors::mark($user);
                }

                $profile = InstructorProfile::query()->where('user_id', $user->getKey())->first();
                if ($profile !== null && $profile->slug_en !== $slug) {
                    return;
                }

                $updates = [];
                $portrait = 'instructors/demo-'.$key.'.webp';
                $savedPortrait = $profile?->avatar;
                if (blank($savedPortrait) || $savedPortrait === $portrait) {
                    $this->storePortrait($key, $portrait);
                    if (blank($savedPortrait)) {
                        $updates['avatar'] = $portrait;
                    }
                }
                foreach (['headline' => 'Creative instructor', 'bio' => 'Demo instructor profile.'] as $field => $placeholder) {
                    $translations = $profile?->getTranslations($field) ?? [];
                    if (array_diff($translations, ['', $placeholder]) === []) {
                        $updates[$field] = $content[$field];
                    }
                }

                if ($profile === null) {
                    $category = Category::query()->where('seed_key', $content['discipline'])->value('id');
                    $updates += [
                        'user_id' => $user->getKey(), 'slug_ar' => $slug.'-ar', 'slug_en' => $slug,
                        'display_name' => $content['name'], 'status' => InstructorProfile::APPROVED,
                        'disciplines' => $category === null ? [] : [$category],
                    ];
                } elseif ($updates !== []) {
                    $updates['disciplines'] = $profile->disciplines()->allRelatedIds()->all();
                }

                if ($updates !== []) {
                    app(SaveInstructorProfile::class)($admin, $profile, $updates);
                }
            });
        }
    }

    private function storePortrait(string $key, string $portrait): void
    {
        $disk = Storage::disk('public');
        if ($disk->exists($portrait)) {
            return;
        }

        $bytes = file_get_contents(database_path('seeders/fixtures/instructors/'.$key.'.webp'));
        if ($bytes === false || ! $disk->put($portrait, $bytes, 'public')) {
            throw new RuntimeException('Unable to store the demo portrait for '.$key.'.');
        }
    }
}
