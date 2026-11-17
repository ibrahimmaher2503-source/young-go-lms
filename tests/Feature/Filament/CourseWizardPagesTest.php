<?php

declare(strict_types=1);

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function wizardPageCategory(): Category
{
    return Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);
}

function wizardPageUser(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

function wizardPageInstructorCourse(User $instructor, string $scope = 'manage'): Course
{
    $course = Course::query()->create(['title' => ['en' => 'Assigned', 'ar' => 'مسند'], 'slug_ar' => 'm-'.uniqid(), 'slug_en' => 'm-'.uniqid(), 'wizard_step' => 2]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => $scope]);

    return $course;
}

it('opens the wizard for admin and content manager only', function (): void {
    $this->actingAs(wizardPageUser('admin'))->get('/admin/courses/create')->assertOk()->assertSee(__('courses.step_basic'));
    $this->actingAs(wizardPageUser('content_manager'))->get('/admin/courses/create')->assertOk();
    $this->actingAs(wizardPageUser('instructor_academy'))->get('/instructor/courses/create')->assertForbidden();
})->group('T-P3-02', 'A2', 'PRD-09.2');

it('creates a draft through the pages and keeps it when the author saves mid-way', function (): void {
    $this->actingAs(wizardPageUser('admin'));
    $category = wizardPageCategory();

    $test = Livewire::test(CreateCourse::class)
        ->fillForm(['title' => ['ar' => 'دورة الرسم', 'en' => 'Drawing course'], 'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'both', 'format' => 'recorded'])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();

    $course = Course::query()->sole();
    expect($course->getTranslation('title', 'en'))->toBe('Drawing course')
        ->and($course->category_id)->toBe($category->id)
        ->and($course->wizard_completed)->toBe([]);

    // Resume later: the edit page starts on the saved step with the data filled in.
    $this->get("/admin/courses/{$course->id}/edit")->assertOk();
    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet(['title' => ['ar' => 'دورة الرسم', 'en' => 'Drawing course'], 'level' => 'beginner', 'status' => 'draft']);
})->group('T-P3-02', 'A1');

it('shows field errors with their form paths when a step cannot be passed', function (): void {
    $this->actingAs(wizardPageUser('admin'));

    Livewire::test(CreateCourse::class)
        ->fillForm(['title' => ['en' => 'No category']])
        ->goToNextWizardStep()
        ->assertHasErrors(['data.category_id']);
    expect(Course::query()->count())->toBe(0);
})->group('T-P3-02', 'A1');

it('saves the Information step with reorderable rows', function (): void {
    $this->actingAs(wizardPageUser('admin'));
    $category = wizardPageCategory();

    Livewire::test(CreateCourse::class)
        ->fillForm(['title' => ['en' => 'Rows'], 'category_id' => $category->id, 'level' => 'all', 'content_language' => 'en', 'format' => 'live'])
        ->goToNextWizardStep()
        ->fillForm(['outcomes' => [['text' => ['en' => 'First']], ['text' => ['en' => 'Second']]], 'faqs' => [['question' => ['en' => 'Q'], 'answer' => ['en' => 'A']]]])
        ->call('create')
        ->assertHasNoFormErrors();

    $course = Course::query()->sole();
    expect(array_column(array_column($course->outcomes, 'text'), 'en'))->toBe(['First', 'Second'])
        ->and($course->faqs)->toHaveCount(1)
        ->and($course->wizard_completed)->toBe(['1' => true, '2' => true]);
})->group('T-P3-02', 'A1');

it('hides ownership, status and featured from instructors and keeps their values', function (): void {
    $instructor = wizardPageUser('instructor_academy');
    $course = wizardPageInstructorCourse($instructor);
    $this->actingAs($instructor);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormFieldIsHidden('owner_type')->assertFormFieldIsHidden('status')->assertFormFieldIsHidden('featured')
        ->fillForm(['title' => ['en' => 'Renamed by instructor']])
        ->callAction('saveDraft');

    $course->refresh();
    expect($course->getTranslation('title', 'en'))->toBe('Renamed by instructor')
        ->and($course->owner_type)->toBe('academy')->and($course->status->value)->toBe('draft')->and($course->featured)->toBeFalse();
})->group('T-P3-02', 'A2');

it('shows ownership, status and featured to admin', function (): void {
    $course = wizardPageInstructorCourse(wizardPageUser('instructor_academy'));
    $this->actingAs(wizardPageUser('admin'));

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormFieldIsVisible('owner_type')->assertFormFieldIsVisible('status')->assertFormFieldIsVisible('featured');
})->group('T-P3-02', 'A2');

it('denies unassigned and view-only instructors at the page', function (): void {
    $viewOnly = wizardPageUser('instructor_academy');
    $course = wizardPageInstructorCourse($viewOnly, 'view');
    $other = wizardPageInstructorCourse(wizardPageUser('instructor_academy'));

    $this->actingAs($viewOnly)->get("/instructor/courses/{$course->id}/edit")->assertForbidden();
    $this->actingAs($viewOnly)->get("/instructor/courses/{$other->id}/edit")->assertNotFound();
})->group('T-P3-02', 'A2', 'I02');
