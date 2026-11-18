<?php

declare(strict_types=1);

use App\Domain\Assignments\ReviewAccess;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Queries\CourseDirectory;
use App\Domain\Commerce\CoursePrice;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Library\Pages\ListLibraryItems;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('lets instructors open the builder and resume their own draft', function (string $role, string $ownership): void {
    $instructor = User::factory()->create()->assignRole($role);
    $this->actingAs($instructor)->get('/instructor/courses/create')->assertOk();

    $draft = app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Draft']]);
    expect($draft->owner_type)->toBe($ownership)
        ->and($draft->owner_instructor_id)->toBe($ownership === Course::OWNER_INDEPENDENT ? $instructor->id : null)
        ->and(Gate::forUser($instructor)->allows('update', $draft))->toBeTrue();
    $this->get('/instructor/courses/'.$draft->id.'/edit')->assertOk();
})->with([
    ['instructor_academy', Course::OWNER_ACADEMY],
    ['instructor_independent', Course::OWNER_INDEPENDENT],
])->group('T-P3-02', 'A1', 'A2');

it('scopes reviews to managed courses and keeps permission lookup query count independent of course count', function (): void {
    $actor = User::factory()->create()->assignRole('instructor_academy');
    $manageable = [];
    foreach (range(1, 12) as $index) {
        $course = Course::query()->create(['title' => ['en' => 'Review'], 'slug_en' => 'review-'.$index, 'slug_ar' => 'review-'.$index]);
        $scope = $index % 2 === 0 ? 'manage' : 'view';
        $course->instructors()->attach($actor, ['scope' => $scope]);
        if ($scope === 'manage') {
            $manageable[] = $course->id;
        }
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    expect(ReviewAccess::courseIds($actor))->toEqualCanonicalizing($manageable);
    $reviewQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'course_instructors'));
    expect($reviewQueries)->toHaveCount(1);

    DB::flushQueryLog();
    CourseDirectory::counts($actor);
    $directoryQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'courses'));
    expect($directoryQueries)->toHaveCount(1);

    $rows = CourseDirectory::for($actor)->with('instructors')->get();
    DB::flushQueryLog();
    foreach ($rows as $row) {
        expect(Gate::forUser($actor)->allows('view', $row))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('update', $row))->toBe(in_array($row->id, $manageable, true));
    }
    $pivotQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'course_instructors'));
    expect($pivotQueries)->toBeEmpty();
    DB::disableQueryLog();
})->group('T-P3-01', 'A1', 'A3');

it('loads library reference counts with the table rows', function (): void {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    foreach (range(1, 4) as $index) {
        LibraryItem::query()->create([
            'owner_type' => 'academy', 'kind' => 'file', 'title' => ['en' => 'Reference '.$index],
            'path' => 'library/reference-'.$index.'.txt', 'mime' => 'text/plain', 'size' => 5,
            'sha256' => hash('sha256', (string) $index), 'protection_tier' => 'T1',
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::test(ListLibraryItems::class)->assertOk()->assertSee('Reference 4');
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'media_assets') || str_contains($query['query'], 'lesson_resources'));
    expect($queries)->toHaveCount(1);
    DB::disableQueryLog();
})->group('T-P3-14', 'PRD-09.6');

it('shows the course directory counts and the Egypt price from persisted data', function (): void {
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('admin'));
    $course = Course::query()->create(['title' => ['en' => 'Photography'], 'slug_en' => 'photo', 'slug_ar' => 'photo', 'access_mode' => 'paid']);
    $section = CourseSection::query()->create(['course_id' => $course->id, 'title' => ['en' => 'Introduction']]);
    $lesson = Lesson::query()->create(['type' => 'text', 'title' => ['en' => 'Shutter speed']]);
    CurriculumItem::query()->create(['course_id' => $course->id, 'section_id' => $section->id, 'itemable_type' => 'lesson', 'itemable_id' => $lesson->id]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 59900]);

    Livewire::test(ListCourses::class)
        ->assertTableColumnStateSet('sections_count', 1, $course)
        ->assertTableColumnStateSet('lessons_count', 1, $course)
        ->assertTableColumnStateSet('enrollments_count', 0, $course)
        ->assertTableColumnStateSet('market_price', 59900, $course)
        ->assertSee('EGP');
})->group('T-P3-01', 'PRD-09.1');

it('keeps the bilingual course description formatting while stripping unsafe markup', function (): void {
    $actor = User::factory()->create()->assignRole('admin');
    $draft = app(SaveCourseWizard::class)($actor, ['title' => ['en' => 'Draft'], 'description' => [
        'ar' => '<p><strong>وصف</strong></p>',
        'en' => '<p><strong>Drawing</strong></p><script>alert(1)</script>',
    ]]);

    expect($draft->getTranslation('description', 'ar'))->toContain('<strong>وصف</strong>')
        ->and($draft->getTranslation('description', 'en'))->toContain('<strong>Drawing</strong>')->not->toContain('<script');
})->group('T-P3-02', 'PRD-09.2');
