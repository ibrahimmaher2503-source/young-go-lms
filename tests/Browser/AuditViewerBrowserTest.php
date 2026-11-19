<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters audit records by actor and shows the empty state accessibly', function (string $locale, string $theme, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $viewer = User::factory()->create(['locale' => $locale]);
    $viewer->givePermissionTo('panel.admin.access', 'audit.view');
    $selectedActor = User::factory()->create(['name' => 'Mona Audit', 'display_name' => 'Mona Audit']);
    $otherActor = User::factory()->create(['name' => 'Omar Audit', 'display_name' => 'Omar Audit']);
    $noActivityActor = User::factory()->create();
    $course = Course::query()->create([
        'title' => ['en' => 'Audit course', 'ar' => 'دورة التدقيق'],
        'slug_en' => 'audit-course',
        'slug_ar' => 'audit-course-ar',
    ]);
    $audit = app(AuditLogger::class);
    $audit->record('user.roles_changed', $viewer, $selectedActor->id);
    $audit->record('course.duplicated', $course, $otherActor->id);
    $this->actingAs($viewer);

    $directory = base_path('tests/Browser/screenshots/T-P1-04');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $page = $this->visit(route('filament.admin.resources.audit.audit-logs.index', absolute: false));
    $page->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); }');
    $page->assertSee('Mona Audit')->assertSee('Omar Audit');
    expect($page->script('() => Array.from(document.querySelectorAll(".fi-ta-records td"), cell => cell.textContent.trim())'))
        ->toContain(__('audit-events.user.roles_changed', [], $locale))
        ->toContain(__('staff.user', [], $locale))
        ->toContain(__('courses.course', [], $locale));
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $trigger = '.fi-modal-trigger:has(+ .fi-ta-filters-modal) button';
    $dialog = '.fi-ta-filters-modal .fi-modal-window:visible';
    $actorInput = 'input[id$=".criteria.actor"]';
    $apply = '.fi-ta-filters-modal [data-table-filter-action="apply"]';
    $capture = "{$locale}-{$theme}-{$width}";
    $settleModal = 'async () => { await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); await Promise.allSettled(document.getAnimations().map(animation => animation.finished)); }';

    $page->click($trigger)->assertVisible($dialog)->assertVisible($actorInput);
    $page->script($settleModal);
    $subjectInput = 'select[id$=".criteria.subject"]';
    $page->assertVisible($subjectInput)->select($subjectInput, $course->getMorphClass());
    expect($page->script('() => document.querySelector(\'select[id$=".criteria.subject"]\').selectedOptions[0].textContent.trim()'))
        ->toBe(__('courses.course', [], $locale));
    $page->select($subjectInput, '')->assertNoAccessibilityIssues();
    $page->screenshot(fullPage: false, filename: "T-P1-04/filters-open-{$capture}");
    $page->keys($dialog, 'Escape')->assertNotPresent($dialog)->assertVisible($trigger.':focus');

    $page->click($trigger)->fill($actorInput, (string) $selectedActor->id)->click($apply)
        ->assertVisible('.fi-ta-filter-indicators [role="listitem"]')
        ->assertSeeIn('.fi-ta-filter-indicators', __('audit.actor_id', [], $locale).' · '.$selectedActor->id)
        ->assertSeeIn($trigger, '1')->assertDontSee('Omar Audit')->assertSee('Mona Audit');
    $page->script($settleModal);
    $page->assertNotPresent($dialog)->assertNoAccessibilityIssues();
    $page->screenshot(fullPage: false, filename: "T-P1-04/filtered-{$capture}");

    $page->click($trigger)->fill($actorInput, (string) $noActivityActor->id)->click($apply)
        ->assertVisible('.fi-ta-empty-state')->assertSeeIn($trigger, '1')->assertDontSee('Mona Audit');
    $page->script($settleModal);
    $page->assertNotPresent($dialog)->assertSee(__('audit.empty_filtered_hint', [], $locale))
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $page->screenshot(fullPage: false, filename: "T-P1-04/empty-{$capture}");

    $page->click('.fi-ta-filter-indicators button[wire\\:target="removeTableFilter"]')
        ->waitForText('Omar Audit')->assertSee('Mona Audit')->assertNotPresent('.fi-ta-filter-indicators');
})->with([
    ['ar', 'light', 390, 844], ['ar', 'dark', 390, 844],
    ['en', 'light', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['ar', 'dark', 1440, 900],
    ['en', 'light', 1440, 900], ['en', 'dark', 1440, 900],
    ['ar', 'light', 1568, 1003],
])->group('browser', 'T-P1-04', 'A3', 'PRD-22');
