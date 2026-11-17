<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\GuardianAccess;
use App\Domain\Settings\PlatformFlag;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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

            if ($user->hasRole('super_admin', (string) config('permissions.guard', 'web'))) {
                return true;
            }

            $permissionNames = array_column(config('permissions.permissions', []), 'name');
            if (! in_array($ability, $permissionNames, true)) {
                return null;
            }

            return $user->checkPermissionTo($ability, (string) config('permissions.guard', 'web')) ? true : null;
        });

        Gate::define('guardian.child.access', [GuardianAccess::class, 'parentCanAccessChild']);

        RateLimiter::for('login', static function (Request $request): array {
            $login = $request->input('login');
            $identity = is_string($login) ? mb_strtolower(trim($login)) : '';

            return [
                Limit::perMinute(Config::integer('identity.login_max_attempts'))->by(hash('sha256', $identity).'|'.$request->ip()),
                Limit::perMinute(30)->by('address:'.$request->ip()),
            ];
        });
        RateLimiter::for('two-factor', static fn (Request $request): array => [Limit::perMinute(5)->by('user:'.($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip())]);
        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        foreach (PlatformFlag::cases() as $flag) {
            Feature::define($flag->value, false);
        }
    }
}
