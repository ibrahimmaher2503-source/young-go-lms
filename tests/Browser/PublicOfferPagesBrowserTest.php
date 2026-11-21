<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Seo\PublicUrl;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps public offers accessible and course links in the browsing language', function (string $locale, string $theme, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $staff = User::factory()->create()->assignRole('admin');
    $courses = [
        Course::query()->create([
            'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital Drawing Basics'],
            'slug_ar' => 'digital-drawing-ar', 'slug_en' => 'digital-drawing',
            'status' => 'published', 'access_mode' => 'subscription_only',
        ]),
        Course::query()->create([
            'title' => ['ar' => 'الأداء الصوتي والدبلجة', 'en' => 'Voice Acting and Dubbing'],
            'slug_ar' => 'voice-dubbing-ar', 'slug_en' => 'voice-dubbing',
            'status' => 'published', 'access_mode' => 'subscription_only',
        ]),
    ];
    app(SavePlan::class)($staff, [
        'name_ar' => 'الاشتراك الشهري', 'name_en' => 'Monthly plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 42500], 'course_ids' => array_map(static fn (Course $course): int => $course->id, $courses),
    ]);
    app(SavePlan::class)($staff, [
        'name_ar' => 'الاشتراك السنوي', 'name_en' => 'Annual plan', 'term' => 'annual', 'is_active' => true,
        'prices' => [Market::Egypt->value => 360000], 'course_ids' => array_map(static fn (Course $course): int => $course->id, $courses),
    ]);
    $bundle = app(SaveBundle::class)($staff, [
        'name_ar' => 'باقة المهارات الإبداعية', 'name_en' => 'Creative Skills Bundle', 'is_active' => true,
        'prices' => [Market::Egypt->value => 69900],
        'items' => array_map(static fn (Course $course): array => ['course_id' => $course->id, 'weight' => null], $courses),
    ]);

    $directory = base_path('tests/Browser/screenshots/T-P8-12/public-release');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    foreach (['plans' => '/'.$locale.'/plans', 'bundle' => '/'.$locale.'/bundles/'.$bundle->id] as $kind => $url) {
        $page = $this->visit($url);
        if ($theme === 'dark') {
            $page = $page->inDarkMode();
        }
        $page->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; }');
        $offer = $kind === 'plans' ? '[data-plan]:first-child' : '[data-bundle-page]';
        $page->assertVisible("main [data-{$kind}-page]")
            ->assertVisible($offer." a[data-cta='sign-in']")
            ->assertVisible($offer.' a[href="'.PublicUrl::course($courses[0], $locale).'"]')
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        expect($page->script(<<<'JS'
            () => Array.from(document.querySelectorAll('main a[data-cta], main ul a[href]')).every(link => link.getBoundingClientRect().height >= 44)
            JS))->toBeTrue();
        $page->screenshot(fullPage: true, filename: "T-P8-12/public-release/{$kind}-{$locale}-{$theme}-{$width}");
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])->group('T-P8-12', 'T-P8-GAPS', 'A3', 'A4', 'YG-WEB-010')->group('browser');
