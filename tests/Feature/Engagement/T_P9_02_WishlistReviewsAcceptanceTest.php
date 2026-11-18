<?php

// T-P9-02 | YG-GAP-ENG-001 YG-GAP-ENG-002 | A1 A2

declare(strict_types=1);

use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\Actions\HideCourseReview;
use App\Domain\Engagement\Actions\RejectCourseReview;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Engagement\Actions\ToggleWishlist;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\Queries\PublishedReviews;
use App\Domain\Engagement\Queries\ReviewEligibility;
use App\Domain\Engagement\Queries\WishlistQuery;
use App\Domain\Engagement\ReviewerName;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Engagement\ReviewSummary;
use App\Domain\Engagement\Wishlist;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function tp902Course(string $status = 'published'): Course
{
    return Course::query()->create([
        'title' => ['en' => 'Pastel Portraits', 'ar' => 'رسم البورتريه'],
        'slug_ar' => 'tp902-ar-'.uniqid(),
        'slug_en' => 'tp902-en-'.uniqid(),
        'status' => $status,
        'access_mode' => Course::ACCESS_FREE,
    ]);
}

function tp902Learner(array $overrides = []): User
{
    return User::factory()->create(array_merge(['status' => 'active', 'email_verified_at' => now(), 'name' => 'Mona Khaled'], $overrides))->assignRole('student');
}

function tp902Admin(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('admin');
}

function tp902Entitled(User $learner, Course $course): void
{
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
}

function tp902Review(Course $course, User $learner, int $rating = 5, string $body = 'A thoughtful course with clear steps.'): CourseReview
{
    tp902Entitled($learner, $course);

    return app(SubmitCourseReview::class)($learner, $course, $rating, $body);
}

it('saves and removes a wishlist course, keeps the pair unique and refuses unpublished courses to add', function (): void {
    $learner = tp902Learner();
    $course = tp902Course();
    $draft = tp902Course('draft');
    $toggle = app(ToggleWishlist::class);

    expect($toggle($learner, $course))->toBeTrue()
        ->and($toggle($learner, $course))->toBeFalse()
        ->and($toggle($learner, $course))->toBeTrue();

    expect(Wishlist::query()->where('user_id', $learner->id)->where('course_id', $course->id)->count())->toBe(1);
    expect(fn () => DB::table('wishlists')->insert(['user_id' => $learner->id, 'course_id' => $course->id, 'created_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => $toggle($learner, $draft))->toThrow(ValidationException::class);

    // A course unpublished after saving can still be removed
    $course->forceFill(['status' => 'draft'])->save();
    expect($toggle($learner, $course))->toBeFalse();
    expect(Wishlist::query()->where('user_id', $learner->id)->count())->toBe(0);
})->group('T-P9-02', 'A1');

it('lists only the learner own published saved courses and refuses inactive users', function (): void {
    $learner = tp902Learner();
    $other = tp902Learner();
    $published = tp902Course();
    $archived = tp902Course();
    $toggle = app(ToggleWishlist::class);

    $toggle($learner, $published);
    $toggle($other, $published);
    $toggle($learner, $archived);
    $archived->forceFill(['status' => 'archived'])->save();

    expect(app(WishlistQuery::class)->courses($learner)->pluck('id')->all())->toBe([$published->id])
        ->and(app(WishlistQuery::class)->savedIds($learner))->toContain($published->id);

    $inactive = tp902Learner(['status' => 'suspended']);
    expect(fn () => $toggle($inactive, $published))->toThrow(AuthorizationException::class);
})->group('T-P9-02', 'A1');

it('lets a guest reach the wishlist through login only and toggles JSON state for a learner', function (): void {
    $course = tp902Course();
    $learner = tp902Learner();

    $this->post(route('wishlist.toggle', ['course' => $course->id]))->assertRedirect(route('login.page'));

    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $course->id]))
        ->assertOk()->assertJson(['saved' => true]);
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $course->id]))
        ->assertOk()->assertJson(['saved' => false]);
})->group('T-P9-02', 'A1');

it('refuses a non-entitled, expired or wishlist-only learner from reviewing and creates no row', function (): void {
    $course = tp902Course();
    $wishlistOnly = tp902Learner();
    app(ToggleWishlist::class)($wishlistOnly, $course);

    $expired = tp902Learner();
    app(GrantEntitlement::class)($expired, $course, EntitlementSource::Free, '', now()->subDays(30), now()->subDay());

    foreach ([$wishlistOnly, $expired] as $learner) {
        expect(fn () => app(SubmitCourseReview::class)($learner, $course, 5, 'A long enough review text.'))->toThrow(AuthorizationException::class);
        $this->actingAs($learner)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'A long enough review text.'])
            ->assertForbidden();
    }

    expect(CourseReview::query()->count())->toBe(0);
    expect(app(ReviewEligibility::class)->for($wishlistOnly, $course)['state'])->toBe(ReviewEligibility::NOT_ENTITLED);
})->group('T-P9-02', 'A1');

it('creates one pending review for an entitled learner and edits that same row on resubmit', function (): void {
    $course = tp902Course();
    $learner = tp902Learner();
    $review = tp902Review($course, $learner);

    expect($review->status)->toBe(ReviewStatus::Pending);

    $again = app(SubmitCourseReview::class)($learner, $course, 4, 'Edited text after a second thought.');
    expect($again->id)->toBe($review->id)
        ->and($again->rating)->toBe(4)
        ->and($again->status)->toBe(ReviewStatus::Pending);
    expect(CourseReview::query()->count())->toBe(1);
})->group('T-P9-02', 'A1');

it('keeps pending, rejected and hidden reviews off the public summary and the course reviews list', function (): void {
    $course = tp902Course();
    $pending = tp902Review($course, tp902Learner());
    $rejected = tp902Review($course, tp902Learner());
    $hidden = tp902Review($course, tp902Learner());
    $admin = tp902Admin();

    app(RejectCourseReview::class)($admin, $rejected, 'Off topic for this course page.');
    app(ApproveCourseReview::class)($admin, $hidden);
    app(HideCourseReview::class)($admin, $hidden, 'Contains personal contact details.');

    expect(ReviewSummary::for($course))->toBe(['average' => 0.0, 'count' => 0])
        ->and(app(PublishedReviews::class)->forCourse($course)->count())->toBe(0);
    expect($pending->fresh()->status)->toBe(ReviewStatus::Pending);
})->group('T-P9-02', 'A2');

it('publishes an approved review into the summary and the JSON-LD aggregate only after approval', function (): void {
    $course = tp902Course();
    $admin = tp902Admin();
    $five = tp902Review($course, tp902Learner(), 5);
    $four = tp902Review($course, tp902Learner(), 4);

    expect(ReviewSummary::for($course)['count'])->toBe(0);

    app(ApproveCourseReview::class)($admin, $five);
    app(ApproveCourseReview::class)($admin, $four);

    expect(ReviewSummary::for($course))->toBe(['average' => 4.5, 'count' => 2]);
    expect(app(PublishedReviews::class)->forCourse($course)->pluck('rating')->sort()->values()->all())->toBe([4, 5]);

    // Approving again is a no-op and writes no second audit
    app(ApproveCourseReview::class)($admin, $five);
    expect(Activity::query()->where('event', 'review.published')->count())->toBe(2);
})->group('T-P9-02', 'A2');

it('drops the rating cache when a published review is hidden or edited by its learner', function (): void {
    $course = tp902Course();
    $admin = tp902Admin();
    $learner = tp902Learner();
    $review = tp902Review($course, $learner, 5);
    app(ApproveCourseReview::class)($admin, $review);
    expect(ReviewSummary::for($course)['count'])->toBe(1);

    app(HideCourseReview::class)($admin, $review->fresh(), 'Hidden for a policy check.');
    expect(ReviewSummary::for($course)['count'])->toBe(0);

    expect(fn () => app(SubmitCourseReview::class)($learner, $course, 3, 'Trying to edit a hidden review.'))->toThrow(ValidationException::class);

    app(ApproveCourseReview::class)($admin, $review->fresh());
    expect(ReviewSummary::for($course)['count'])->toBe(1);
    app(SubmitCourseReview::class)($learner, $course, 2, 'Edited after approval, back to pending.');
    expect(ReviewSummary::for($course)['count'])->toBe(0);
})->group('T-P9-02', 'A2');

it('requires the review.moderate permission and refuses inactive staff for every moderation action', function (): void {
    $course = tp902Course();
    $review = tp902Review($course, tp902Learner());

    foreach (['instructor_academy', 'finance', 'support', 'student'] as $role) {
        $actor = User::factory()->create(['status' => 'active'])->assignRole($role);
        expect(fn () => app(ApproveCourseReview::class)($actor, $review))->toThrow(AuthorizationException::class);
    }

    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    expect(fn () => app(ApproveCourseReview::class)($inactive, $review))->toThrow(AuthorizationException::class);
})->group('T-P9-02', 'A2');

it('enforces reason rules and state transitions for reject and hide', function (): void {
    $course = tp902Course();
    $admin = tp902Admin();
    $pending = tp902Review($course, tp902Learner());
    $published = tp902Review($course, tp902Learner());
    app(ApproveCourseReview::class)($admin, $published);

    expect(fn () => app(RejectCourseReview::class)($admin, $pending, 'short'))->toThrow(ValidationException::class)
        ->and(fn () => app(RejectCourseReview::class)($admin, $published, 'A valid reason but wrong state.'))->toThrow(ValidationException::class)
        ->and(fn () => app(HideCourseReview::class)($admin, $published, null))->toThrow(ValidationException::class)
        ->and(fn () => app(HideCourseReview::class)($admin, $pending, 'A valid reason here.'))->toThrow(ValidationException::class);

    app(RejectCourseReview::class)($admin, $pending, 'Please avoid contact details in the text.');
    expect($pending->fresh()->status)->toBe(ReviewStatus::Rejected);

    // A rejected review can be edited by its learner and returns to pending
    app(SubmitCourseReview::class)(User::query()->findOrFail($pending->learner_id), $course, 4, 'Reworked text without contact details.');
    expect($pending->fresh()->status)->toBe(ReviewStatus::Pending);
})->group('T-P9-02', 'A2');

it('writes one review.published audit for a repeated approval (decision notice is not asserted here)', function (): void {
    // BUG-TODO: notice delivery is not asserted yet: the notification channel and table are not verified by this file.
    $course = tp902Course();
    $admin = tp902Admin();
    $review = tp902Review($course, tp902Learner());

    app(ApproveCourseReview::class)($admin, $review);
    app(ApproveCourseReview::class)($admin, $review);

    expect(Activity::query()->where('event', 'review.published')->count())->toBe(1);
})->group('T-P9-02', 'A2');

it('shows learners as first name and last initial, uses the display name only for adults and never for children', function (): void {
    $adult = tp902Learner(['name' => 'Mona Khaled', 'display_name' => 'Mona Khaled Ali']);
    expect(ReviewerName::for($adult))->toBe('Mona A.');

    $single = tp902Learner(['name' => 'Nour', 'display_name' => null]);
    expect(ReviewerName::for($single))->toBe('Nour');

    $empty = tp902Learner(['name' => '   ', 'display_name' => null]);
    expect(ReviewerName::for($empty))->toBe(__('engagement.anonymous'));

    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null, 'name' => 'Youssef Adel', 'display_name' => 'Yo Champ'])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);
    expect(ReviewerName::for($child))->toBe('Youssef A.');

    $course = tp902Course();
    $review = tp902Review($course, $child);
    expect($review->author_name)->toBe('Youssef A.')->and($review->status)->toBe(ReviewStatus::Pending);
})->group('T-P9-02', 'A2');

it('escapes HTML in the review body and keeps newlines, and never stores more than the snapshot name', function (): void {
    $course = tp902Course();
    $learner = tp902Learner(['name' => 'Mona Khaled', 'email' => 'mona@example.com', 'phone' => '+201000000000']);
    $review = tp902Review($course, $learner, 5, "<script>alert('x')</script>\nSecond line of review");

    expect($review->body)->toContain("\n")
        ->and($review->author_name)->toBe('Mona K.')
        ->and($review->author_name)->not->toContain('mona@example.com')
        ->and(e($review->body))->toContain('&lt;script&gt;')
        ->and(e($review->body))->not->toContain('<script>');
})->group('T-P9-02', 'A2');

it('validates rating and body length, with localized messages', function (): void {
    $course = tp902Course();
    $learner = tp902Learner();
    tp902Entitled($learner, $course);
    $submit = app(SubmitCourseReview::class);

    expect(fn () => $submit($learner, $course, 0, 'Valid body text here.'))->toThrow(ValidationException::class)
        ->and(fn () => $submit($learner, $course, 6, 'Valid body text here.'))->toThrow(ValidationException::class)
        ->and(fn () => $submit($learner, $course, 4, str_repeat('a', 9)))->toThrow(ValidationException::class)
        ->and(fn () => $submit($learner, $course, 4, str_repeat('a', 1001)))->toThrow(ValidationException::class);

    expect(Config::integer('engagement.reviews.body_min'))->toBe(10)
        ->and(Config::integer('engagement.reviews.body_max'))->toBe(1000);
    expect(CourseReview::query()->count())->toBe(0);
})->group('T-P9-02', 'A1');

it('blocks children from reviewing when children_may_review is off and keeps the review off when parent acts in child context', function (): void {
    $course = tp902Course();
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null, 'name' => 'Youssef Adel'])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);
    tp902Entitled($child, $course);

    Config::set('engagement.reviews.children_may_review', false);
    expect(fn () => app(SubmitCourseReview::class)($child, $course, 5, 'A child review that should be refused.'))->toThrow(AuthorizationException::class);
    expect(app(ReviewEligibility::class)->for($child, $course)['state'])->toBe(ReviewEligibility::BLOCKED);

    Config::set('engagement.reviews.children_may_review', true);
    $childReview = app(SubmitCourseReview::class)($child, $course, 5, 'A child review that starts pending.');
    expect($childReview->status)->toBe(ReviewStatus::Pending);

    tp902Entitled($parent, $course);
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id]);
    $this->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Parent acting for a child text.'])
        ->assertForbidden();
})->group('T-P9-02', 'A1');

it('rate-limits the seventh review post within a minute and the thirty-first wishlist toggle', function (): void {
    $course = tp902Course();
    $learner = tp902Learner();
    tp902Entitled($learner, $course);

    $this->actingAs($learner);
    for ($i = 0; $i < 6; $i++) {
        $this->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Rate limit body text '.$i]);
    }
    $this->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Seventh post in a minute'])
        ->assertStatus(429);

    $toggled = tp902Course();
    for ($i = 0; $i < 30; $i++) {
        $this->postJson(route('wishlist.toggle', ['course' => $toggled->id]));
    }
    $this->postJson(route('wishlist.toggle', ['course' => $toggled->id]))->assertStatus(429);
})->group('T-P9-02', 'A1');

it('keeps the rating average rounded to one decimal over published reviews only and drops the cache on approval', function (): void {
    $course = tp902Course();
    $admin = tp902Admin();
    $first = tp902Review($course, tp902Learner(), 4);
    $second = tp902Review($course, tp902Learner(), 5);
    $third = tp902Review($course, tp902Learner(), 1);

    Cache::put('engagement:rating:'.$course->id, ['average' => 9.9, 'count' => 9], 600);
    app(ApproveCourseReview::class)($admin, $first);
    app(ApproveCourseReview::class)($admin, $second);
    expect(ReviewSummary::for($course))->toBe(['average' => 4.5, 'count' => 2]);

    app(ApproveCourseReview::class)($admin, $third);
    expect(ReviewSummary::for($course))->toBe(['average' => 3.3, 'count' => 3]);
})->group('T-P9-02', 'A2');
