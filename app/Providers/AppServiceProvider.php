<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AI\Assistant\AssistantEngine;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Contracts\ModerationClassifier;
use App\Domain\AI\Ingestion\Contracts\PageRenderer;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Ingestion\PdftoppmRenderer;
use App\Domain\AI\Ingestion\PdftotextExtractor;
use App\Domain\AI\Providers\FakeModerationClassifier;
use App\Domain\AI\Providers\KeywordModerationClassifier;
use App\Domain\AI\Resilience\CircuitBreaker;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\AI\Resilience\ResilientProvider;
use App\Domain\Assessment\AssessmentHistory;
use App\Domain\Catalog\Events\CourseReviewed;
use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Domain\Catalog\Revisions\Events\CourseRevisionSubmitted;
use App\Domain\Certificates\Listeners\IssueCertificateOnCompletion;
use App\Domain\Cohorts\CohortHistory;
use App\Domain\Commerce\Contracts\EReceiptProvider;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\Events\ManualPaymentRejected;
use App\Domain\Commerce\Events\PaymentAttemptDeclined;
use App\Domain\Commerce\Events\PaymentCheckoutStarted;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Events\RefundExecuted;
use App\Domain\Commerce\Payments\PaymentGatewayResolver;
use App\Domain\Commerce\Receipts\EReceiptProviderResolver;
use App\Domain\Completion\Events\CourseCompleted;
use App\Domain\Completion\Events\CourseCompletionInputChanged;
use App\Domain\Completion\Listeners\EvaluateCompletion;
use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementHistory;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\Actions\AuthorizeSuperAdminBypass;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\SessionLimiter;
use App\Domain\InstructorFinance\Listeners\AccrueInstructorEarnings;
use App\Domain\Live\ManualMeetingProvider;
use App\Domain\Live\MeetingProvider;
use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Support\LegacyIdMap;
use App\Domain\Migration\Support\ReadOnlyLegacyConnection;
use App\Domain\Notifications\Listeners\NotifyInstructorsOfCourseReview;
use App\Domain\Notifications\Listeners\NotifyOfCourseRevision;
use App\Domain\Notifications\Listeners\NotifyPayerOfPayment;
use App\Domain\Progress\LessonCompletionLookup;
use App\Domain\Progress\ProgressHistory;
use App\Domain\Settings\Listeners\ApplyMailSenderIdentity;
use App\Domain\Settings\PlatformFlag;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    private static function assistantLimit(Request $request, int $perMinute): Limit
    {
        return Limit::perMinute($perMinute)->by('assistant:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(static fn (Request $request, array $headers): JsonResponse => response()->json([
                'state' => 'rate_limited', 'message' => __('ai.quota.rate_limited'), 'retry_after' => (int) ($headers['Retry-After'] ?? 60),
            ], 429, $headers + ['Cache-Control' => 'private, no-store']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(static function (User $user, string $ability, array $arguments): ?bool {
            if ($user->getAttribute('status') !== 'active') {
                return false;
            }

            // Acting as a child always requires a verified relationship, including for staff.
            if ($ability === 'guardian.child.access') {
                return null;
            }

            if ($ability === 'viewTelescope') {
                return null;
            }

            $guard = (string) config('permissions.guard', 'web');
            $permissionNames = array_column(config('permissions.permissions', []), 'name');
            if (in_array($ability, $permissionNames, true) && $user->checkPermissionTo($ability, $guard)) {
                return true;
            }

            if ($user->hasRole('super_admin', $guard)) {
                $target = $arguments[0] ?? null;

                return app(AuthorizeSuperAdminBypass::class)($user, $ability, $target instanceof Model ? $target : null);
            }

            return null;
        });

        Relation::morphMap(['lesson' => Lesson::class, 'quiz' => Quiz::class, 'assignment' => Assignment::class, 'live_session' => LiveSession::class]);
        $this->app->bind(MeetingProvider::class, ManualMeetingProvider::class);
        // T-P8-02: Paymob when credentials are complete, else Fake (local/testing) or the refusing Null gateway (B-07). Singleton so the Fake keeps its recorded calls.
        $this->app->singleton(PaymentGateway::class, static fn (): PaymentGateway => (new PaymentGatewayResolver)());
        // T-P8-06: e-receipt submission is disabled (null driver) until owner decision O-023 closes.
        $this->app->singleton(EReceiptProvider::class, static fn (): EReceiptProvider => (new EReceiptProviderResolver)());
        // One authority for learner access (T-P4-01); it fails closed on anything it does not know.
        Event::listen(Logout::class, static function (Logout $event): void {
            if (app()->bound('session.store') && session()->isStarted()) {
                app(SessionLimiter::class)->end(session()->getId());
            }
        });
        // T-P6-05: anything that can change a learner's completion asks the evaluator (idempotent, synchronous).
        Event::listen(MessageSending::class, [ApplyMailSenderIdentity::class, 'handle']);
        Event::listen(CourseCompletionInputChanged::class, [EvaluateCompletion::class, 'handle']);
        // T-P6-06: a completed course gets its certificate once (idempotent) and the learner a single notice.
        Event::listen(CourseCompleted::class, [IssueCertificateOnCompletion::class, 'handle']);
        // T-P9-05: payer notices for payments (never break the payment; see NotifyPayerOfPayment).
        Event::listen(PaymentVerified::class, [NotifyPayerOfPayment::class, 'verified']);
        Event::listen(ManualPaymentApproved::class, [NotifyPayerOfPayment::class, 'manualApproved']);
        Event::listen(ManualPaymentProofSubmitted::class, [NotifyPayerOfPayment::class, 'proofSubmitted']);
        Event::listen(PaymentCheckoutStarted::class, [NotifyPayerOfPayment::class, 'checkoutStarted']);
        Event::listen(PaymentAttemptDeclined::class, [NotifyPayerOfPayment::class, 'declined']);
        Event::listen(ManualPaymentRejected::class, [NotifyPayerOfPayment::class, 'proofRejected']);
        // T-P3-03: instructors hear the course review outcome (never undoes the decision).
        Event::listen(CourseReviewed::class, [NotifyInstructorsOfCourseReview::class, 'handle']);
        // T-P3-07: reviewers hear a submitted revision; the author and instructors hear its outcome.
        Event::listen(CourseRevisionSubmitted::class, [NotifyOfCourseRevision::class, 'submitted']);
        Event::listen(CourseRevisionReviewed::class, [NotifyOfCourseRevision::class, 'reviewed']);
        // T-P8-09: instructor ledger earnings on paid orders, reversals on executed refunds (idempotent; failures are reported, never thrown).
        Event::listen(PaymentVerified::class, [AccrueInstructorEarnings::class, 'verified']);
        Event::listen(ManualPaymentApproved::class, [AccrueInstructorEarnings::class, 'manualApproved']);
        Event::listen(RefundExecuted::class, [AccrueInstructorEarnings::class, 'refunded']);
        Event::listen(RefundExecuted::class, [NotifyPayerOfPayment::class, 'refunded']);
        $this->app->bind(CompletionLookup::class, LessonCompletionLookup::class);
        $this->app->bind(LegacySourceReader::class, static fn (): LegacySourceReader => new ReadOnlyLegacyConnection);
        $this->app->scoped(LegacyIdMap::class);
        $this->app->scoped(EntitlementService::class);
        $this->app->tag([EntitlementHistory::class, ProgressHistory::class, CohortHistory::class, AssessmentHistory::class], 'course.history');
        $this->app->bind(LessonAccessChecker::class, EntitlementService::class);
        $this->app->bind(PdfTextExtractor::class, PdftotextExtractor::class);
        $this->app->bind(PageRenderer::class, PdftoppmRenderer::class);
        // O-014: no real provider is approved. Local and test runs get the deterministic fake, everything else the refusing null.
        // T-P7-06: a super admin may pick another registered key (AiSettings); with no choice the environment decides, as before.
        $this->app->singleton(AiProviderInterface::class, static fn (): AiProviderInterface => app(ProviderRegistry::class)->make(app(ProviderRegistry::class)->activeKey()));
        // The request pipeline talks to the provider through the circuit breaker and the optional fallback route (AI spec 11).
        $this->app->when(AssistantEngine::class)->needs(AiProviderInterface::class)->give(static fn (): AiProviderInterface => new ResilientProvider(
            app(AiProviderInterface::class), app(ProviderRegistry::class), app(CircuitBreaker::class),
        ));

        // Moderation adapter: the keyword baseline everywhere except local/test, which get the deterministic fake. A real adapter comes with O-014.
        $this->app->singleton(ModerationClassifier::class, static fn (): ModerationClassifier => config('ai.provider') === 'fake' || app()->environment('local', 'testing')
            ? new FakeModerationClassifier
            : new KeywordModerationClassifier);

        Gate::define('guardian.child.access', [GuardianAccess::class, 'parentCanAccessChild']);

        // Assistant endpoints (T-P7-02): per signed-in account, answered as JSON so the player can show a rate-limit state.
        RateLimiter::for('assistant-ask', static fn (Request $request): Limit => self::assistantLimit($request, max(1, (int) config('ai.rate_limit.ask_per_minute', 8))));
        RateLimiter::for('assistant-status', static fn (Request $request): Limit => self::assistantLimit($request, max(1, (int) config('ai.rate_limit.status_per_minute', 60))));
        RateLimiter::for('login', static function (Request $request): array {
            $login = $request->input('login');
            $identity = is_string($login) ? mb_strtolower(trim($login)) : '';

            return [
                Limit::perMinute(Config::integer('identity.login_max_attempts'))->by(hash('sha256', $identity).'|'.$request->ip()),
                Limit::perMinute(30)->by('address:'.$request->ip()),
            ];
        });
        RateLimiter::for('two-factor', static function (Request $request): array {
            $user = 'user:'.($request->user()?->getAuthIdentifier() ?? 'guest');

            // Per user and address, plus a per-user cap that rotating addresses cannot sidestep.
            return [Limit::perMinute(5)->by($user.'|'.$request->ip()), Limit::perMinutes(15, 10)->by($user)];
        });
        RateLimiter::for('progress', static fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('learning.progress.heartbeats_per_minute')))->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->route('item')));
        RateLimiter::for('checkout-coupon', static fn (Request $request): Limit => Limit::perMinute(10)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->route()?->getName()));
        RateLimiter::for('certificate-verify', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('certificate-download', static fn (Request $request): Limit => Limit::perMinute(10)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->route('certificate')));
        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        // Public lead forms (T-P2-08): a short burst limit and an hourly cap per address, separate buckets per form.
        foreach (['contact', 'newsletter', 'instructor-application'] as $form) {
            RateLimiter::for($form, static fn (Request $request): array => [
                Limit::perMinute(max(1, Config::integer('contact.rate_limit.per_minute')))->by($form.'|m|'.$request->ip()),
                Limit::perHour(max(1, Config::integer('contact.rate_limit.per_hour')))->by($form.'|h|'.$request->ip()),
            ]);
        }
        RateLimiter::for('newsletter-unsubscribe', static fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        foreach (PlatformFlag::cases() as $flag) {
            Feature::define($flag->value, false);
        }
    }
}
