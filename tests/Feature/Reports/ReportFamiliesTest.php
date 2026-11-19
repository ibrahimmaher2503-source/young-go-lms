<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    app()->setLocale('en');
});

function reportTestCourse(string $slug): Course
{
    $course = new Course([
        'title' => ['en' => $slug], 'slug_ar' => $slug, 'slug_en' => $slug,
        'status' => 'draft', 'access_mode' => 'free',
    ]);
    $course->forceFill(['created_at' => '2026-11-12 10:00:00'])->save();

    return $course;
}

function reportTestFilters(): ReportFilters
{
    return ReportFilters::fromInput(['from' => '2026-11-10', 'to' => '2026-11-15']);
}

it('adds an admin-only platform overview with date-windowed counts', function (): void {
    $catalog = app(ReportCatalog::class);
    $key = ReportKey::tryFrom('platform_overview');
    expect($key)->not->toBeNull();
    $admin = User::factory()->create()->assignRole('admin');
    $finance = User::factory()->create()->assignRole('finance');
    $student = User::factory()->create(['created_at' => '2026-11-12 10:00:00'])->assignRole('student');
    reportTestCourse('new-course');
    reportTestCourse('old-course')->forceFill(['created_at' => '2026-11-09 10:00:00'])->save();

    expect($catalog->canView($admin, $key))->toBeTrue()
        ->and($catalog->canView($finance, $key))->toBeFalse()
        ->and($catalog->canView($student, $key))->toBeFalse();
    $definition = $catalog->definition($key);
    $row = iterator_to_array($definition->rows($catalog->scope($admin, $key, reportTestFilters())))[0];
    expect(array_combine(array_keys($definition->columns()), $row))->toMatchArray(['courses' => 1, 'students' => 1]);
})->group('T-P9-06', 'PRD-21');

it('lists student enrollment pairs without exposing names and rejects a forged course filter', function (): void {
    $catalog = app(ReportCatalog::class);
    $key = ReportKey::tryFrom('student_progress');
    expect($key)->not->toBeNull();
    $teacher = User::factory()->create()->assignRole('instructor_academy');
    $student = User::factory()->create()->assignRole('student');
    $own = reportTestCourse('own-course');
    $other = reportTestCourse('other-course');
    DB::table('course_instructors')->insert(['course_id' => $own->id, 'instructor_id' => $teacher->id]);
    foreach ([$own, $other] as $course) {
        app(GrantEntitlement::class)($student, $course, EntitlementSource::Free, startsAt: CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC'));
    }
    $item = reportTestLesson($own);
    DB::table('lesson_progress')->insert([
        'curriculum_item_id' => $item, 'learner_id' => $student->id, 'status' => 'completed',
        'last_accessed_at' => '2026-11-12 11:00:00', 'completed_at' => '2026-11-12 11:00:00',
    ]);

    $definition = $catalog->definition($key);
    $filters = $catalog->scope($teacher, $key, reportTestFilters());
    expect($definition->count($filters))->toBe(1)
        ->and(json_encode(iterator_to_array($definition->rows($filters))))->toContain('own-course')->not->toContain($student->name)
        ->and(array_combine(array_keys($definition->columns()), iterator_to_array($definition->rows($filters))[0]))->toMatchArray([
            'learner_reference' => $student->id, 'completed_lessons' => 1, 'last_activity_at' => '2026-11-12 11:00:00',
        ])
        ->and($catalog->canView($student, $key))->toBeFalse();
    $forged = ReportFilters::fromInput(['from' => '2026-11-10', 'to' => '2026-11-15', 'course' => $other->id]);
    expect($definition->count($catalog->scope($teacher, $key, $forged)))->toBe(0);
})->group('T-P9-06', 'PRD-21', 'A2');

it('counts missing persisted lesson assets and localizes the content report', function (): void {
    $catalog = app(ReportCatalog::class);
    $key = ReportKey::tryFrom('content');
    expect($key)->not->toBeNull();
    $admin = User::factory()->create()->assignRole('admin');
    $course = reportTestCourse('content-course');
    $item = reportTestLesson($course);

    $definition = $catalog->definition($key);
    $first = iterator_to_array($definition->rows($catalog->scope($admin, $key, reportTestFilters())))[0];
    expect(array_combine(array_keys($definition->columns()), $first))->toMatchArray([
        'sections' => 1, 'lessons' => 1, 'missing_thumbnails' => 1, 'missing_pdfs' => 1, 'missing_media' => 1,
    ]);
    $lesson = DB::table('curriculum_items')->where('id', $item)->value('itemable_id');
    $course->forceFill(['thumbnail_path' => 'courses/cover.webp'])->save();
    DB::table('lesson_curriculum_documents')->insert([
        'lesson_id' => $lesson, 'version' => 1, 'path' => 'lessons/curriculum.pdf',
        'sha256' => hash('sha256', 'fixture'), 'size' => 12, 'pages' => 1,
    ]);
    DB::table('media_assets')->insert([
        'lesson_id' => $lesson, 'provider' => 'youtube', 'kind' => 'video', 'external_id' => 'fixture',
        'protection_tier' => 'T3', 'exception_at' => '2026-11-12 11:00:00',
    ]);
    $updated = iterator_to_array($definition->rows($catalog->scope($admin, $key, reportTestFilters())))[0];
    expect(array_combine(array_keys($definition->columns()), $updated))->toMatchArray([
        'missing_thumbnails' => 0, 'missing_pdfs' => 0, 'missing_media' => 0, 'media_exceptions' => 1, 'tier_open' => 1,
    ]);
    foreach (['en', 'ar'] as $locale) {
        expect(trans('reports.names.content', [], $locale))->not->toBe('reports.names.content');
    }
})->group('T-P9-06', 'PRD-21');

it('summarizes course progress and approved ratings without exposing learner review content', function (): void {
    $course = reportTestCourse('course-performance');
    $admin = User::factory()->create()->assignRole('admin');
    foreach ([['published', 4, 75], ['pending', 1, 25]] as [$status, $rating, $progress]) {
        $learner = User::factory()->create();
        DB::table('course_progress')->insert([
            'course_id' => $course->id, 'learner_id' => $learner->id, 'percent' => $progress, 'last_activity_at' => '2026-11-12 10:00:00',
        ]);
        DB::table('course_reviews')->insert([
            'course_id' => $course->id, 'learner_id' => $learner->id, 'rating' => $rating, 'body' => 'Private review text',
            'author_name' => 'Private author', 'status' => $status, 'created_at' => '2026-11-12 10:00:00',
        ]);
    }
    $section = DB::table('course_sections')->insertGetId(['course_id' => $course->id, 'title' => '{}']);
    $assignment = DB::table('assignments')->insertGetId(['title' => '{}']);
    DB::table('curriculum_items')->insert([
        'course_id' => $course->id, 'section_id' => $section, 'itemable_type' => 'assignment', 'itemable_id' => $assignment,
    ]);
    $submission = DB::table('assignment_submissions')->insertGetId([
        'assignment_id' => $assignment, 'learner_id' => $learner->id, 'status' => 'graded', 'current_revision' => 1,
    ]);
    foreach (['accepted', 'graded'] as $decision) {
        DB::table('submission_decisions')->insert([
            'submission_id' => $submission, 'revision_number' => 1, 'decision' => $decision, 'created_at' => '2026-11-12 12:00:00',
        ]);
    }

    $catalog = app(ReportCatalog::class);
    $report = $catalog->definition(ReportKey::Enrollments);
    $filters = ReportFilters::fromInput(['from' => '2026-11-10', 'to' => '2026-11-15', 'course' => $course->id]);
    $row = array_combine(array_keys($report->columns()), iterator_to_array($report->rows($catalog->scope($admin, ReportKey::Enrollments, $filters)))[0]);
    expect($row)->toMatchArray([
        'average_progress' => 50.0, 'approved_reviews' => 1, 'average_rating' => 4.0,
        'graded_assignments' => 1, 'accepted_assignments' => 0, 'rejected_assignments' => 0,
    ])
        ->and(json_encode($row))->not->toContain('Private');
})->group('T-P9-06', 'PRD-21');

it('attributes discounted bundle sales to each course without exposing revenue to instructors', function (): void {
    $first = reportTestCourse('first-course-revenue');
    $second = reportTestCourse('second-course-revenue');
    $finance = User::factory()->create()->assignRole('finance');
    $payer = User::factory()->create();
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $first->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => 'manage']);
    $order = DB::table('orders')->insertGetId([
        'reference' => 'REPORT-COURSE-REVENUE', 'payer_id' => $payer->id, 'market' => 'EG', 'currency' => 'EGP',
        'subtotal_minor' => 1200, 'discount_minor' => 200, 'total_minor' => 1000, 'status' => 'paid', 'paid_at' => '2026-11-12 10:00:00',
        'idempotency_key' => 'report-course-revenue', 'request_hash' => hash('sha256', 'report-course-revenue'),
    ]);
    $bundleItem = DB::table('order_items')->insertGetId([
        'order_id' => $order, 'product_type' => 'bundle', 'product_id' => 1, 'learner_id' => $payer->id,
        'unit_price_minor' => 1000, 'discount_minor' => 200, 'allocated_minor' => 800, 'currency' => 'EGP',
    ]);
    DB::table('order_items')->insert([
        'order_id' => $order, 'product_type' => 'course', 'product_id' => $first->id, 'learner_id' => $payer->id,
        'unit_price_minor' => 200, 'allocated_minor' => 200, 'currency' => 'EGP',
    ]);
    DB::table('order_item_allocations')->insert([
        ['order_item_id' => $bundleItem, 'course_id' => $first->id, 'allocated_minor' => 600, 'weight' => 60],
        ['order_item_id' => $bundleItem, 'course_id' => $second->id, 'allocated_minor' => 400, 'weight' => 40],
    ]);
    $catalog = app(ReportCatalog::class);
    $key = ReportKey::tryFrom('course_sales');
    expect($key)->not->toBeNull();
    expect($catalog->canView($instructor, $key))->toBeFalse();
    $report = $catalog->definition($key);
    $filters = $catalog->scope($finance, $key, reportTestFilters());
    expect(iterator_to_array($report->rows($filters)))->toBe([
        ['first-course-revenue', 'EGP', 6.8],
        ['second-course-revenue', 'EGP', 3.2],
    ])->and(array_column($report->totals($filters), 'value'))->toBe([10.0]);
    $filtered = ReportFilters::fromInput(['from' => '2026-11-10', 'to' => '2026-11-15', 'course' => $second->id]);
    expect(iterator_to_array($report->rows($catalog->scope($finance, $key, $filtered))))->toBe([['second-course-revenue', 'EGP', 3.2]]);
})->group('T-P9-06', 'PRD-21', 'A1', 'A2');

function reportTestLesson(Course $course): int
{
    $section = DB::table('course_sections')->insertGetId(['course_id' => $course->id, 'title' => '{}']);
    $lesson = DB::table('lessons')->insertGetId(['type' => 'video', 'title' => '{}']);

    return DB::table('curriculum_items')->insertGetId([
        'course_id' => $course->id, 'section_id' => $section, 'itemable_type' => 'lesson', 'itemable_id' => $lesson,
    ]);
}
