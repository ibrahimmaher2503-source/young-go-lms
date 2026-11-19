<?php

declare(strict_types=1);

// T-G7-FIXES-F | F7 Learner360Record reference_label and notice-kind labels (support.source_refs.*, support.notice_kinds.*)

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Support\Queries\Learner360Record;
use App\Filament\Pages\LearnerThreeSixtyPage;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

afterEach(fn () => app()->setLocale('en'));

/** Creates a published free course with a unique slug pair. */
function g7fCourse(string $tag): Course
{
    return Course::query()->create([
        'title' => ['en' => $tag.' course', 'ar' => 'دورة '.$tag],
        'slug_ar' => 'g7f-'.$tag.'-ar-'.uniqid(), 'slug_en' => 'g7f-'.$tag.'-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
    ]);
}

/** Learner with access rows for a subscription period and an order, plus notices of several kinds. */
function g7fLearnerWithHistory(): User
{
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');

    app(GrantEntitlement::class)($learner, g7fCourse('sub'), EntitlementSource::Subscription, 'subscription_period:1');
    app(GrantEntitlement::class)($learner, g7fCourse('ord'), EntitlementSource::Purchase, 'order:YG-ORD-1');

    // The dispatcher stores class_basename() of the notice, so the fixtures use basenames too.
    $kinds = ['ReviewDecisionNotice', 'CertificateIssuedNotice', 'TemplatedNotice', 'SomethingNewNotice'];
    foreach ($kinds as $index => $class) {
        NotificationDispatch::query()->create([
            'dedupe_key' => 'g7f-'.$index.'-'.uniqid(), 'recipient_id' => $learner->getKey(), 'notification' => $class,
        ]);
    }

    return $learner;
}

/** @return array<string, mixed> */
function g7fRecord(User $viewer, User $learner): array
{
    return app(Learner360Record::class)->for($viewer, $learner);
}

it('F7 a sensitive viewer gets a localized reference label for subscription periods and orders', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $learner = g7fLearnerWithHistory();

    $labels = collect(g7fRecord($admin, $learner)['access'])->pluck('reference_label')->filter()->values()->all();

    expect($labels)->toContain('Subscription period 1')
        ->and($labels)->toContain('Order YG-ORD-1');
});

it('F7 the Arabic reference labels use the Arabic phrasing and never show the raw kind token', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'ar'])->assignRole('admin');
    $learner = g7fLearnerWithHistory();

    app()->setLocale('ar');
    $labels = collect(g7fRecord($admin, $learner)['access'])->pluck('reference_label')->filter()->values()->all();

    expect($labels)->toContain('فترة الاشتراك 1')
        ->and($labels)->toContain('الطلب YG-ORD-1');
    foreach ($labels as $label) {
        expect(str_contains($label, 'subscription_period:'))->toBeFalse();
    }
});

it('F7 an unknown reference prefix renders the reference alone', function (): void {
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    app(GrantEntitlement::class)($learner, g7fCourse('unk'), EntitlementSource::Free, 'mystery:42');

    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $labels = collect(g7fRecord($admin, $learner)['access'])->pluck('reference_label')->filter()->values()->all();

    expect($labels)->toContain('42')->and($labels)->not->toContain('mystery:42');
});

it('F7 a viewer without sensitive learner access gets no reference label', function (): void {
    $support = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('support');
    $learner = g7fLearnerWithHistory();

    expect($support->can('learner.record.sensitive'))->toBeFalse();

    foreach (g7fRecord($support, $learner)['access'] as $row) {
        expect($row['reference_label'])->toBeNull();
    }
});

it('F7 notice kinds render localized labels and unknown kinds fall back to Notice', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $learner = g7fLearnerWithHistory();

    $labels = collect(g7fRecord($admin, $learner)['notices'])->pluck('label')->all();

    expect($labels)->toContain('Course review decision')
        ->and($labels)->toContain('Certificate issued')
        ->and($labels)->toContain('Notice')
        ->and(array_count_values($labels)['Notice'] ?? 0)->toBe(2);
});

it('F7 the Arabic notice labels are localized and omit the English headlines', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'ar'])->assignRole('admin');
    $learner = g7fLearnerWithHistory();

    app()->setLocale('ar');
    $labels = collect(g7fRecord($admin, $learner)['notices'])->pluck('label')->all();

    expect($labels)->not->toContain('Review Decision')
        ->and($labels)->not->toContain('Certificate Issued')
        ->and($labels)->toContain('إشعار');
});

it('F7 the Learner 360 page HTML shows the reference label and no raw subscription token', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $learner = g7fLearnerWithHistory();

    $this->actingAs($admin);
    $html = Livewire::test(LearnerThreeSixtyPage::class, ['learner' => (string) $learner->getKey()])->html();

    expect($html)->toContain('Subscription period 1')
        ->and($html)->toContain('Order YG-ORD-1')
        ->and($html)->not->toContain('subscription_period:');
});

it('F7 the reference and notice keys exist in English and Arabic and keep the ref placeholder', function (): void {
    foreach (['order', 'subscription_period', 'subscription', 'bundle', 'manual_payment', 'manual_grant', 'other'] as $key) {
        $en = __('support.source_refs.'.$key, ['ref' => 'X'], 'en');
        $ar = __('support.source_refs.'.$key, ['ref' => 'X'], 'ar');

        expect($en)->not->toBe('support.source_refs.'.$key)
            ->and($ar)->not->toBe('support.source_refs.'.$key)
            ->and(str_contains($en, 'X'))->toBe(str_contains($ar, 'X'));
    }

    foreach (['help_request', 'essay_reviewed', 'submission_decision', 'certificate_issued', 'review_decision', 'live_session', 'announcement', 'templated', 'other'] as $key) {
        expect(__('support.notice_kinds.'.$key, [], 'en'))->not->toBe('support.notice_kinds.'.$key)
            ->and(__('support.notice_kinds.'.$key, [], 'ar'))->not->toBe('support.notice_kinds.'.$key);
    }
});
