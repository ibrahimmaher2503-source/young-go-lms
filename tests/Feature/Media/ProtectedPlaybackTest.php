<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Queries\LessonPlayback;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('playback-review');
    config(['media.disk' => 'playback-review']);
});

function protectedPlaybackFixture(): array
{
    $author = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Protected'], 'slug_ar' => 'protected', 'slug_en' => 'protected', 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($author->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($author, $course, ['title' => ['en' => 'Section']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($author, $section, ['title' => ['en' => 'Video'], 'type' => 'video']);
    Storage::disk('playback-review')->put('media/private.mp4', 'private-video');
    app(AttachLessonMedia::class)($author, $item->itemable, ['provider' => 'signed_storage', 'path' => 'media/private.mp4']);
    app(SetCurriculumStatus::class)($author, $course, [$item->id], 'published');
    $learner = User::factory()->create()->assignRole('student');
    $grant = app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$author, $course, $item->fresh(), $learner, $grant];
}

it('binds protected playback to its viewer and refuses anonymous or copied access', function (): void {
    [, , $item, $learner] = protectedPlaybackFixture();
    $url = LessonPlayback::for($item->itemable, $learner)->url;
    $this->actingAs($learner)->get($url)->assertOk();
    $other = User::factory()->create()->assignRole('student');
    $this->actingAs($other)->get($url)->assertForbidden();
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->get($url)->assertRedirect('/login');
})->group('T-P3-05', 'T-P4-01', 'A1', 'ED-007', 'I01');

it('rechecks protected playback after access or content is withdrawn', function (string $change): void {
    [, $course, $item, $learner, $grant] = protectedPlaybackFixture();
    $url = LessonPlayback::for($item->itemable, $learner)->url;
    $this->actingAs($learner)->get($url)->assertOk();
    match ($change) {
        'grant' => app(RevokeEntitlement::class)(User::factory()->create()->assignRole('admin'), $grant, 'Review revocation'),
        'course' => $course->update(['status' => 'draft']),
        'item' => $item->update(['status' => 'draft']),
        'account' => $learner->forceFill(['status' => 'inactive'])->save(),
    };
    $this->actingAs($learner->fresh())->get($url)->assertForbidden();
})->with(['grant', 'course', 'item', 'account'])->group('T-P3-05', 'T-P4-01', 'A2', 'I01');

it('preserves authorized draft preview and withdraws it when author scope is removed', function (): void {
    [$author, $course, $item] = protectedPlaybackFixture();
    $item->update(['status' => 'draft']);
    $url = LessonPlayback::for($item->fresh()->itemable, $author)->url;
    $this->actingAs($author)->get($url)->assertOk();
    $course->instructors()->detach($author->id);
    $this->get($url)->assertForbidden();
})->group('T-P3-05', 'T-P4-03', 'A1', 'I02');

it('binds child playback to the active guardian context', function (): void {
    [, , $item, $child] = protectedPlaybackFixture();
    $parent = User::factory()->create()->assignRole('parent');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id]);
    $url = LessonPlayback::for($item->itemable, $child)->url;
    expect($url)->toContain('actor='.$parent->id)->and($url)->toContain('u='.$child->id);
    $this->get($url)->assertOk();
    $this->withSession([ActingLearner::SESSION_KEY => null])->get($url)->assertForbidden();
    $this->actingAs($child)->get($url)->assertForbidden();
})->group('T-P3-05', 'T-P4-01', 'A1', 'I02');

it('keeps an explicit published preview playable without a course grant', function (): void {
    [, , $item] = protectedPlaybackFixture();
    $item->itemable->update(['is_preview' => true]);
    $viewer = User::factory()->create()->assignRole('student');
    $url = LessonPlayback::for($item->fresh()->itemable, $viewer)->url;
    $this->actingAs($viewer)->get($url)->assertOk();
    $item->update(['status' => 'draft']);
    $this->get($url)->assertForbidden();
})->group('T-P3-05', 'T-P4-03', 'A1', 'I01');
