<?php

declare(strict_types=1);

use App\Domain\Content\RichContent;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config(['media.iframe_allowlist' => ['slides.example.edu']]);
});

function rcText(string $text, array $marks = []): array
{
    return ['type' => 'text', 'text' => $text, 'marks' => $marks];
}

function rcDoc(array ...$blocks): array
{
    return ['type' => 'doc', 'content' => $blocks];
}

function rcHtml(array $doc): string
{
    return (string) RichContent::html($doc);
}

it('neutralizes script, event handlers, unsafe links, raw html and foreign frames', function (array $doc): void {
    $html = rcHtml($doc);

    foreach (['<script', 'onerror', 'onclick', 'javascript:', 'vbscript:', 'data:text', '<img', 'evil.example', '<style', 'srcdoc'] as $needle) {
        expect(strtolower($html))->not->toContain($needle);
    }
})->with([
    'script in text' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('<script>alert(1)</script>')]])],
    'script node' => [rcDoc(['type' => 'script', 'content' => [rcText('alert(1)')]], ['type' => 'paragraph', 'content' => [rcText('ok')]])],
    'html node' => [rcDoc(['type' => 'html', 'content' => '<img src=x onerror=alert(1)>'])],
    'event handler attr' => [rcDoc(['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)', 'style' => 'x'], 'content' => [rcText('hi')]])],
    'javascript link' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('click', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]])]])],
    'mixed case javascript link' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('click', [['type' => 'link', 'attrs' => ['href' => 'JaVaScRiPt:alert(1)']]])]])],
    'data link' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('x', [['type' => 'link', 'attrs' => ['href' => 'data:text/html;base64,PHNjcmlwdD4=']]])]])],
    'whitespace obfuscated link' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('x', [['type' => 'link', 'attrs' => ['href' => "java\tscript:alert(1)"]]])]])],
    'quote breakout in link' => [rcDoc(['type' => 'paragraph', 'content' => [rcText('x', [['type' => 'link', 'attrs' => ['href' => 'https://ok.example/" onclick="alert(1)']]])]])],
    'foreign iframe' => [rcDoc(['type' => 'embed', 'attrs' => ['url' => 'https://evil.example/frame']])],
    'http iframe on allowed host' => [rcDoc(['type' => 'embed', 'attrs' => ['url' => 'http://slides.example.edu/deck']])],
    'javascript iframe' => [rcDoc(['type' => 'embed', 'attrs' => ['url' => 'javascript:alert(1)']])],
    'external image' => [rcDoc(['type' => 'image', 'attrs' => ['src' => 'https://evil.example/x.png', 'alt' => '<b>']])],
    'callout tone injection' => [rcDoc(['type' => 'callout', 'attrs' => ['tone' => 'info" onmouseover="alert(1)'], 'content' => [['type' => 'paragraph', 'content' => [rcText('hi')]]]])],
])->group('T-P3-15', 'A1', 'YG-GAP-CNT-002', 'ED-031');

it('renders the allowed blocks with auto direction and escaped text', function (): void {
    $html = rcHtml(rcDoc(
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [rcText('Top')]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [rcText('Sub')]],
        ['type' => 'paragraph', 'content' => [rcText('Bold ', [['type' => 'bold']]), rcText('a < b & c', [['type' => 'italic']]), ['type' => 'hardBreak'], rcText('الرسم الرقمي')]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [rcText('one')]]]]]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [rcText('two')]]]]]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [rcText('quote')]]]],
        ['type' => 'callout', 'attrs' => ['tone' => 'tip'], 'content' => [['type' => 'paragraph', 'content' => [rcText('tip text')]]]],
        ['type' => 'horizontalRule'],
        ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [rcText('H')]]]], ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [rcText('C')]]]]]]]],
        ['type' => 'embed', 'attrs' => ['url' => 'https://slides.example.edu/deck/1']],
        ['type' => 'paragraph', 'content' => [rcText('site', [['type' => 'link', 'attrs' => ['href' => 'https://example.com/page?a=1&b=2']]])]],
    ));

    expect($html)->toContain('<h2 dir="auto">Top</h2>')->and($html)->toContain('<h3 dir="auto">Sub</h3>')
        ->and($html)->toContain('<strong>Bold </strong>')->and($html)->toContain('<em>a &lt; b &amp; c</em>')->and($html)->toContain('<br>')
        ->and($html)->toContain('الرسم الرقمي')->and($html)->toContain('<ul dir="auto"><li><p dir="auto">one</p></li></ul>')->and($html)->toContain('<ol dir="auto">')
        ->and($html)->toContain('<blockquote dir="auto">')->and($html)->toContain('rc-callout-tip')->and($html)->toContain('<hr>')
        ->and($html)->toContain('<th scope="col">')->and($html)->toContain('<td>')
        ->and($html)->toContain('<iframe src="https://slides.example.edu/deck/1"')->and($html)->toContain('sandbox="')
        ->and($html)->toContain('href="https://example.com/page?a=1&amp;b=2" target="_blank" rel="noopener noreferrer nofollow"');
})->group('T-P3-15', 'A1', 'A2', 'ED-003');

it('rejects a document that is not a document or is too large and limits nesting', function (): void {
    expect(fn () => RichContent::clean('<p>raw</p>'))->toThrow(ValidationException::class)
        ->and(fn () => RichContent::clean(['type' => 'paragraph']))->toThrow(ValidationException::class)
        ->and(fn () => RichContent::clean(rcDoc(['type' => 'paragraph', 'content' => [rcText(str_repeat('a', RichContent::MAX_BYTES))]])))->toThrow(ValidationException::class);
    expect((string) RichContent::html(null))->toBe('')->and((string) RichContent::html(['type' => 'nope']))->toBe('');

    $deep = ['type' => 'paragraph', 'content' => [rcText('deep')]];
    for ($i = 0; $i < 12; $i++) {
        $deep = ['type' => 'blockquote', 'content' => [$deep]];
    }
    expect(rcHtml(rcDoc($deep)))->not->toContain('deep');
})->group('T-P3-15', 'A1');

it('stores cleaned content when a lesson is saved and drops everything else', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Rich'], 'slug_ar' => 'r-'.uniqid(), 'slug_en' => 'r-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $dirty = rcDoc(['type' => 'paragraph', 'attrs' => ['onclick' => 'x'], 'content' => [rcText('safe'), ['type' => 'script', 'text' => 'alert(1)']]], ['type' => 'html', 'content' => '<b>x</b>']);

    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Text'], 'type' => 'text']);
    app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['en' => 'Text'], 'type' => 'text', 'content' => $dirty]);

    $stored = $item->itemable->refresh()->content;
    expect($stored)->toBe(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'safe', 'marks' => []]]]]]);

    app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['en' => 'Text'], 'content' => null]);
    expect($item->itemable->refresh()->content)->toBeNull();
    expect(fn () => app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['en' => 'Text'], 'content' => 'not a doc']))->toThrow(ValidationException::class);
})->group('T-P3-15', 'A1');

it('shows rich lessons to entitled learners with images only from the lesson materials', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Rich'], 'slug_ar' => 'r-'.uniqid(), 'slug_en' => 'r-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Mixed text'], 'type' => 'text']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $own = LessonResource::query()->create(['lesson_id' => $lesson->id, 'type' => 'image', 'title' => ['en' => 'Pic'], 'path' => 'resources/pic.png', 'mime' => 'image/png', 'downloadable' => true, 'visibility' => 'enrolled']);
    $foreign = LessonResource::query()->create(['lesson_id' => Lesson::query()->create(['title' => ['en' => 'Other'], 'type' => 'text'])->id, 'type' => 'image', 'title' => ['en' => 'Theirs'], 'path' => 'resources/theirs.png', 'mime' => 'image/png', 'downloadable' => true, 'visibility' => 'enrolled']);
    app(ManageLessons::class)->update($instructor, $lesson, ['title' => ['en' => 'Mixed text'], 'type' => 'text', 'content' => rcDoc(
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [rcText('مقدمة Intro')]],
        ['type' => 'paragraph', 'content' => [rcText('نص عربي mixed with English text.')]],
        ['type' => 'image', 'attrs' => ['resource_id' => $own->id, 'alt' => 'Own image']],
        ['type' => 'image', 'attrs' => ['resource_id' => $foreign->id, 'alt' => 'Foreign image']],
    )]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $response = $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")->assertOk();
    $response->assertSee('data-lesson-text', false)->assertSee('<h2 dir="auto">مقدمة Intro</h2>', false)->assertSee('alt="Own image"', false)->assertDontSee('Foreign image');

    $this->actingAs(User::factory()->create()->assignRole('student'))->get("/learn/{$course->id}/{$item->id}")->assertNotFound();
})->group('T-P3-15', 'A2', 'ED-003');
