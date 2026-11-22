<?php

declare(strict_types=1);

// T-P9-05 | PRD 20, Checklist 11 | A1 A2

use App\Domain\Notifications\Actions\SaveNotificationTemplate;
use App\Domain\Notifications\Announcement;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Filament\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

function t905fCourse(string $suffix): Course
{
    return Course::query()->create([
        'title' => ['en' => 'T905F '.$suffix],
        'slug_ar' => 't905f-ar-'.$suffix.'-'.uniqid(),
        'slug_en' => 't905f-en-'.$suffix.'-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
}

function t905fAnnouncement(Course $course, string $title): Announcement
{
    return Announcement::query()->create([
        'course_id' => $course->getKey(),
        'title_ar' => $title, 'title_en' => $title, 'body_ar' => 'x', 'body_en' => 'x',
        'status' => Announcement::DRAFT, 'author_id' => User::factory()->create()->getKey(),
    ]);
}

it('lists every editable matrix event on the admin template page and mounts the row sync', function (): void {
    // super_admin is the role that holds system.settings.manage; the admin role does not.
    $superAdmin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('super_admin');
    $this->actingAs($superAdmin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(NotificationTemplateResource::canViewAny())->toBeTrue();

    Livewire::test(ListNotificationTemplates::class)->assertSuccessful();

    $editable = array_filter(NotificationMatrix::all(), static fn ($row): bool => $row->editable());
    expect(NotificationTemplate::query()->count())->toBe(count($editable))
        ->and(NotificationTemplate::query()->whereIn('event_key', ['account.verify', 'account.password_reset'])->exists())->toBeFalse();
})->group('T-P9-05', 'A1');

it('adds a wording override from the template list without replacing a saved template', function (): void {
    $superAdmin = User::factory()->create(['locale' => 'en'])->assignRole('super_admin');
    $this->actingAs($superAdmin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $page = Livewire::test(ListNotificationTemplates::class)->assertActionVisible('addTemplate');
    $template = NotificationTemplate::query()->where('event_key', 'live.reminder')->sole();
    $page->callAction('addTemplate', ['event_key' => 'live.reminder', 'title_en' => 'Reminder for :title'])
        ->assertHasNoActionErrors()->assertNotified(__('notification_events.template_saved'));
    expect($template->fresh()->title_en)->toBe('Reminder for :title');
    $this->assertDatabaseHas('activity_log', ['event' => 'notification_template.updated', 'subject_id' => $template->id, 'causer_id' => $superAdmin->id]);

    $save = app(SaveNotificationTemplate::class);
    expect(fn () => $save($superAdmin, 'live.reminder', ['title_en' => 'Overwrite'], onlyUncustomized: true))->toThrow(ValidationException::class)
        ->and(fn () => $save($superAdmin, 'access.granted', [], onlyUncustomized: true))->toThrow(ValidationException::class)
        ->and(fn () => $save($superAdmin, 'account.verify', ['title_en' => 'Test'], onlyUncustomized: true))->toThrow(ValidationException::class)
        ->and($template->fresh()->title_en)->toBe('Reminder for :title');

    $page->callTableAction('edit', $template, ['title_en' => 'Edited :title'])->assertHasNoTableActionErrors();
    expect($template->fresh()->title_en)->toBe('Edited :title');
})->group('T-P9-05', 'A1', 'PRD-20');

it('keeps the template page closed to support, finance and instructors', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    foreach (['support', 'finance', 'instructor_academy'] as $role) {
        $user = User::factory()->create(['status' => 'active'])->assignRole($role);
        $this->actingAs($user);

        expect(NotificationTemplateResource::canViewAny())->toBeFalse();
    }

    $this->actingAs(User::factory()->create()->assignRole('support'))->get('/admin/notification-templates')->assertForbidden();
    $this->assertDatabaseCount('notification_templates', 0);
})->group('T-P9-05', 'A1');

it('identifies the selected event and current wording in the table editor', function (string $locale): void {
    $actor = User::factory()->create(['locale' => $locale])->assignRole('super_admin');
    $this->actingAs($actor);
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $page = Livewire::test(ListNotificationTemplates::class);
    $due = NotificationTemplate::query()->where('event_key', 'assignment.due')->sole();
    $published = app(SaveNotificationTemplate::class)($actor, 'assignment.published', ['title_en' => 'Published :title']);

    foreach ([[$due, 'default', 'optional'], [$published, 'custom', 'mandatory']] as [$template, $wording, $category]) {
        $event = NotificationMatrix::find($template->event_key);
        $page->assertTableColumnStateSet('event_key', $event->label(), $template)
            ->assertTableColumnStateSet('custom', $wording, $template)
            ->mountTableAction('edit', $template);

        $action = $page->instance()->getMountedAction();
        expect($action?->getModalHeading())->toBe(__('notification_events.template_edit_event', ['event' => $event->label()]))
            ->and($action?->getModalDescription())->toContain(__('notification_events.wording_'.$wording), __('notification_events.category_'.$category))
            ->and($action?->getModalSubmitAction()?->getLabel())->toBe(__('staff.save_changes'));

        $page->unmountTableAction();
    }

    $page->callTableAction('edit', $due, ['body_en' => ':title is due on :date.'])->assertHasNoTableActionErrors();
    expect($due->fresh()->body_en)->toBe(':title is due on :date.')
        ->and($published->fresh()->title_en)->toBe('Published :title')
        ->and($published->fresh()->body_en)->toBeNull();
})->with(['ar', 'en'])->group('T-P9-05', 'A1', 'PRD-20');

it('shows an instructor only the announcements of their own courses and hides publish once published', function (): void {
    $own = t905fCourse('own');
    $foreign = t905fCourse('foreign');
    $instructor = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('instructor_academy');
    $own->instructors()->attach($instructor->getKey(), ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    $ownDraft = t905fAnnouncement($own, 'Own draft');
    $ownPublished = t905fAnnouncement($own, 'Own published');
    $ownPublished->forceFill(['status' => Announcement::PUBLISHED, 'published_at' => now()])->save();
    $foreignDraft = t905fAnnouncement($foreign, 'Foreign draft');

    $this->actingAs($instructor);
    Filament::setCurrentPanel(Filament::getPanel('instructor'));

    Livewire::test(ListAnnouncements::class)
        ->assertCanSeeTableRecords([$ownDraft, $ownPublished])
        ->assertCanNotSeeTableRecords([$foreignDraft])
        ->assertTableActionVisible('publish', $ownDraft)
        ->assertTableActionHidden('publish', $ownPublished);

    expect(AnnouncementResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$ownDraft->getKey(), $ownPublished->getKey()]);
})->group('T-P9-05', 'A1');

it('lets an admin see announcements of every course', function (): void {
    $course = t905fCourse('admin');
    $draft = t905fAnnouncement($course, 'Admin sees this');
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListAnnouncements::class)->assertCanSeeTableRecords([$draft]);
})->group('T-P9-05', 'A1');
