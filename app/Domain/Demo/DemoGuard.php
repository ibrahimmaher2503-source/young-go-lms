<?php

declare(strict_types=1);

namespace App\Domain\Demo;

use Illuminate\Support\Facades\Config;

/** The one gate for every demo command. No flag, option or env value can switch production on. */
final class DemoGuard
{
    public static function assertAllowed(): void
    {
        if (app()->environment('production')) {
            throw new DemoRefused(__('demo.refused_production'));
        }

        $allowed = array_values(array_filter(Config::array('demo.environments'), static fn (mixed $env): bool => is_string($env) && $env !== 'production'));
        if (! app()->environment($allowed)) {
            throw new DemoRefused(__('demo.refused_environment', ['environments' => implode(', ', $allowed)]));
        }
    }
}
