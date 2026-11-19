<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Models\AgeBand;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
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

it('matches Arabic spelling variants using normalized course text', function () {
    $this->get('/courses')->assertOk();
    publicCatalogCourse('acting', ['title' => ['ar' => 'أداء مسرحية', 'en' => 'Theatre performance']]);
    publicCatalogCourse('drawing', ['title' => ['ar' => 'رسم', 'en' => 'Drawing']]);

    foreach (['اداء', 'مسرحيه'] as $term) {
        $this->withSession(['locale' => 'ar'])->get('/courses?'.http_build_query(['q' => $term]))
            ->assertOk()->assertSee('أداء مسرحية')->assertDontSee('رسم');
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
