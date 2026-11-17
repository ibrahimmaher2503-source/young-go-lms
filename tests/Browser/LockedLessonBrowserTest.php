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

it('shows locked lessons with their reason accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'lk-'.uniqid(), 'slug_en' => 'lk-'.uniqid(), 'status' => 'published', 'access_mode' => 'free', 'sequence_mode' => 'sequential']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $section->update(['published' => true]);
    $lessons = [];
    foreach ([['التعرف على الأدوات', 'Know your tools'], ['الخطوط والأشكال', 'Lines and shapes'], ['الضوء والظل', 'Light and shadow']] as [$ar, $en]) {
        $lessons[] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => $ar, 'en' => $en], 'type' => 'text', 'duration_seconds' => 600]);
    }
    app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($l) => $l->id, $lessons), 'published');
    $lessons[2]->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);
    $learner = User::factory()->create(['locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$lessons[1]->id}")->resize($width, $height);
    $page->assertSee(__('learn.locked_title', [], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.querySelectorAll('[data-lock-reason]').length"))->toBeGreaterThan(0);

    $directory = base_path('tests/Browser/screenshots/T-P4-09');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-09/locked-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P4-09', 'A3');
