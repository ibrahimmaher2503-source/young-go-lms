<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\ApproveCurriculumDocument;
use App\Domain\Media\Actions\ManageLessonResources;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Domain\Media\PdfInspector;
use App\Domain\Media\SignedFileUrl;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/** Builds a small PDF with $pages page objects; $extra is appended to the trailer dictionary. */
function pdfBytes(int $pages = 2, string $extra = '', string $marker = ''): string
{
    $objects = "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count {$pages}>>endobj\n";
    for ($i = 0; $i < $pages; $i++) {
        $objects .= (3 + $i)." 0 obj<</Type/Page/Parent 2 0 R>>endobj\n";
    }

    return "%PDF-1.4\n{$objects}% {$marker}\ntrailer<</Root 1 0 R{$extra}>>\n%%EOF\n";
}

function pdfWithObjectStream(int $pages): string
{
    $inner = '';
    for ($i = 0; $i < $pages; $i++) {
        $inner .= '<</Type/Page/Parent 2 0 R>> ';
    }
    $stream = gzcompress($inner);

    return "%PDF-1.5\n5 0 obj<</Type/ObjStm/N {$pages}/First 0/Filter/FlateDecode/Length ".strlen($stream).">>\nstream\n".$stream."\nendstream\nendobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

function fileOf(string $bytes): File
{
    $path = tempnam(sys_get_temp_dir(), 'ygp');
    file_put_contents($path, $bytes);

    return new File($path);
}

function filesLesson(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Files'], 'slug_ar' => 'f-'.uniqid(), 'slug_en' => 'f-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']]);

    return [$instructor, $item->itemable, $course];
}

it('inspects pdfs: pages, object streams, encryption, active content and broken files', function (): void {
    $report = fn (string $bytes) => PdfInspector::inspect(fileOf($bytes)->getPathname());

    expect($report(pdfBytes(3)))->toMatchArray(['valid' => true, 'pages' => 3, 'encrypted' => false, 'active_content' => false])
        ->and($report(pdfWithObjectStream(4))['pages'])->toBe(4)
        ->and($report(pdfBytes(1, '/Encrypt 9 0 R'))['encrypted'])->toBeTrue()
        ->and($report(pdfBytes(1, '', '/JavaScript (app.alert(1))'))['active_content'])->toBeTrue()
        ->and($report('%PDF-1.4 truncated, no end marker'))->toMatchArray(['valid' => false, 'pages' => 0])
        ->and($report('plain text'))->toMatchArray(['valid' => false]);
})->group('T-P3-08', 'A3');

it('rejects encrypted, oversized, long, active, unreadable and non-pdf uploads', function (string $kind): void {
    [$instructor, $lesson] = filesLesson();
    config(['media.pdf_max_pages' => 5, 'media.pdf_max_kilobytes' => 1]);

    $bytes = match ($kind) {
        'encrypted' => pdfBytes(1, '/Encrypt 9 0 R'),
        'oversized' => pdfBytes(1, '', str_repeat('x', 2048)),
        'too long' => pdfBytes(6),
        'script' => pdfBytes(1, '', '/JS (this.print())'),
        'truncated' => "%PDF-1.4\n1 0 obj<</Type/Page>>endobj\n",
        'png renamed' => "\x89PNG\r\n\x1a\n".str_repeat("\0", 40),
        'empty' => '',
    };

    expect(fn () => app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf($bytes)))->toThrow(ValidationException::class);
    expect(LessonCurriculumDocument::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['encrypted', 'oversized', 'too long', 'script', 'truncated', 'png renamed', 'empty'])->group('T-P3-08', 'A3', 'ED-031');

it('stores a valid pdf as an inactive version 1 under a random private name', function (): void {
    [$instructor, $lesson] = filesLesson();

    $document = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(4)));

    expect($document->version)->toBe(1)->and($document->pages)->toBe(4)->and($document->status)->toBe('uploaded')->and($document->active)->toBeFalse()
        ->and($document->sha256)->toHaveLength(64)->and($document->path)->toMatch('#^documents/[0-9a-f-]{36}\.pdf$#')->and($document->uploaded_by)->toBe($instructor->id);
    Storage::disk('local')->assertExists($document->path);
    expect($lesson->activeCurriculumDocument())->toBeNull()
        ->and(Activity::query()->where('event', 'curriculum_document.uploaded')->count())->toBe(1);
})->group('T-P3-08', 'A2');

it('treats the same file again as a no-op', function (): void {
    [$instructor, $lesson] = filesLesson();
    $bytes = pdfBytes(2);

    $first = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf($bytes));
    $again = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf($bytes));

    expect($again->id)->toBe($first->id)->and(LessonCurriculumDocument::query()->count())->toBe(1)->and(Storage::disk('local')->allFiles('documents'))->toHaveCount(1);
})->group('T-P3-08', 'A2');

it('keeps the previous version active until the new one is approved and then retires it atomically', function (): void {
    [$instructor, $lesson] = filesLesson();
    $approver = User::factory()->create()->assignRole('admin');

    $v1 = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(2)));
    $v1->update(['status' => 'awaiting_approval']);
    app(ApproveCurriculumDocument::class)($approver, $v1);
    expect($lesson->activeCurriculumDocument()->id)->toBe($v1->id);

    $v2 = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(3)));
    expect($v2->version)->toBe(2)->and($v2->active)->toBeFalse()->and($lesson->activeCurriculumDocument()->id)->toBe($v1->id);

    $v2->update(['status' => 'awaiting_approval']);
    app(ApproveCurriculumDocument::class)($approver, $v2);
    expect($lesson->activeCurriculumDocument()->id)->toBe($v2->id)->and($v1->refresh()->active)->toBeFalse()->and($v1->status)->toBe('ready')
        ->and(LessonCurriculumDocument::query()->where('lesson_id', $lesson->id)->where('active', true)->count())->toBe(1)
        ->and($v2->refresh()->approved_by)->toBe($approver->id)->and($v2->approved_at)->not->toBeNull();
    Storage::disk('local')->assertExists($v1->path); // old versions stay for the trace
})->group('T-P3-08', 'A2');

it('refuses approval by people without the permission and for failed versions', function (): void {
    [$instructor, $lesson] = filesLesson();
    $document = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(2)));

    foreach ([$instructor, User::factory()->create()->assignRole('student'), User::factory()->create()->assignRole('finance')] as $actor) {
        expect(fn () => app(ApproveCurriculumDocument::class)($actor, $document))->toThrow(AuthorizationException::class);
    }
    $document->update(['status' => 'failed']);
    expect(fn () => app(ApproveCurriculumDocument::class)(User::factory()->create()->assignRole('admin'), $document))->toThrow(ValidationException::class);
})->group('T-P3-08', 'A2');

it('lets only managing instructors upload documents', function (): void {
    [, $lesson] = filesLesson();

    foreach ([User::factory()->create()->assignRole('instructor_academy'), User::factory()->create()->assignRole('student')] as $actor) {
        expect(fn () => app(UploadCurriculumDocument::class)($actor, $lesson, fileOf(pdfBytes(1))))->toThrow(AuthorizationException::class);
    }
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->group('T-P3-08', 'A3', 'I02');

it('adds lesson resources of every kind with safe storage and rejects dangerous files', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resources = app(ManageLessonResources::class);

    $pdf = $resources->addFile($instructor, $lesson, fileOf(pdfBytes(2)), ['title' => ['en' => 'Worksheet']]);
    $note = $resources->addFile($instructor, $lesson, fileOf("plain notes\n"), ['title' => ['ar' => 'ملاحظات']]);
    $link = $resources->addLink($instructor, $lesson, ['title' => ['en' => 'Reference'], 'url' => 'https://example.org/guide']);

    expect($pdf->type)->toBe('file')->and($pdf->path)->toMatch('#^resources/[0-9a-f-]{36}\.pdf$#')->and($pdf->mime)->toBe('application/pdf')
        ->and($note->path)->toEndWith('.txt')->and($link->type)->toBe('link')->and($link->downloadable)->toBeFalse()
        ->and($lesson->resources->pluck('sort')->all())->toBe([1, 2, 3]);

    foreach (["MZ\x90\x00\x03\x00\x00\x00\x04\x00", '<?php echo 1;', pdfBytes(1, '/Encrypt 4 0 R')] as $bad) {
        expect(fn () => $resources->addFile($instructor, $lesson, fileOf($bad), ['title' => ['en' => 'x']]))->toThrow(ValidationException::class);
    }
    expect(fn () => $resources->addLink($instructor, $lesson, ['title' => ['en' => 'x'], 'url' => 'http://example.org/a']))->toThrow(ValidationException::class);
    expect(fn () => $resources->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => '  ']]))->toThrow(ValidationException::class);
    expect(LessonResource::query()->count())->toBe(3);
})->group('T-P3-08', 'A3', 'ED-031');

it('removes a resource with its file and audits it', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Gone']]);

    app(ManageLessonResources::class)->remove($instructor, $resource);

    Storage::disk('local')->assertMissing($resource->path);
    expect(LessonResource::query()->count())->toBe(0)->and(Activity::query()->where('event', 'lesson_resource.removed')->count())->toBe(1);
})->group('T-P3-08', 'A3');

it('gives out a signed link only to people who may open the file, and fails closed for learners', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sheet']]);
    $learner = User::factory()->create()->assignRole('student');
    $stranger = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('admin');
    $urls = app(SignedFileUrl::class);

    expect($urls->for($learner, $resource))->toBeNull()
        ->and($urls->for($stranger, $resource))->toBeNull()
        ->and($urls->for($instructor, $resource))->toContain('signature=')
        ->and($urls->for($admin, $resource))->toContain('signature=');
})->group('T-P3-08', 'A1', 'ED-031');

it('serves the file for the account the link was made for, once it is signed and fresh', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sheet']]);
    $url = app(SignedFileUrl::class)->for($instructor, $resource);

    $this->actingAs($instructor)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(preg_replace('/signature=[^&]+/', 'signature=tampered', $url))->assertForbidden();
    $this->get(str_replace('u='.$instructor->id, 'u='.($instructor->id + 1), $url))->assertForbidden();
})->group('T-P3-08', 'A1');

it('refuses a forwarded link: another account cannot use it and an expired link is dead', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sheet']]);
    $url = app(SignedFileUrl::class)->for($instructor, $resource);
    $other = User::factory()->create()->assignRole('admin');

    $this->actingAs($other)->get($url)->assertForbidden();
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->get($url)->assertRedirect('/login');

    $this->actingAs($instructor)->travel(config('media.signed_ttl') + 5)->seconds();
    $this->get($url)->assertForbidden();
})->group('T-P3-08', 'T-P4-06', 'A1');

it('rechecks access when the link is used: a revoked author gets nothing', function (): void {
    [$instructor, $lesson, $course] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sheet']]);
    $url = app(SignedFileUrl::class)->for($instructor, $resource);

    $course->instructors()->detach($instructor->id);

    $this->actingAs($instructor)->get($url)->assertForbidden();
})->group('T-P3-08', 'A1');

it('opens public previews to any signed-in account but never non-downloadable files or links', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resources = app(ManageLessonResources::class);
    $preview = $resources->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Free sample'], 'is_preview' => true]);
    $locked = $resources->addFile($instructor, $lesson, fileOf(pdfBytes(2)), ['title' => ['en' => 'Locked'], 'is_preview' => true, 'downloadable' => false]);
    $link = $resources->addLink($instructor, $lesson, ['title' => ['en' => 'Link'], 'url' => 'https://example.org/x', 'is_preview' => true]);
    $learner = User::factory()->create()->assignRole('student');
    $urls = app(SignedFileUrl::class);

    expect($urls->for($learner, $preview))->not->toBeNull()->and($urls->for($learner, $locked))->toBeNull()->and($urls->for($learner, $link))->toBeNull();
    $this->actingAs($learner)->get($urls->for($learner, $preview))->assertOk();
})->group('T-P3-08', 'A1');

it('opens protected files to entitled learners through the entitlement checker', function (): void {
    [$instructor, $lesson] = filesLesson();
    $resource = app(ManageLessonResources::class)->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sheet']]);
    $document = app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(3)));
    $learner = User::factory()->create()->assignRole('student');
    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('student');

    app()->bind(LessonAccessChecker::class, fn () => new class implements LessonAccessChecker
    {
        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return true;
        }
    });

    $urls = app(SignedFileUrl::class);
    expect($urls->for($learner, $resource))->not->toBeNull()->and($urls->for($learner, $document))->not->toBeNull()->and($urls->for($suspended, $resource))->toBeNull();
    $this->actingAs($learner)->get($urls->for($learner, $document))->assertOk();
})->group('T-P3-08', 'A1');

it('exposes only flagged downloads on a preview lesson until the learner is granted access', function (string $kind): void {
    [$instructor, $lesson, $course] = filesLesson();
    $course->update(['status' => 'published']);
    $item = $lesson->item()->firstOrFail();
    $item->section->update(['published' => true]);
    $lesson->update(['is_preview' => true]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    $resources = app(ManageLessonResources::class);
    $preview = $resources->addFile($instructor, $lesson, fileOf(pdfBytes(1)), ['title' => ['en' => 'Sample'], 'is_preview' => true]);
    $protected = match ($kind) {
        'resource' => $resources->addFile($instructor, $lesson, fileOf(pdfBytes(2)), ['title' => ['en' => 'Worksheet']]),
        'curriculum PDF' => app(UploadCurriculumDocument::class)($instructor, $lesson, fileOf(pdfBytes(3))),
    };
    $learner = User::factory()->create()->assignRole('student');
    $urls = app(SignedFileUrl::class);

    expect(app(EntitlementService::class)->canAccessItem($learner, $item->refresh())->allowed)->toBeTrue();
    $this->actingAs($learner)->get($urls->for($learner, $preview))->assertOk();
    expect($urls->for($learner, $protected))->toBeNull();

    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $this->get($urls->for($learner, $protected))->assertOk();
})->with(['resource', 'curriculum PDF'])->group('T-P4-06', 'A2', 'I01');
