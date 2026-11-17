<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('renders a rich text lesson with mixed Arabic and English content accessibly', function (string $locale, int $width, int $height): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'rl-'.uniqid(), 'slug_en' => 'rl-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $section->update(['published' => true]);
    $text = fn (string $t, array $marks = []) => ['type' => 'text', 'text' => $t, 'marks' => $marks];
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'text']);
    app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'text', 'content' => ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$text('مقدمة عن الضوء — Introduction to light')]],
        ['type' => 'paragraph', 'content' => [$text('الظل الذاتي يظهر على الجسم نفسه، أما '), $text('cast shadow', [['type' => 'bold']]), $text(' فيظهر على السطح المجاور له.')]],
        ['type' => 'callout', 'attrs' => ['tone' => 'tip'], 'content' => [['type' => 'paragraph', 'content' => [$text('Tip: ابدأ بمصدر ضوء واحد. Start with one light source.')]]]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('Value scale — مقياس القيمة')]]]], ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('الحواف الناعمة Soft edges')]]]]]],
        ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [$text('Term')]]]], ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [$text('المعنى')]]]]]], ['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [$text('Highlight')]]]], ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [$text('أفتح منطقة')]]]]]]]],
    ]]]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $learner = User::factory()->create(['locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$item->id}")->resize($width, $height);
    $page->assertSee('Introduction to light');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => [...document.querySelectorAll('[data-rich-content] p')].every(p => p.getAttribute('dir') === 'auto')"))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P3-15');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-15/rich-lesson-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-15', 'A2');
