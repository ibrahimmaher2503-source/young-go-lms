<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Actions\SubmitCourseForReview;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Action;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

it('opens the wizard for authorized staff and instructors', function (): void {
    $this->actingAs(wizardPageUser('admin'))->get('/admin/courses/create')->assertOk()->assertSee(__('courses.step_basic'));
    $this->actingAs(wizardPageUser('content_manager'))->get('/admin/courses/create')->assertOk();
    $this->actingAs(wizardPageUser('instructor_academy'))->get('/instructor/courses/create')->assertOk();
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

it('authorizes media changes before validating courses outside the instructor edit scope', function (): void {
    $instructor = wizardPageUser('instructor_academy');
    $viewOnly = wizardPageInstructorCourse($instructor, 'view');
    $unassigned = wizardPageInstructorCourse(wizardPageUser('instructor_academy'));
    $save = app(SaveCourseWizard::class);

    foreach ([$viewOnly, $unassigned] as $course) {
        expect(fn () => $save($instructor, ['promo_video' => ['provider' => 'unknown', 'url' => 'https://example.com/x']], $course, SaveCourseWizard::MEDIA_STEP))
            ->toThrow(AuthorizationException::class);
    }
})->group('T-P3-03', 'PRD-09-2');

it('previews and saves a cropped course thumbnail, preserves it on draft retries and cleans up replacements', function (): void {
    $disk = Storage::fake('course-thumbnails-public');
    Storage::set('public', $disk);
    $admin = wizardPageUser('admin');
    $this->actingAs($admin);
    $course = Course::query()->create([
        'title' => ['en' => 'Thumbnail example'], 'slug_ar' => 'cover-ar', 'slug_en' => 'cover-en',
        'access_mode' => 'free', 'wizard_step' => 4,
    ]);

    $original = UploadedFile::fake()->image('cover.jpg', 320, 240);
    $upload = UploadedFile::fake()->createWithContent('cover.jpg', $original->get().'hidden image metadata');
    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['thumbnail_path' => [$upload]])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();

    $first = $course->refresh()->thumbnail_path;
    expect($first)->toBeString()->toMatch('#^course-thumbnails/[a-f0-9-]+\.jpg$#');
    $disk->assertExists($first);
    $bytes = $disk->get($first);
    expect(array_slice(getimagesizefromstring($bytes), 0, 2))->toBe([320, 180])
        ->and($bytes)->not->toContain('hidden image metadata');

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertFormSet(['thumbnail_path' => $first])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors()
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();
    app(SaveCourseWizard::class)($admin, [], $course, 4);
    app(SaveCourseWizard::class)($admin, ['thumbnail_path' => []], $course, 2);
    expect($course->refresh()->thumbnail_path)->toBe($first)
        ->and($disk->allFiles())->toBe([$first]);

    $edit = Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['thumbnail_path' => [UploadedFile::fake()->image('replacement.jpg', 160, 160)]])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();
    $replacement = $course->refresh()->thumbnail_path;
    $edit->assertFormSet(['thumbnail_path' => $replacement])
        ->callAction('saveDraft')
        ->assertHasNoFormErrors();
    expect($replacement)->not->toBe($first)
        ->and($course->refresh()->thumbnail_path)->toBe($replacement)
        ->and(array_slice(getimagesizefromstring($disk->get($replacement)), 0, 2))->toBe([160, 90])
        ->and($disk->allFiles())->toBe([$replacement]);

    $edit->fillForm(['thumbnail_path' => []])->call('save')->assertHasNoFormErrors();
    expect($course->refresh()->thumbnail_path)->toBeNull()
        ->and($disk->allFiles())->toBe([]);
})->group('T-P3-03', 'PRD-09-2');

it('rejects forged paths, invalid bytes and oversized thumbnail dimensions and preserves the current image', function (): void {
    $disk = Storage::fake('course-thumbnails-public');
    Storage::set('public', $disk);
    $actor = wizardPageUser('admin');
    $course = Course::query()->create([
        'title' => ['en' => 'Thumbnail validation'], 'slug_ar' => 'cover-validation-ar', 'slug_en' => 'cover-validation-en',
        'wizard_step' => 4,
    ]);
    $save = app(SaveCourseWizard::class);
    $first = $save($actor, ['thumbnail_path' => [UploadedFile::fake()->image('cover.jpg', 320, 240)]], $course, 4)->thumbnail_path;
    $foreign = $save($actor, [
        'title' => ['en' => 'Other course'], 'thumbnail_path' => [UploadedFile::fake()->image('other.jpg', 160, 90)],
    ], null, 4)->thumbnail_path;
    $invalid = UploadedFile::fake()->createWithContent('bad.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $wide = UploadedFile::fake()->image('wide.jpg', 9000, 20);
    $tooLarge = UploadedFile::fake()->createWithContent('large.jpg', $wide->get().str_repeat('x', 4096 * 1024));

    foreach ([$foreign, $invalid, $wide, $tooLarge] as $input) {
        expect(fn () => $save($actor, ['thumbnail_path' => [$input]], $course, 4))->toThrow(function (ValidationException $error): void {
            expect($error->errors())->toHaveKey('thumbnail_path');
        });
    }
    expect($course->refresh()->thumbnail_path)->toBe($first)
        ->and($disk->allFiles())->toEqualCanonicalizing([$first, $foreign]);
})->group('T-P3-03', 'PRD-09-2');

it('keeps the old thumbnail and removes the new upload if the enclosing transaction rolls back', function (): void {
    $disk = Storage::fake('course-thumbnails-public');
    Storage::set('public', $disk);
    $actor = wizardPageUser('admin');
    $save = app(SaveCourseWizard::class);
    $course = $save($actor, ['title' => ['en' => 'Rollback'], 'thumbnail_path' => [UploadedFile::fake()->image('old.jpg', 160, 90)]], null, 4);
    $oldPath = $course->thumbnail_path;

    DB::beginTransaction();
    try {
        $newPath = $save($actor, ['thumbnail_path' => [UploadedFile::fake()->image('new.jpg', 160, 90)]], $course, 4)->thumbnail_path;
        expect($newPath)->not->toBe($oldPath);
        $disk->assertExists([$oldPath, $newPath]);
    } finally {
        DB::rollBack();
    }

    expect($course->refresh()->thumbnail_path)->toBe($oldPath)
        ->and($disk->allFiles())->toBe([$oldPath]);
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

/** Independent instructor's course that passes Basic and, unless told otherwise, has one lesson so it can be submitted. */
function wizardApprovalCourse(User $instructor, string $title = 'Approval course', bool $withLesson = true): Course
{
    $course = app(SaveCourseWizard::class)($instructor, [
        'title' => ['ar' => 'دورة للمراجعة', 'en' => $title],
        'short_description' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'category_id' => wizardPageCategory()->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12,
    ], null, 1, true);

    if ($withLesson) {
        wizardApprovalLesson($course);
    }

    return $course;
}

function wizardApprovalLesson(Course $course): void
{
    $section = CourseSection::query()->create(['course_id' => $course->getKey(), 'published' => true, 'sort' => 1, 'title' => ['en' => 'Section']]);
    $lesson = Lesson::query()->create(['title' => ['en' => 'Lesson one'], 'type' => 'text', 'is_preview' => false]);
    $lesson->item()->create(['course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => 1, 'status' => 'published']);
}

function wizardApprovalPrice(User $admin, Course $course): void
{
    app(SaveCourseWizard::class)($admin, ['access_mode' => 'paid', 'market_prices' => ['EG' => '150']], $course, 3, true);
}

/** Academy course with category, academy instructor and a lesson; ready to publish except for pricing. */
function wizardApprovalAcademyCourse(): Course
{
    $course = Course::query()->create(['title' => ['en' => 'Academy approval'], 'slug_ar' => 'aa-'.uniqid(), 'slug_en' => 'aa-'.uniqid(), 'owner_type' => Course::OWNER_ACADEMY]);
    $course->forceFill(['category_id' => wizardPageCategory()->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded'])->save();
    $course->instructors()->attach(wizardPageUser('instructor_academy'), ['role' => 'instructor', 'scope' => 'manage']);
    wizardApprovalLesson($course);

    return $course;
}

it('shows approve and reject only on pending courses and only to a reviewer who passes the publish gate', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $pending = wizardApprovalCourse($instructor, 'Pending course');
    $draft = wizardApprovalCourse($instructor, 'Draft course');
    app(SubmitCourseForReview::class)($instructor, $pending);

    $this->actingAs(wizardPageUser('admin'));
    Livewire::test(ListCourses::class)
        ->assertTableActionVisible('approve', $pending)
        ->assertTableActionVisible('reject', $pending)
        ->assertTableActionHidden('approve', $draft)
        ->assertTableActionHidden('reject', $draft);

    $this->actingAs(wizardPageUser('content_manager'));
    Livewire::test(ListCourses::class)
        ->assertTableActionHidden('approve', $pending)
        ->assertTableActionHidden('reject', $pending);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2', 'PRD-09-4');

it('keeps the review queue away from instructors', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    app(SubmitCourseForReview::class)($instructor, wizardApprovalCourse($instructor));

    $this->actingAs($instructor)->get('/admin/courses')->assertForbidden();
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-4');

it('refuses a rejection whose reason is too short and leaves the course pending with no audit entry', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $pending = wizardApprovalCourse($instructor);
    app(SubmitCourseForReview::class)($instructor, $pending);

    $this->actingAs(wizardPageUser('admin'));
    Livewire::test(ListCourses::class)
        ->callTableAction('reject', $pending, data: ['reason' => 'short'])
        ->assertHasTableActionErrors(['reason']);

    expect($pending->fresh()->status)->toBe(CourseStatus::PendingReview)
        ->and(Activity::query()->where('event', 'course.review.rejected')->where('subject_id', $pending->getKey())->count())->toBe(0);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');

it('returns a course rejected from the queue to draft with its reason and notifies the instructor', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $pending = wizardApprovalCourse($instructor);
    app(SubmitCourseForReview::class)($instructor, $pending);
    $reason = 'Missing safety prerequisites in unit 1';

    $this->actingAs(wizardPageUser('admin'));
    Livewire::test(ListCourses::class)
        ->callTableAction('reject', $pending, data: ['reason' => $reason])
        ->assertHasNoTableActionErrors();

    $rejected = $pending->fresh();
    expect($rejected->status)->toBe(CourseStatus::Draft)
        ->and($rejected->review_reason)->toBe($reason)
        ->and(Activity::query()->where('event', 'course.review.rejected')->where('subject_id', $pending->getKey())->sole()->properties->get('context')['reason'])->toBe($reason)
        ->and($instructor->notifications()->count())->toBeGreaterThan(0);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2', 'PRD-09-4');

it('runs the publish checks before approving a pending course from the queue', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $admin = wizardPageUser('admin');
    $pending = wizardApprovalCourse($instructor);
    app(SubmitCourseForReview::class)($instructor, $pending);

    $this->actingAs($admin);
    Livewire::test(ListCourses::class)->callTableAction('approve', $pending);
    expect($pending->fresh()->status)->toBe(CourseStatus::PendingReview)
        ->and(Activity::query()->where('event', 'course.review.approved')->count())->toBe(0);

    wizardApprovalPrice($admin, $pending);
    Livewire::test(ListCourses::class)->callTableAction('approve', $pending)->assertHasNoTableActionErrors();

    expect($pending->fresh()->status)->toBe(CourseStatus::Published)
        ->and(Activity::query()->where('event', 'course.review.approved')->where('subject_id', $pending->getKey())->count())->toBe(1);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');

/**
 * The Finish-step buttons are declared inside the wizard schema, which Filament's table/page action lookup cannot reach,
 * so they are read from the page's finishActions() and run with the same closures the buttons use.
 */
function wizardApprovalFinishAction(object $test, string $name): Action
{
    $page = $test->instance();
    $actions = (new ReflectionMethod($page, 'finishActions'))->invoke($page);

    return collect($actions)->firstOrFail(fn (Action $action): bool => $action->getName() === $name);
}

it('offers submit for review on an editable draft and moves it to pending review', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $course = wizardApprovalCourse($instructor);

    $this->actingAs($instructor);
    $test = Livewire::test(EditCourse::class, ['record' => $course->getKey()]);
    expect(wizardApprovalFinishAction($test, 'submitForReview')->isVisible())->toBeTrue();
    wizardApprovalFinishAction($test, 'submitForReview')->getActionFunction()();

    expect($course->fresh()->status)->toBe(CourseStatus::PendingReview)
        ->and(Activity::query()->where('event', 'course.submitted_for_review')->where('subject_id', $course->getKey())->count())->toBe(1);

    $this->actingAs(wizardPageUser('admin'));
    expect(wizardApprovalFinishAction(Livewire::test(EditCourse::class, ['record' => $course->getKey()]), 'submitForReview')->isVisible())->toBeFalse();
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');

it('keeps an incomplete draft a draft when submit for review is refused', function (): void {
    $instructor = wizardPageUser('instructor_independent');
    $course = wizardApprovalCourse($instructor, 'No lesson yet', withLesson: false);

    $this->actingAs($instructor);
    $test = Livewire::test(EditCourse::class, ['record' => $course->getKey()]);
    wizardApprovalFinishAction($test, 'submitForReview')->getActionFunction()();

    expect($course->fresh()->status)->toBe(CourseStatus::Draft)
        ->and(Activity::query()->where('event', 'course.submitted_for_review')->count())->toBe(0);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');

it('offers publish now only to a publish holder and does not publish while checks are missing', function (): void {
    $admin = wizardPageUser('admin');
    $course = wizardApprovalAcademyCourse();
    $assignedInstructor = wizardPageUser('instructor_academy');
    $assigned = wizardPageInstructorCourse($assignedInstructor);

    $this->actingAs($assignedInstructor);
    expect(wizardApprovalFinishAction(Livewire::test(EditCourse::class, ['record' => $assigned->getKey()]), 'publishNow')->isVisible())->toBeFalse();

    $this->actingAs($admin);
    $test = Livewire::test(EditCourse::class, ['record' => $course->getKey()]);
    expect(wizardApprovalFinishAction($test, 'publishNow')->isVisible())->toBeTrue();
    wizardApprovalFinishAction($test, 'publishNow')->getActionFunction()();

    expect($course->fresh()->status)->toBe(CourseStatus::Draft)
        ->and(Activity::query()->where('event', 'course.published')->count())->toBe(0);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');

it('publishes a ready course from the finish step', function (): void {
    $admin = wizardPageUser('admin');
    $course = wizardApprovalAcademyCourse();
    wizardApprovalPrice($admin, $course);

    $this->actingAs($admin);
    $test = Livewire::test(EditCourse::class, ['record' => $course->getKey()]);
    wizardApprovalFinishAction($test, 'publishNow')->getActionFunction()();

    expect($course->fresh()->status)->toBe(CourseStatus::Published)
        ->and(Activity::query()->where('event', 'course.published')->where('subject_id', $course->getKey())->count())->toBe(1);
})->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2');
