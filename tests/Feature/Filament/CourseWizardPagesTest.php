<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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
        ->goToNextWizardStep();

    Livewire::test(EditCourse::class, ['record' => Course::query()->sole()->getKey()])
        ->fillForm(['outcomes' => [['text' => ['en' => 'First']], ['text' => ['en' => 'Second']]], 'faqs' => [['question' => ['en' => 'Q'], 'answer' => ['en' => 'A']]]])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors();

    $course = Course::query()->sole();
    expect(array_column(array_column($course->outcomes, 'text'), 'en'))->toBe(['First', 'Second'])
        ->and($course->faqs)->toHaveCount(1)
        ->and($course->wizard_completed)->toBe(['1' => true, '2' => true]);
})->group('T-P3-02', 'A1');

it('saves exact market prices from the wizard and rejects checkout in the disabled market', function (): void {
    $this->actingAs(wizardPageUser('admin'));
    $course = Course::query()->create([
        'title' => ['en' => 'Market pricing'], 'slug_ar' => 'market-ar', 'slug_en' => 'market-en',
        'wizard_step' => 3, 'wizard_completed' => ['1' => true, '2' => true],
    ]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['access_mode' => 'both', 'market_prices' => ['EG' => '19.99', 'SA' => '31.50']])
        ->call('save')
        ->assertHasNoFormErrors();

    $prices = CoursePrice::query()->where('course_id', $course->id)->get()->keyBy('market');
    expect($course->refresh()->access_mode)->toBe('both')
        ->and($prices['EG']->amount_minor)->toBe(1999)
        ->and($prices['EG']->money()->value->getCurrency()->getCurrencyCode())->toBe('EGP')
        ->and($prices['SA']->amount_minor)->toBe(3150)
        ->and($prices['SA']->money()->value->getCurrency()->getCurrencyCode())->toBe('SAR');
    $audit = Activity::query()->where('event', 'course.pricing_changed')->sole();
    expect($audit->getProperty('after.market_prices'))->toBe(['EG' => 1999, 'SA' => 3150]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet(['market_prices' => ['EG' => '19.99', 'SA' => '31.50']]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['market_prices' => ['EG' => '0.001', 'SA' => '45.25']])
        ->call('save')
        ->assertHasFormErrors(['market_prices.EG']);
    expect($prices['EG']->refresh()->amount_minor)->toBe(1999)
        ->and($prices['SA']->refresh()->amount_minor)->toBe(3150);

    $course->update(['status' => 'published']);
    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['market_prices' => ['EG' => '20.50', 'SA' => '31.50']])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($course->refresh()->status->value)->toBe('published');

    $learner = wizardPageUser('student');
    $line = new OrderLine(ProductType::Course, $course->id, $learner);
    expect(fn () => app(CreateOrder::class)($learner, Market::SaudiArabia, [$line], 'sa-disabled'))
        ->toThrow(ValidationException::class, __('commerce.market_unavailable'));
    expect(app(CreateOrder::class)($learner, Market::Egypt, [$line], 'eg-enabled')->total_minor)->toBe(2050);
})->group('T-P3-03', 'T-P3-03:A2', 'PRD-09-2');

it('rejects pricing mutations from an assigned instructor and accepts blank form placeholders', function (): void {
    $instructor = wizardPageUser('instructor_academy');
    $course = wizardPageInstructorCourse($instructor);
    $this->actingAs($instructor);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertDontSee(__('courses.market_price.SA'));

    $save = app(SaveCourseWizard::class);
    $save($instructor, ['access_mode' => null, 'market_prices' => ['EG' => null]], $course);

    expect(fn () => $save($instructor, ['access_mode' => 'paid', 'market_prices' => ['EG' => '1.00']], $course))
        ->toThrow(AuthorizationException::class);
    expect($course->refresh()->access_mode)->toBeNull()
        ->and(CoursePrice::query()->count())->toBe(0);
})->group('T-P3-03', 'T-P3-03:A2', 'PRD-09-2');

it('resumes legacy paid courses and preserves a saved amount in an incomplete draft', function (): void {
    $admin = wizardPageUser('admin');
    $this->actingAs($admin);
    $course = Course::query()->create([
        'title' => ['en' => 'Legacy paid course'], 'slug_ar' => 'legacy-ar', 'slug_en' => 'legacy-en',
        'access_mode' => 'paid', 'wizard_step' => 3, 'wizard_completed' => ['1' => true, '2' => true],
    ]);
    $price = CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 1250]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet(['access_mode' => 'paid', 'market_prices.EG' => '12.50']);

    app(SaveCourseWizard::class)($admin, ['access_mode' => null, 'market_prices' => ['EG' => null]], $course);

    expect($course->refresh()->access_mode)->toBe('paid')
        ->and($price->refresh()->amount_minor)->toBe(1250);
})->group('T-P3-03', 'T-P3-03:A2', 'PRD-09-2');

it('saves validated overview and preview links and resumes with both canonical URLs', function (): void {
    $this->actingAs(wizardPageUser('admin'));
    $course = Course::query()->create([
        'title' => ['en' => 'Course with video'], 'slug_ar' => 'media-ar', 'slug_en' => 'media-en',
        'access_mode' => 'free', 'wizard_step' => 3, 'wizard_completed' => ['1' => true, '2' => true],
    ]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors();
    expect($course->refresh()->wizard_step)->toBe(4);

    $media = [
        'overview_video_provider' => 'youtube', 'overview_video_url' => 'https://youtu.be/dQw4w9WgXcQ?t=7',
        'preview_video_provider' => 'vimeo', 'preview_video_url' => 'https://vimeo.com/123456789/a1b2c3d4e5',
    ];
    $canonical = [
        ...$media,
        'overview_video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
        'preview_video_url' => 'https://player.vimeo.com/video/123456789?dnt=1&h=a1b2c3d4e5',
    ];
    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm($media)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($course->refresh()->only(array_keys($canonical)))->toBe($canonical)
        ->and($course->wizard_completed)->toBe(['1' => true, '2' => true, '3' => true, '4' => true]);
    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet($canonical)
        ->call('save')
        ->assertHasNoFormErrors();
})->group('T-P3-03', 'PRD-09-2');

it('rejects invalid course video inputs without changing either video or the title', function (array $invalid, string $field): void {
    $actor = wizardPageUser('admin');
    $course = Course::query()->create([
        'title' => ['en' => 'Original'], 'slug_ar' => 'invalid-media-ar', 'slug_en' => 'invalid-media-en',
        'overview_video_provider' => 'youtube', 'overview_video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
        'wizard_step' => 4,
    ]);

    expect(fn () => app(SaveCourseWizard::class)($actor, [
        'title' => ['en' => 'Must not save'],
        'preview_video_provider' => 'vimeo', 'preview_video_url' => 'https://vimeo.com/123456789',
        ...$invalid,
    ], $course, 4))->toThrow(function (ValidationException $error) use ($field): void {
        expect($error->errors())->toHaveKey($field);
    });

    expect($course->refresh()->getTranslation('title', 'en'))->toBe('Original')
        ->and($course->overview_video_provider)->toBe('youtube')
        ->and($course->overview_video_url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0')
        ->and($course->preview_video_url)->toBeNull();
})->with([
    'unknown provider' => [['overview_video_provider' => 'iframe', 'overview_video_url' => 'https://vimeo.com/123456789'], 'overview_video_provider'],
    'mismatched host' => [['overview_video_provider' => 'vimeo'], 'overview_video_url'],
    'unsafe URL' => [['overview_video_url' => 'https://username@www.youtube.com/watch?v=dQw4w9WgXcQ'], 'overview_video_url'],
    'preview URL without provider' => [['preview_video_provider' => null], 'preview_video_provider'],
])->group('T-P3-03', 'PRD-09-2');

it('lets an assigned instructor resume incomplete draft media and clear links from the media step', function (): void {
    $instructor = wizardPageUser('instructor_academy');
    $course = wizardPageInstructorCourse($instructor);
    $course->update(['wizard_step' => 4]);
    $this->actingAs($instructor);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['preview_video_provider' => 'youtube'])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();
    expect($course->refresh()->preview_video_provider)->toBe('youtube')
        ->and($course->preview_video_url)->toBeNull();

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet(['preview_video_provider' => 'youtube'])
        ->call('save')
        ->assertHasFormErrors(['preview_video_url']);

    $save = app(SaveCourseWizard::class);
    $save($instructor, ['preview_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], $course, 4);
    $save($instructor, ['preview_video_provider' => null, 'preview_video_url' => null], $course, 2, true);
    expect($course->refresh()->preview_video_url)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0');

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['preview_video_provider' => null, 'preview_video_url' => null])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();
    expect($course->refresh()->preview_video_provider)->toBeNull()
        ->and($course->preview_video_url)->toBeNull();
})->group('T-P3-03', 'PRD-09-2');

it('authorizes media changes before validating courses outside the instructor edit scope', function (): void {
    $instructor = wizardPageUser('instructor_academy');
    $viewOnly = wizardPageInstructorCourse($instructor, 'view');
    $unassigned = wizardPageInstructorCourse(wizardPageUser('instructor_academy'));
    $save = app(SaveCourseWizard::class);

    foreach ([$viewOnly, $unassigned] as $course) {
        expect(fn () => $save($instructor, ['overview_video_provider' => 'unknown'], $course, 4))
            ->toThrow(AuthorizationException::class);
    }
})->group('T-P3-03', 'PRD-09-2');

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
