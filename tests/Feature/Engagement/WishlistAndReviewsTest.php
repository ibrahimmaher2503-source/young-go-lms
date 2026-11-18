<?php

declare(strict_types=1);

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\Actions\HideCourseReview;
use App\Domain\Engagement\Actions\RejectCourseReview;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Engagement\Actions\ToggleWishlist;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\Notifications\ReviewDecisionNotice;
use App\Domain\Engagement\Queries\ReviewEligibility;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Engagement\ReviewSummary;
use App\Domain\Engagement\Wishlist;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Filament\Resources\Reviews\CourseReviewResource;
use App\Filament\Resources\Reviews\Pages\ListCourseReviews;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function wrCreatePublishedCourse(string $suffix = ''): Course
{
    return Course::query()->create([
        'title' => ['en' => 'Pastel Portraits'.$suffix, 'ar' => 'رسم البورتريه'.$suffix],
        'slug_ar' => 'wr-ar-'.uniqid(),
        'slug_en' => 'wr-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => Course::ACCESS_FREE,
    ]);
}

it('manages learner wishlists across published, draft, child-context, and guest boundaries', function (): void {
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $otherLearner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    $published = wrCreatePublishedCourse(' One');
    $otherPublished = wrCreatePublishedCourse(' Two');
    $draft = Course::query()->create([
        'title' => ['en' => 'Hidden Draft', 'ar' => 'مسودة مخفية'],
        'slug_ar' => 'wr-draft-ar-'.uniqid(),
        'slug_en' => 'wr-draft-en-'.uniqid(),
        'status' => 'draft',
        'access_mode' => Course::ACCESS_FREE,
    ]);

    // Guest redirected to login
    $this->get(route('wishlist.index'))->assertRedirect(route('login.page'));
    $this->post(route('wishlist.toggle', ['course' => $published->id]))->assertRedirect(route('login.page'));

    // JSON toggle saves then removes; draft add refused; removing unpublished works
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $published->id]))
        ->assertOk()->assertJson(['saved' => true]);
    expect(Wishlist::query()->where('user_id', $learner->id)->count())->toBe(1);

    expect(fn () => app(ToggleWishlist::class)($learner, $draft))->toThrow(ValidationException::class);

    $this->actingAs($otherLearner)->postJson(route('wishlist.toggle', ['course' => $otherPublished->id]))
        ->assertOk()->assertJson(['saved' => true]);

    $this->actingAs($learner)->get(route('wishlist.index'))
        ->assertOk()
        ->assertSee('Pastel Portraits One')
        ->assertDontSee('Pastel Portraits Two');

    // Toggle off an unpublished saved course still removes cleanly
    $published->update(['status' => 'draft']);
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $published->id]))
        ->assertOk()->assertJson(['saved' => false]);
    expect(Wishlist::query()->where('user_id', $learner->id)->count())->toBe(0);

    // Parent acting in child context modifies child's wishlist, not parent's
    $published->update(['status' => 'published']);
    $this->actingAs($parent)
        ->withSession([ActingLearner::SESSION_KEY => $child->id])
        ->postJson(route('wishlist.toggle', ['course' => $published->id]))
        ->assertOk()
        ->assertJson(['saved' => true]);
    expect(Wishlist::query()->where('user_id', $child->id)->count())->toBe(1)
        ->and(Wishlist::query()->where('user_id', $parent->id)->count())->toBe(0);
})->group('T-P9-02', 'YG-GAP-ENG-001');

it('restricts course reviews to entitled learners, enforces one row per pair, and anonymizes child/adult identities', function (): void {
    $course = wrCreatePublishedCourse();
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $unentitled = User::factory()->create(['status' => 'active'])->assignRole('student');
    $revokedLearner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $adult = User::factory()->create(['status' => 'active', 'name' => 'Mona Kamel', 'display_name' => 'Mona Kamel', 'birth_year' => 1990])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active', 'name' => 'Omar Adel', 'display_name' => 'SuperKid99', 'email' => null, 'email_verified_at' => null, 'birth_year' => now()->year - 11])->assignRole('student');

    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);

    app(ToggleWishlist::class)($unentitled, $course);
    $grant = app(GrantEntitlement::class)($revokedLearner, $course, EntitlementSource::ManualGrant, '', null, null, $admin, 'Initial grant');
    app(RevokeEntitlement::class)($admin, $grant, 'Revoked for test.');
    app(GrantEntitlement::class)($adult, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);

    // Guest redirected; non-entitled, wishlist-only, and revoked receive 403 and no row
    $this->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Trying to bypass access check.'])
        ->assertRedirect(route('login.page'));
    $this->actingAs($unentitled)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Saved to wishlist only.'])
        ->assertForbidden();
    $this->actingAs($revokedLearner)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Revoked entitlement cannot review.'])
        ->assertForbidden();

    // Parent acting in child context gets 403
    $this->actingAs($parent)
        ->withSession([ActingLearner::SESSION_KEY => $child->id])
        ->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => 'Parent in child context review.'])
        ->assertForbidden();
    expect(CourseReview::query()->count())->toBe(0);

    // Entitled adult creates pending row and editing updates the same row (no duplicates)
    $this->actingAs($adult)->post(route('reviews.store', ['course' => $course->id]), [
        'rating' => 5,
        'body' => 'Detailed lesson demonstrations and clean feedback.',
    ])->assertRedirect(route('catalog.show', ['course' => PublicCourseDetail::key($course)]).'#reviews');

    $this->actingAs($adult)->post(route('reviews.store', ['course' => $course->id]), [
        'rating' => 4,
        'body' => 'Edited review body still meets validation minimum.',
    ])->assertRedirect();

    $adultReview = CourseReview::query()->where('learner_id', $adult->id)->sole();
    expect($adultReview->rating)->toBe(4)
        ->and($adultReview->status)->toBe(ReviewStatus::Pending)
        ->and($adultReview->author_name)->toBe('Mona K.');

    // Validation bounds
    $this->actingAs($adult)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 6, 'body' => 'short'])
        ->assertSessionHasErrors(['rating', 'body']);

    // Child account allowed by default, uses account name (never display_name), refused when config turned off
    app(SubmitCourseReview::class)($child, $course, 5, 'Fun colors and clear drawing steps.');
    $childReview = CourseReview::query()->where('learner_id', $child->id)->sole();
    expect($childReview->status)->toBe(ReviewStatus::Pending)
        ->and($childReview->author_name)->toBe('Omar A.')
        ->and($childReview->author_name)->not->toContain('SuperKid99');

    Config::set('engagement.reviews.children_may_review', false);
    expect(app(ReviewEligibility::class)->for($child, $course)['state'])->toBe(ReviewEligibility::BLOCKED);
    $this->actingAs($child)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 4, 'body' => 'Second child review when blocked.'])
        ->assertForbidden();
})->group('T-P9-02', 'A1', 'YG-GAP-ENG-002');

it('keeps non-published reviews hidden, updates aggregates/JSON-LD on moderation, and escapes/protects PII', function (): void {
    Notification::fake();

    $course = wrCreatePublishedCourse();
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner1 = User::factory()->create(['status' => 'active', 'name' => 'Nadia Hassan', 'email' => 'nadia.secret@example.test', 'birth_year' => 1990])->assignRole('student');
    $learner2 = User::factory()->create(['status' => 'active', 'name' => 'Tarek Zaki', 'email' => 'tarek.secret@example.test', 'birth_year' => 1991])->assignRole('student');

    app(GrantEntitlement::class)($learner1, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($learner2, $course, EntitlementSource::Free);

    $r1 = app(SubmitCourseReview::class)($learner1, $course, 5, "Great shading <script>alert('xss')</script> lessons!");
    $r2 = app(SubmitCourseReview::class)($learner2, $course, 4, 'Clear pacing across every exercise.');

    // A2: Pending reviews hidden from public course page, summary, and JSON-LD
    $courseUrl = route('catalog.show', ['course' => PublicCourseDetail::key($course)]);
    $pendingHtml = $this->get($courseUrl)->assertOk()->getContent();
    expect($pendingHtml)->toContain('data-reviews-slot')
        ->not->toContain('data-review>')
        ->not->toContain('aggregateRating')
        ->and(ReviewSummary::for($course))->toBe(['average' => 0.0, 'count' => 0]);

    // Queue permission gating
    $this->actingAs($finance);
    expect(CourseReviewResource::canViewAny())->toBeFalse();
    $this->actingAs($instructor);
    expect(CourseReviewResource::canViewAny())->toBeFalse();
    $this->actingAs($admin);
    expect(CourseReviewResource::canViewAny())->toBeTrue();

    // Approve both via Action + Filament table, check rating cache + JSON-LD + XSS escaping + PII protection
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin);
    Livewire::test(ListCourseReviews::class)->callTableAction('approve', $r1);
    app(ApproveCourseReview::class)($admin, $r1->fresh()); // repeat no-op
    app(ApproveCourseReview::class)($admin, $r2->fresh());

    expect(Activity::query()->where('event', 'review.published')->count())->toBe(2)
        ->and(ReviewSummary::for($course))->toBe(['average' => 4.5, 'count' => 2]);
    Notification::assertSentToTimes($learner1, ReviewDecisionNotice::class, 1);

    $publishedHtml = $this->get($courseUrl)->assertOk()->getContent();
    expect($publishedHtml)->toContain('data-reviews-summary')
        ->toContain('data-review')
        ->toContain('Nadia H.')
        ->toContain('Tarek Z.')
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')")
        ->not->toContain('Nadia Hassan')
        ->not->toContain('nadia.secret@example.test')
        ->toContain('"aggregateRating"')
        ->toContain('"ratingValue":4.5')
        ->toContain('"reviewCount":2');

    // Editing published review returns it to pending and drops rating cache back to count 1
    app(SubmitCourseReview::class)($learner2, $course, 3, 'Updated feedback that needs re-moderation.');
    expect($r2->fresh()->status)->toBe(ReviewStatus::Pending)
        ->and(ReviewSummary::for($course))->toBe(['average' => 5.0, 'count' => 1]);

    // Reject requires 10+ char reason and notifies learner with reason; rejected can re-submit
    expect(fn () => app(RejectCourseReview::class)($admin, $r2->fresh(), 'Short'))->toThrow(ValidationException::class);
    app(RejectCourseReview::class)($admin, $r2->fresh(), 'Contains off-topic unrelated commentary.');
    Notification::assertSentTo($learner2, ReviewDecisionNotice::class, function (ReviewDecisionNotice $notice) use ($learner2): bool {
        $mail = $notice->toArray($learner2);

        return str_contains((string) ($mail['message'] ?? ''), 'Contains off-topic unrelated commentary.');
    });
    app(SubmitCourseReview::class)($learner2, $course, 4, 'Resubmitted focused course feedback.');
    expect($r2->fresh()->status)->toBe(ReviewStatus::Pending);

    // Hide published review: reason hidden from learner notification, learner cannot re-edit
    expect(fn () => app(HideCourseReview::class)($admin, $r1->fresh(), 'Too short'))->toThrow(ValidationException::class);
    app(HideCourseReview::class)($admin, $r1->fresh(), 'Confidential moderation policy violation.');
    expect($r1->fresh()->status)->toBe(ReviewStatus::Hidden)
        ->and(ReviewSummary::for($course))->toBe(['average' => 0.0, 'count' => 0])
        ->and(app(ReviewEligibility::class)->for($learner1, $course)['state'])->toBe(ReviewEligibility::BLOCKED)
        ->and(fn () => app(SubmitCourseReview::class)($learner1, $course, 5, 'Trying to re-edit hidden review.'))->toThrow(ValidationException::class);
    Notification::assertSentTo($learner1, ReviewDecisionNotice::class, function (ReviewDecisionNotice $notice) use ($learner1): bool {
        $mail = $notice->toArray($learner1);

        return str_contains((string) ($mail['title'] ?? ''), __('engagement.notice.hidden.title'))
            && ! str_contains((string) ($mail['message'] ?? ''), 'Confidential moderation policy violation.');
    });
    expect(fn () => app(ApproveCourseReview::class)($finance, $r1->fresh()))->toThrow(AuthorizationException::class);
})->group('T-P9-02', 'A2', 'YG-GAP-ENG-002');
