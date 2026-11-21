<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Providers\RichTextProvider;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function publicCatalogCourse(string $slug, array $fields = []): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'ورشة فنية', 'en' => ucfirst($slug)],
        'slug_ar' => 'دورة-'.$slug, 'slug_en' => $slug, 'status' => CourseStatus::Published,
        ...$fields,
    ]);
}

it('keeps the explicit public language and current filters in catalog controls', function () {
    $course = publicCatalogCourse('drawing');

    $this->withSession(['locale' => 'ar'])->get('/en/courses?q=drawing')
        ->assertOk()
        ->assertSee('action="'.url('/en/courses').'"', false)
        ->assertSee('name="q" value="drawing"', false)
        ->assertSee('name="sort"', false)
        ->assertSee('href="'.url('/en/courses/'.$course->slug_en).'" data-course-link', false);
})->group('T-P2-04', 'A2', 'YG-WEB-003');

it('matches Arabic spelling variants using normalized course text', function () {
    $this->get('/courses')->assertOk();
    publicCatalogCourse('acting', ['title' => ['ar' => 'أداء مسرحية', 'en' => 'Theatre performance']]);
    publicCatalogCourse('drawing', ['title' => ['ar' => 'رسم بالفحم', 'en' => 'Charcoal drawing']]);

    foreach (['اداء', 'مسرحيه'] as $term) {
        $this->withSession(['locale' => 'ar'])->get('/courses?'.http_build_query(['q' => $term]))
            ->assertOk()->assertSee('أداء مسرحية')->assertDontSee('رسم بالفحم');
    }
})->group('T-P2-04', 'A1', 'YG-GAP-SRCH-001');

it('combines catalog filters and excludes non-public courses and unpublished categories from filter options', function () {
    $root = Category::query()->create(['name' => ['en' => 'Arts', 'ar' => 'الفنون'], 'slug_en' => 'arts', 'slug_ar' => 'فنون']);
    $child = Category::query()->create(['parent_id' => $root->id, 'name' => ['en' => 'Theatre', 'ar' => 'مسرح'], 'slug_en' => 'theatre', 'slug_ar' => 'المسرح']);
    Category::query()->create(['name' => ['en' => 'Hidden discipline', 'ar' => 'مخفي'], 'slug_en' => 'hidden', 'slug_ar' => 'مخفي', 'status' => 'inactive']);
    $instructor = User::factory()->create(['name' => 'Test Instructor']);
    $fields = ['category_id' => $child->id, 'min_age' => 10, 'max_age' => 13, 'level' => 'beginner', 'format' => 'live', 'content_language' => 'ar', 'access_mode' => 'free'];

    $match = publicCatalogCourse('matches-all', $fields);
    $match->instructors()->attach($instructor);
    foreach ([
        'draft-excluded' => ['status' => CourseStatus::Draft],
        'pending-excluded' => ['status' => CourseStatus::PendingReview],
        'private-excluded' => ['status' => CourseStatus::Private],
        'age-excluded' => ['min_age' => 14, 'max_age' => 18],
        'category-excluded' => ['category_id' => null],
        'level-excluded' => ['level' => 'advanced'],
        'format-excluded' => ['format' => 'recorded'],
        'language-excluded' => ['content_language' => 'en'],
        'price-excluded' => ['access_mode' => 'paid'],
    ] as $slug => $overrides) {
        publicCatalogCourse($slug, [...$fields, ...$overrides])->instructors()->attach($instructor);
    }
    publicCatalogCourse('instructor-excluded', $fields);

    $query = [
        'category' => $root->id, 'age' => 13, 'level' => 'beginner', 'format' => 'live',
        'language' => 'ar', 'instructor' => $instructor->id, 'access' => 'free',
    ];
    $this->withSession(['locale' => 'en'])->get('/courses?'.http_build_query($query))->assertOk()
        ->assertSee('Matches-all')->assertDontSee('Draft-excluded')->assertDontSee('Pending-excluded')
        ->assertDontSee('Private-excluded')->assertDontSee('Age-excluded')->assertDontSee('Category-excluded')
        ->assertDontSee('Level-excluded')->assertDontSee('Format-excluded')->assertDontSee('Language-excluded')
        ->assertDontSee('Price-excluded')->assertDontSee('Instructor-excluded')->assertDontSee('Hidden discipline');
})->group('T-P2-04', 'A2', 'YG-WEB-003');

it('uses configured age bands and keeps search state on newest-first pagination', function () {
    $band = AgeBand::query()->create(['min' => 9, 'max' => 12, 'sort' => 1, 'label' => ['en' => 'First band', 'ar' => 'الفئة الأولى']]);
    $learner = User::factory()->create(['locale' => 'en', 'birth_year' => now()->year - 10, 'birth_month' => 1]);
    foreach (range(14, 1) as $i) {
        publicCatalogCourse('drawing-'.$i, ['min_age' => 10, 'max_age' => 13])
            ->forceFill(['created_at' => now()->addSeconds($i)])->save();
    }
    publicCatalogCourse('drawing-older', ['min_age' => 25]);
    $this->actingAs($learner)->get('/courses?q=drawing&age_band=learner')->assertOk()
        ->assertSeeInOrder(['Drawing-14', 'Drawing-13'])->assertDontSee('Drawing-older')
        ->assertSee('q=drawing&amp;age_band=learner&amp;page=2', false);
    $this->get('/courses?'.http_build_query(['q' => 'drawing', 'age_band' => $band->id, 'page' => 2]))
        ->assertOk()->assertSee('Drawing-1')->assertDontSee('Drawing-14');
})->group('T-P2-04', 'A2');

it('ranks matching courses by distinct active learners even below the global top 200', function (): void {
    $this->freezeTime();
    [$learner, $secondLearner] = User::factory()->count(2)->create()->all();
    $drafts = [];
    foreach (range(1, 200) as $number) {
        $draft = publicCatalogCourse('rank-draft-'.$number, ['status' => CourseStatus::Draft]);
        foreach ([$learner, $secondLearner] as $student) {
            $drafts[] = ['course_id' => $draft->id, 'learner_id' => $student->id, 'source_type' => EntitlementSource::ManualGrant->value,
                'source_ref' => 'catalog-rank', 'status' => Entitlement::ACTIVE, 'starts_at' => now()->subDay()];
        }
    }
    foreach (array_chunk($drafts, 100) as $batch) {
        Entitlement::query()->insert($batch);
    }

    $twoActive = publicCatalogCourse('rank-two-active');
    $ranked = publicCatalogCourse('rank-enrolled');
    $noActive = publicCatalogCourse('rank-no-active');
    foreach ([[$twoActive, $learner, null, 'a'], [$twoActive, $secondLearner, null, 'a'], [$ranked, $learner, null, 'a'], [$ranked, $learner, null, 'b'], [$noActive, $learner, now(), 'expired']] as [$course, $student, $endsAt, $source]) {
        Entitlement::query()->create(['course_id' => $course->id, 'learner_id' => $student->id, 'source_type' => EntitlementSource::ManualGrant,
            'source_ref' => $source, 'status' => Entitlement::ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => $endsAt]);
    }

    $this->get('/en/courses?q=rank&sort=popular')->assertOk()
        ->assertSeeInOrder(['Rank-two-active', 'Rank-enrolled', 'Rank-no-active'])->assertDontSee('Rank-draft-');
})->group('T-P2-04', 'A2', 'YG-WEB-003');

it('only lists starting-soon courses whose enrolling cohort can still take learners', function (): void {
    $this->freezeTime();
    $available = publicCatalogCourse('available-workshop', ['format' => 'live']);
    $full = publicCatalogCourse('occupied-workshop', ['format' => 'live']);
    $cohortFields = ['name' => 'Workshop intake', 'status' => Cohort::OPEN, 'capacity' => 1,
        'starts_on' => now('Africa/Cairo')->addWeek()->toDateString()];
    $available->cohorts()->create([...$cohortFields, 'starts_on' => now('Africa/Cairo')->addDays(14)->toDateString()]);
    $full->cohorts()->create($cohortFields)->members()->create([
        'learner_id' => User::factory()->create()->id, 'status' => CohortMember::SEATED,
    ]);

    $this->get('/en/courses?availability=soon')->assertOk()->assertSee('Available-workshop')
        ->assertSee('data-availability="soon"', false)->assertDontSee('Occupied-workshop');
})->group('T-P2-04', 'A2', 'YG-WEB-003');

it('combines Egypt price bounds with price sorting', function (): void {
    $this->freezeTime();
    $cheap = publicCatalogCourse('priced-cheap', ['access_mode' => Course::ACCESS_PAID]);
    $higher = publicCatalogCourse('priced-higher', ['access_mode' => Course::ACCESS_BOTH]);
    $outside = publicCatalogCourse('priced-outside', ['access_mode' => Course::ACCESS_PAID]);
    foreach ([[$cheap, 'EG', 'EGP', 1000], [$higher, 'EG', 'EGP', 2250], [$outside, 'EG', 'EGP', 2251], [$outside, 'SA', 'SAR', 2200]] as [$course, $market, $currency, $amount]) {
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => $market, 'currency' => $currency, 'amount_minor' => $amount]);
    }

    $this->get('/en/courses?price_min=10&price_max=22.50&sort=price_asc')->assertOk()
        ->assertSeeInOrder(['Priced-cheap', 'Priced-higher'])->assertDontSee('Priced-outside');
})->group('T-P2-04', 'A2', 'YG-WEB-003');

it('renders a published course and its review area', function (bool $signedIn, bool $seedRoles): void {
    $this->withoutExceptionHandling();
    if ($seedRoles) {
        $this->seed(RolePermissionSeeder::class);
    }

    if ($signedIn) {
        $this->actingAs(User::factory()->create()->assignRole('student'));
    }

    $course = publicCatalogCourse('studio');
    $instructor = User::factory()->create(['name' => 'Studio Instructor']);
    if ($seedRoles) {
        $instructor->assignRole('instructor_academy');
    }
    InstructorProfile::query()->create([
        'user_id' => $instructor->id,
        'slug_en' => 'studio-instructor',
        'slug_ar' => 'studio-instructor-ar',
        'display_name' => ['en' => 'Studio Public Profile', 'ar' => 'Studio Public Profile'],
        'status' => InstructorProfile::APPROVED,
    ]);
    $course->instructors()->attach($instructor);

    $this->get('/courses/'.$course->slug_en)->assertOk()
        ->assertSee($seedRoles ? 'Studio Public Profile' : 'Studio Instructor')
        ->assertSee('data-reviews-slot', false);
})->with([
    'guest before roles are seeded' => [false, false],
    'guest' => [false, true],
    'learner' => [true, true],
])->group('T-P2-05', 'YG-WEB-004', 'ISSUE-080');

it('shows the configured age suitability in the course access card', function (string $locale): void {
    $course = publicCatalogCourse('drawing', ['min_age' => 10, 'max_age' => 13]);
    $label = $locale === 'ar' ? 'ملاءمة العمر' : 'Age suitability';
    $ages = $locale === 'ar' ? 'من 10 إلى 13 سنة' : 'Ages 10 to 13';

    $this->withSession(['locale' => $locale])->get('/courses/'.$course->getAttribute('slug_'.$locale))
        ->assertOk()->assertSeeInOrder(['data-access-card', $label, $ages], false);
})->with(['ar', 'en'])->group('T-P2-05', 'YG-WEB-004');

it('does not expose an unpublished course publicly', function (): void {
    $course = publicCatalogCourse('draft', ['status' => CourseStatus::Draft]);

    $this->get('/en/courses/'.$course->slug_en)->assertNotFound();
})->group('T-P2-05', 'T-P2-05:A1', 'YG-WEB-004');

it('opens the explicit preview to guests and keeps the other lesson locked', function (): void {
    $course = publicCatalogCourse('previews');
    $section = CourseSection::query()->create([
        'course_id' => $course->id, 'published' => true, 'sort' => 1, 'title' => ['en' => 'Drawing tools'],
    ]);
    foreach ([true, false] as $isPreview) {
        $title = $isPreview ? 'Open lesson' : 'Protected lesson';
        $lesson = Lesson::query()->create(['title' => ['en' => $title], 'type' => 'text', 'is_preview' => $isPreview]);
        $lesson->media()->create((new RichTextProvider)->validate(['text' => $title.' content']));
        $lesson->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => $isPreview ? 1 : 2, 'status' => 'published']);
    }

    $url = '/en/courses/'.$course->slug_en;
    $this->get($url)->assertOk()->assertSeeInOrder(['data-state="preview"', 'Open lesson', 'data-state="locked"', 'Protected lesson'], false);
    $this->get($url.'/preview/'.$section->items()->firstOrFail()->id)->assertOk()->assertSee('Open lesson content');
    $this->get($url.'/preview/'.$section->items()->orderByDesc('sort')->reorder('sort', 'desc')->firstOrFail()->id)->assertNotFound();
})->group('T-P2-05', 'T-P2-05:A2', 'YG-WEB-004');
it('shows a published course thumbnail before falling back to its category cover', function (): void {
    $category = Category::query()->create([
        'name' => ['en' => 'Arts'], 'slug_en' => 'arts', 'slug_ar' => 'arts-ar', 'image' => 'categories/cover.png',
    ]);
    $course = publicCatalogCourse('drawing-ideas', [
        'category_id' => $category->id, 'thumbnail_path' => 'course-thumbnails/drawing.jpg',
    ]);
    publicCatalogCourse('private-drawing', [
        'status' => CourseStatus::Private, 'thumbnail_path' => 'course-thumbnails/private.jpg',
    ]);

    $this->withSession(['locale' => 'en'])->get('/courses')->assertOk()
        ->assertSee('/storage/course-thumbnails/drawing.jpg', false)
        ->assertDontSee('/storage/course-thumbnails/private.jpg', false);

    $course->update(['thumbnail_path' => null]);
    $this->get('/courses')->assertOk()->assertSee('/storage/categories/cover.png', false);
})->group('T-P3-03', 'PRD-09-2');
