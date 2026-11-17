<?php

declare(strict_types=1);

use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Ingestion\IngestCurriculumDocument;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function browserCurriculum(string $locale): array
{
    $instructor = User::factory()->create(['locale' => $locale, 'name' => 'Instructor'])->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'bc-'.uniqid(), 'slug_en' => 'bc-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $one = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية في الرسم الرقمي', 'en' => 'Getting started']]);
    $two = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'تطبيقات عملية', 'en' => 'Practice']]);
    $items = [];
    foreach ([[$one, 'التعرف على الأدوات', 'Know your tools', 720], [$one, 'الخطوط والأشكال', 'Lines and shapes', 960], [$one, 'الضوء والظل', 'Light and shadow', 1080], [$two, 'رسم العناصر البسيطة', 'Simple elements', 1320]] as [$section, $ar, $en, $seconds]) {
        $items[] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => $ar, 'en' => $en], 'duration_seconds' => $seconds]);
    }
    app(SetCurriculumStatus::class)($instructor, $course, [$items[0]->id, $items[1]->id, $items[2]->id], 'published');

    return [$course, $instructor, [$one, $two], $items];
}

it('renders the curriculum manager accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, $instructor] = browserCurriculum($locale);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P3-04');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-04/curriculum-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-04', 'A1', 'PRD-09.5');

it('saves a drag and drop reorder across sections', function (): void {
    [$course, $instructor, $sections, $items] = browserCurriculum('en');
    $this->withSession(['locale' => 'en']);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize(1440, 1200);
    $page->drag("[data-item=\"{$items[3]->id}\"]", "[data-item=\"{$items[0]->id}\"]")->wait(1.5);

    $order = CurriculumOutline::forManager($course)->flatMap(fn ($s) => $s->items->pluck('id'))->map(fn ($id) => (int) $id)->all();
    expect($order)->not->toBe([$items[0]->id, $items[1]->id, $items[2]->id, $items[3]->id])
        ->and(count($order))->toBe(4);
})->group('browser', 'T-P3-04', 'A1');

it('reorders with the keyboard buttons', function (): void {
    [$course, $instructor, , $items] = browserCurriculum('en');
    $this->withSession(['locale' => 'en']);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum");
    $page->click("[data-item=\"{$items[1]->id}\"] button[aria-label^=\"Move up\"]")->wait(1.5);

    $first = CurriculumOutline::forManager($course)->first()->items->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($first)->toBe([$items[1]->id, $items[0]->id, $items[2]->id]);
})->group('browser', 'T-P3-04', 'A1');

it('renders the lesson editor with the upload field accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, $instructor, , $items] = browserCurriculum($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize($width, $height);
    $page->click("[data-item=\"{$items[0]->id}\"] .yg-cur-actions button:nth-child(3)")->wait(1.5);
    $page->select('[id$="media_provider"]', 'signed_storage')->wait(1.5);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P3-06');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "T-P3-06/lesson-editor-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-06', 'A3', 'PRD-09.6');

it('shows the curriculum PDF and material fields and the PDF badge accessibly', function (string $locale, int $width, int $height): void {
    [$course, $instructor, , $items] = browserCurriculum($locale);
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    $path = tempnam(sys_get_temp_dir(), 'ygp');
    file_put_contents($path, $pdf);
    app(UploadCurriculumDocument::class)($instructor, $items[0]->itemable, new File($path));
    $this->withSession(['locale' => $locale]);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize($width, $height);
    $page->assertSee(__('curriculum.pdf_version', ['version' => 1], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $directory = base_path('tests/Browser/screenshots/T-P3-08');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-08/lesson-pdf-badge-{$locale}-{$width}");

    $page->click("[data-item=\"{$items[0]->id}\"] .yg-cur-actions button:nth-child(3)")->wait(1.5);
    $page->script("() => document.querySelector('[id\$=\"curriculum_pdf\"]')?.scrollIntoView({block: 'center'})");
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $page->screenshot(fullPage: false, filename: "T-P3-08/lesson-editor-pdf-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-08', 'A2', 'PRD-09.7');

it('shows the PDF text approval screen accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, , , $items] = browserCurriculum($locale);
    $lesson = $items[0]->itemable;
    Storage::fake('local');
    Storage::disk('local')->put('documents/browser.pdf', '%PDF-1.4 fake');
    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'documents/browser.pdf', 'sha256' => hash('sha256', uniqid()), 'size' => 13, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false,
    ]);
    App::instance(PdfTextExtractor::class, new class implements PdfTextExtractor
    {
        public function extract(string $absolutePath): array
        {
            return [
                'الضوء والظل يعطيان الرسم عمقاً. الضوء يأتي من مصدر واحد، والظل يتبع اتجاه الضوء دائماً.',
                'Light and shadow give a drawing depth. Shadow follows the direction of the light source.',
            ];
        }
    });
    app(IngestCurriculumDocument::class)($document);
    $admin = User::factory()->create(['locale' => $locale])->assignRole('admin');
    $this->withSession(['locale' => $locale]);
    $this->actingAs($admin);

    $page = $this->visit("/admin/courses/{$course->id}/curriculum/documents/{$document->id}")->resize($width, $height);
    $page->assertSee(__('media.approve', [], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P3-09');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-09/approval-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-09', 'A5', 'PRD-09.7');
