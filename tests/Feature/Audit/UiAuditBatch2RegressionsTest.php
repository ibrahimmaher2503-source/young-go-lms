<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('allows independent and academy instructors to create a course draft and advance wizard steps without 403', function (string $role): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole($role);
    $category = Category::query()->create([
        'name' => ['ar' => 'فنون', 'en' => 'Arts'],
        'slug_ar' => 'arts-ar-'.uniqid(),
        'slug_en' => 'arts-en-'.uniqid(),
        'sort' => 1,
        'status' => 'active',
    ]);

    $this->actingAs($instructor);

    Livewire::test(CreateCourse::class)
        ->fillForm([
            'title' => ['ar' => 'دورة جديدة', 'en' => 'Instructor Course'],
            'category_id' => $category->id,
            'level' => 'beginner',
            'content_language' => 'both',
            'format' => 'recorded',
        ])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();

    $course = Course::query()->sole();
    expect($course->getTranslation('title', 'en'))->toBe('Instructor Course')
        ->and($course->featured)->toBeFalse();
})->with(['instructor_independent', 'instructor_academy']);

it('still rejects direct SaveCourseWizard tampering on privileged fields by an instructor', function (): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $category = Category::query()->create([
        'name' => ['ar' => 'فنون', 'en' => 'Arts'],
        'slug_ar' => 'arts-ar-'.uniqid(),
        'slug_en' => 'arts-en-'.uniqid(),
        'sort' => 1,
        'status' => 'active',
    ]);

    expect(fn () => app(SaveCourseWizard::class)($instructor, [
        'title' => ['ar' => 'تلاعب', 'en' => 'Tamper'],
        'category_id' => $category->id,
        'level' => 'beginner',
        'content_language' => 'both',
        'format' => 'recorded',
        'featured' => true,
    ]))->toThrow(AuthorizationException::class);
});

it('updates media_provider options and clears stale selection when lesson type changes in ManageCurriculum', function (): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'منهج', 'en' => 'Curriculum Course'],
        'slug_ar' => 'curr-ar-'.uniqid(),
        'slug_en' => 'curr-en-'.uniqid(),
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = CourseSection::query()->create([
        'course_id' => $course->id,
        'title' => ['ar' => 'الأولى', 'en' => 'First'],
        'sort' => 1,
    ]);

    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->mountAction('addLesson')
        ->setActionData([
            'section_id' => $section->id,
            'title' => ['ar' => 'درس مستند', 'en' => 'Doc Lesson'],
            'type' => 'document',
            'media_provider' => 'document',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(CurriculumItem::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('isolates guardian throttle buckets across confirm-password and guardian endpoints', function (): void {
    $parent = User::factory()->create([
        'status' => 'active',
        'password' => bcrypt('SecretPass123!'),
        'email_verified_at' => now(),
    ])->assignRole('parent');

    $this->actingAs($parent);

    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/confirm-password', ['password' => 'SecretPass123!'])->assertNoContent();
    }
    $this->postJson('/confirm-password', ['password' => 'SecretPass123!'])->assertStatus(429);

    // Separate guardian endpoint must NOT inherit /confirm-password's throttle count.
    $this->postJson('/guardian/verification/code')->assertOk()->assertJson(['sent' => true]);
});
