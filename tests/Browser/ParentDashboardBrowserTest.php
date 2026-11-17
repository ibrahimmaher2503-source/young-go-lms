<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Progress\Actions\RecordProgress;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the parent dashboard accessibly in both directions and sizes, with and without children', function (string $locale, int $width, int $height): void {
    $parent = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'منى سعيد' : 'Mona Saeed'])->assignRole('parent');
    $instructor = User::factory()->create(['name' => $locale === 'ar' ? 'سارة أحمد' : 'Sara Ahmed'])->assignRole('instructor_academy');
    foreach ([['أحمد', 'Ahmed'], ['ليلى', 'Layla']] as $index => [$ar, $en]) {
        $child = User::factory()->create(['name' => $locale === 'ar' ? $ar : $en])->assignRole('student');
        GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);
        if ($index === 0) {
            foreach ([['أساسيات الرسم الرقمي', 'Digital drawing basics'], ['فن الإلقاء والتعبير', 'The art of public speaking']] as [$tar, $ten]) {
                $course = Course::query()->create(['title' => ['ar' => $tar, 'en' => $ten], 'slug_ar' => 'pb-'.uniqid(), 'slug_en' => 'pb-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
                $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
                $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Start']]);
                $section->update(['published' => true]);
                $lessons = [];
                foreach ([1, 2, 3, 4] as $n) {
                    $lessons[] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => "درس {$n}", 'en' => "Lesson {$n}"], 'type' => 'text']);
                }
                app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($l) => $l->id, $lessons), 'published');
                app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);
                foreach (array_slice($lessons, 0, $tar === 'أساسيات الرسم الرقمي' ? 3 : 2) as $lesson) {
                    app(RecordProgress::class)->markComplete($child, $lesson->refresh());
                }
            }
        }
    }
    $this->withSession(['locale' => $locale]);
    $this->actingAs($parent);

    $page = $this->visit('/parent')->resize($width, $height);
    $page->assertSee($locale === 'ar' ? 'تعرض الآن تقدم' : 'progress now');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P4-08');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-08/parent-{$locale}-{$width}");

    $lonely = User::factory()->create(['locale' => $locale])->assignRole('parent');
    $this->actingAs($lonely);
    $empty = $this->visit('/parent')->resize($width, $height);
    $empty->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $empty->screenshot(fullPage: true, filename: "T-P4-08/parent-empty-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P4-08', 'A3');
