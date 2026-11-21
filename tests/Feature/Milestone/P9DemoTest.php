<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\SaveQuizAnswers;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assessment\Notifications\QuizResultNotice;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\Queries\PublishedReviews;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Instructors\Actions\AcceptInstructorAgreement;
use App\Domain\Instructors\Actions\ApproveInstructorApplication;
use App\Domain\Instructors\Actions\PublishInstructorAgreement;
use App\Domain\Instructors\Actions\SaveInstructorAgreement;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Privacy\Actions\ApproveDataRequest;
use App\Domain\Privacy\Actions\CompleteDataRequest;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Reports\Dashboards\DashboardKpi;
use App\Domain\Reports\Dashboards\RoleDashboard;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Settings\Actions\SaveMarketingTags;
use App\Domain\Shared\Markets\Market;
use App\Domain\Support\Actions\StartViewAs;
use App\Domain\Support\Queries\Learner360Record;
use App\Domain\Support\ViewAsState;
use App\Filament\Pages\LearnerThreeSixtyPage;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config([
        'identity.terms_version' => 'demo-terms',
        'identity.privacy_version' => 'demo-privacy',
        // A unique disk root per run, under storage/framework/testing/disks, removed in afterEach.
        'media.disk' => 'p9demo-'.uniqid(),
    ]);
    config(['filesystems.disks.'.config('media.disk') => [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/'.config('media.disk')),
        'throw' => false,
    ]]);
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/disks/'.config('media.disk')));
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p9As($test, ?User $user = null)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $user === null ? $test : $test->actingAs($user);
}

/** A paid order inside the 30-day window, so reports and dashboards see it. */
function p9Order(User $payer, string $reference, int $amount, ?string $paidAt = null): int
{
    return DB::table('orders')->insertGetId([
        'reference' => $reference, 'payer_id' => $payer->id, 'market' => 'EG', 'currency' => 'EGP',
        'subtotal_minor' => $amount, 'total_minor' => $amount, 'status' => 'paid',
        'paid_at' => $paidAt ?? now()->subDays(3)->toDateTimeString(),
        'idempotency_key' => $reference, 'request_hash' => hash('sha256', $reference),
    ]);
}

/** Published free course with one text lesson. */
function p9Course(User $instructor, string $title): array
{
    $course = Course::query()->create([
        'title' => ['en' => $title, 'ar' => 'دورة تجريبية'], 'slug_ar' => 'p9-ar-'.uniqid(), 'slug_en' => 'p9-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading', 'ar' => 'قراءة'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id], 'published');

    return [$course, $lesson];
}

/**
 * T-P9-MG demo script, executed over HTTP and through the real actions:
 * instructor applies and accepts the agreement → learner wishlists and reviews (hidden until moderated) → marketing tags off before
 * consent and absent from the player → quiz result notice → finance CSV export with one audit row → admin KPIs equal the report →
 * learner export and deletion (anonymized, orders kept) → support Learner 360 and a read-only view-as → settings never show secrets.
 */
it('runs the P9 demo script end to end', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $academy = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    // 1. Instructor onboarding: the applicant (Laila) applies over HTTP with a placeholder ID, the admin approves,
    //    publishes the agreement and the applicant accepts it.
    $applicant = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), 'name' => 'Laila Demo'])->assignRole('student');
    p9As($this, $applicant)->post(route('instructors.apply'), [
        'full_name' => 'Laila Demo', 'phone' => '+201000000000', 'discipline' => 'Watercolor', 'experience_years' => 4,
        'bio' => 'Teaching placeholder watercolor lessons for young learners in the demo.',
        'portfolio_links' => 'https://portfolio.example.test/laila', 'privacy' => '1',
        'id_document' => UploadedFile::fake()->image('id.png', 20, 20),
    ])->assertRedirect(route('instructors.teach'));
    $application = DB::table('instructor_applications')->where('user_id', $applicant->id)->firstOrFail();
    expect($application->status)->toBe('pending');

    $applicationModel = InstructorApplication::query()->findOrFail($application->id);
    app(ApproveInstructorApplication::class)($admin, $applicationModel);
    $agreement = app(SaveInstructorAgreement::class)($admin, null, [
        'version' => 'p9-demo-2026',
        'title' => ['ar' => 'اتفاقية المدرب', 'en' => 'Instructor agreement (demo)'],
        'body' => ['ar' => 'نص تجريبي', 'en' => 'Demo placeholder wording.'],
    ]);
    app(PublishInstructorAgreement::class)($admin, $agreement);
    app(AcceptInstructorAgreement::class)($applicant->refresh(), $agreement->fresh(), '127.0.0.1');
    expect($applicant->refresh()->hasRole('instructor_independent'))->toBeTrue();

    // Published course by the academy instructor, reused by the learner steps below.
    [$course, $lesson] = p9Course($academy, 'P9 Demo Course');

    // 2. Learner wishlists the course (JSON heart) and sees it on the wishlist page.
    $learner = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), 'name' => 'Omar Demo'])->assignRole('student');
    p9As($this, $learner)->postJson(route('wishlist.toggle', ['course' => $course->id]))->assertOk()->assertJson(['saved' => true]);
    p9As($this, $learner)->get(route('wishlist.index'))->assertOk()->assertSee('P9 Demo Course');

    // 3. Review: entitled learner posts one; it stays pending and hidden until moderated.
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $reviewBody = 'Clear steps and short lessons, very useful for beginners.';
    p9As($this, $learner)->post(route('reviews.store', ['course' => $course->id]), ['rating' => 5, 'body' => $reviewBody])
        ->assertRedirect();
    $review = CourseReview::query()->where('learner_id', $learner->id)->firstOrFail();
    expect($review->status->value)->toBe('pending');
    expect(app(PublishedReviews::class)->forCourse($course)->count())->toBe(0);
    // The public page (a guest) must not show the pending review; the author sees only their own copy.
    p9As($this, null)->get(route('catalog.show', ['course' => PublicCourseDetail::key($course)]))
        ->assertOk()->assertDontSee($reviewBody);

    app(ApproveCourseReview::class)($admin, $review);
    expect(app(PublishedReviews::class)->forCourse($course)->count())->toBe(1);
    p9As($this, $learner)->get(route('catalog.show', ['course' => PublicCourseDetail::key($course)]))
        ->assertOk()->assertSee($reviewBody);

    // 4. Marketing tags: none before consent; decline keeps them off; accept prints them on the public catalog only, never on the player.
    app(SaveMarketingTags::class)($marketing, ['ga4_measurement_id' => 'G-P9DEMO1234', 'meta_pixel_id' => '1234567890']);
    p9As($this, null)->get('/courses')->assertOk()->assertDontSee('googletagmanager.com', false);
    $accept = $this->post(route('consent.store'), ['decision' => 'accept']);
    $acceptedCookie = null;
    foreach ($accept->headers->getCookies() as $cookie) {
        if ($cookie->getName() === config('consent.cookie')) {
            $acceptedCookie = (string) $cookie->getValue();
        }
    }
    expect($acceptedCookie)->not->toBeNull();
    $this->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)->get('/courses')->assertOk()
        ->assertSee('googletagmanager.com', false);
    p9As($this, $learner)->withUnencryptedCookie(config('consent.cookie'), $acceptedCookie)
        ->get(route('learn.show', ['course' => $course->id, 'item' => $lesson->id]))
        ->assertDontSee('googletagmanager.com', false)->assertDontSee('connect.facebook.net', false);

    // 5. Quiz result notice: an auto-graded attempt reaches the learner as a QuizResultNotice.
    $quizInstructor = $academy;
    $quizCourse = Course::query()->create([
        'title' => ['en' => 'P9 Quiz Course', 'ar' => 'اختبار'], 'slug_ar' => 'p9q-ar-'.uniqid(), 'slug_en' => 'p9q-en-'.uniqid(),
        'status' => 'published', 'sequence_mode' => Course::SEQUENCE_SEQUENTIAL,
    ]);
    $quizCourse->instructors()->attach($quizInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $quizSection = app(ManageSections::class)->add($quizInstructor, $quizCourse, ['title' => ['en' => 'Section']]);
    $quizSection->update(['published' => true]);
    $quizItem = app(ManageAssessmentItems::class)->add($quizInstructor, $quizSection, 'quiz', ['title' => ['en' => 'Module Quiz']]);
    $quiz = $quizItem->itemable;
    app(ConfigureQuiz::class)($quizInstructor, $quiz, [
        'time_limit_minutes' => 10, 'pass_mark_percent' => 60, 'max_attempts' => 5, 'result_visibility' => 'full',
        'randomize' => false, 'progression' => 'after_submission',
    ]);
    $single = app(ManageQuestions::class)->add($quizInstructor, $quiz, [
        'type' => Question::SINGLE, 'prompt' => ['en' => 'Pick the right one'], 'points' => 2,
        'options' => [['text' => ['en' => 'Right'], 'is_correct' => true], ['text' => ['en' => 'Wrong'], 'is_correct' => false]],
    ]);
    app(SetCurriculumStatus::class)($quizInstructor, $quizCourse, [$quizItem->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($learner, $quizCourse, EntitlementSource::Free);

    $attempt = app(StartQuizAttempt::class)($learner, $quizItem->fresh());
    $key = collect($attempt->snapshot['questions'])->firstWhere('id', $single->id)['options'];
    $correct = collect($key)->firstWhere('is_correct', true)['key'];
    app(SaveQuizAnswers::class)($learner, $attempt, [$single->id => ['selected' => [$correct]]]);

    Notification::fake();
    app(SubmitQuizAttempt::class)($learner, $attempt->fresh());
    Notification::assertSentTo($learner, QuizResultNotice::class);

    // 6. Finance exports the sales report as CSV over HTTP: one audit row, a UTF-8 BOM and the paid order in the file.
    p9Order($learner, 'P9-SALE-01', 10000);
    $to = now(Market::Egypt->timezone());
    $window = ['from' => $to->copy()->subDays(29)->toDateString(), 'to' => $to->toDateString()];
    $export = p9As($this, $finance)->get(route('reports.export', ['report' => ReportKey::Sales->value] + $window))
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $csv = $export->streamedContent();
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and(substr_count(trim($csv), "\n"))->toBeGreaterThanOrEqual(1);
    expect(Activity::query()->where('event', 'report.exported')->count())->toBe(1);

    // 7. Admin dashboard KPIs equal the report query for the same window.
    $catalog = app(ReportCatalog::class);
    $windowFilters = ReportFilters::fromInput($window);
    $salesTotals = $catalog->definition(ReportKey::Sales)->totals($catalog->scope($admin, ReportKey::Sales, $windowFilters));
    $net = collect($salesTotals)->firstWhere('key', 'net');
    $kpis = collect(app(RoleDashboard::class)->for($admin, 'admin')->kpis)->keyBy(fn (DashboardKpi $kpi) => $kpi->key);
    expect($kpis['revenue']->money)->toBe(['EGP' => (int) round($net['value'] * 100)])
        ->and($kpis['revenue']->money['EGP'])->toBe(10000);

    // 8. Support opens Learner 360 for the learner, starts a read-only view-as, and writes are refused until Stop.
    p9As($this, $admin)->get(LearnerThreeSixtyPage::getUrl(['learner' => $learner->id], panel: 'admin'))->assertOk();
    $record = app(Learner360Record::class)->for($admin, $learner);
    expect($record)->toBeArray();

    p9As($this, $support);
    app(StartViewAs::class)($support, $learner, 'P9 demo: check the learner course list.');
    $this->get('/support/view-as')->assertOk();
    $this->postJson(route('wishlist.toggle', ['course' => $course->id]))->assertForbidden();
    $this->post('/support/view-as/stop')->assertRedirect();
    expect(app(ViewAsState::class)->current())->toBeNull();

    // 9. Privacy: the learner requests an export; admin approves and completes it; the learner downloads their own zip.
    p9As($this, $learner)->post(route('profile.privacy.store'), ['type' => 'export'])->assertRedirect(route('profile.privacy.show'));
    $exportRequest = DataRequest::query()->where('requested_by', $learner->id)->where('type', 'export')->firstOrFail();
    app(ApproveDataRequest::class)($admin, $exportRequest);
    app(CompleteDataRequest::class)($admin, $exportRequest->fresh());
    expect($exportRequest->fresh()->status)->toBe(DataRequestStatus::Completed);
    p9As($this, $learner)->get(route('profile.privacy.download', ['dataRequest' => $exportRequest->id]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    // 10. Deletion: a second learner with a paid order asks for deletion; the account is anonymized in place and the order stays.
    $deleter = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), 'name' => 'Nour Demo'])->assignRole('student');
    $orderId = p9Order($deleter, 'P9-DEL-ORDER', 5000);
    p9As($this, $deleter)->post(route('profile.privacy.store'), ['type' => 'deletion'])->assertRedirect(route('profile.privacy.show'));
    $deletion = DataRequest::query()->where('requested_by', $deleter->id)->where('type', 'deletion')->firstOrFail();
    app(ApproveDataRequest::class)($admin, $deletion);
    app(CompleteDataRequest::class)($admin, $deletion->fresh());

    $anonymized = DB::table('users')->where('id', $deleter->id)->first();
    expect($anonymized->status)->toBe('anonymized')
        ->and($anonymized->name)->not->toBe('Nour Demo')
        ->and(DB::table('orders')->where('id', $orderId)->value('status'))->toBe('paid');

    // 11. Settings: the settings hub and the general settings never display the application key.
    $key = (string) config('app.key');
    foreach (['/admin/settings', '/admin/general-settings', '/admin/payment-payee'] as $url) {
        $html = p9As($this, $superAdmin)->get($url)->assertOk()->getContent();
        expect($key)->not->toBe('')->and(str_contains($html, $key))->toBeFalse();
    }
})->group('T-P9-MG', 'A1');
