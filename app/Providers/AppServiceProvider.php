<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Ingestion\Contracts\PageRenderer;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Ingestion\PdftoppmRenderer;
use App\Domain\AI\Ingestion\PdftotextExtractor;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Cohorts\CohortHistory;
use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementHistory;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\SessionLimiter;
use App\Domain\Live\ManualMeetingProvider;
use App\Domain\Live\MeetingProvider;
use App\Domain\Progress\LessonCompletionLookup;
use App\Domain\Progress\ProgressHistory;
use App\Domain\Settings\PlatformFlag;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(static function (User $user, string $ability): ?bool {
            if ($user->getAttribute('status') !== 'active') {
                return false;
            }

            // Acting as a child always requires a verified relationship, including for staff.
            if ($ability === 'guardian.child.access') {
                return null;
            }

            if ($user->hasRole('super_admin', (string) config('permissions.guard', 'web'))) {
                return true;
            }

            $permissionNames = array_column(config('permissions.permissions', []), 'name');
            if (! in_array($ability, $permissionNames, true)) {
                return null;
            }

            return $user->checkPermissionTo($ability, (string) config('permissions.guard', 'web')) ? true : null;
        });

        Relation::morphMap(['lesson' => Lesson::class, 'quiz' => Quiz::class, 'assignment' => Assignment::class, 'live_session' => LiveSession::class]);
        $this->app->bind(MeetingProvider::class, ManualMeetingProvider::class);
        // One authority for learner access (T-P4-01); it fails closed on anything it does not know.
        Event::listen(Logout::class, static function (Logout $event): void {
            if (app()->bound('session.store') && session()->isStarted()) {
                app(SessionLimiter::class)->end(session()->getId());
            }
        });
        $this->app->bind(CompletionLookup::class, LessonCompletionLookup::class);
        $this->app->scoped(EntitlementService::class);
        $this->app->tag([EntitlementHistory::class, ProgressHistory::class, CohortHistory::class], 'course.history');
        $this->app->bind(LessonAccessChecker::class, EntitlementService::class);
        $this->app->bind(PdfTextExtractor::class, PdftotextExtractor::class);
        $this->app->bind(PageRenderer::class, PdftoppmRenderer::class);
        // O-014: no real provider is approved. Local and test runs get the deterministic fake, everything else the refusing null.
        $this->app->singleton(AiProviderInterface::class, static fn (): AiProviderInterface => config('ai.provider') === 'fake' || app()->environment('local', 'testing')
            ? new FakeAiProvider
            : new NullAiProvider);

        Gate::define('guardian.child.access', [GuardianAccess::class, 'parentCanAccessChild']);

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
        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        foreach (PlatformFlag::cases() as $flag) {
            Feature::define($flag->value, false);
        }
    }
}
