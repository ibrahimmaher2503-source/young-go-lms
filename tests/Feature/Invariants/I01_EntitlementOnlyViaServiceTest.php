<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * I01 / T-P4-01 A3: protected access is decided by the Entitlements module alone. Outside the module, only the
 * two staff grant UI classes may name the model for Filament's native table, action records and CSV rows.
 */
it('lets only the Entitlements module touch the entitlements table', function (): void {
    $offenders = [];

    foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $file) {
        $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());
        if (str_starts_with($relative, 'app/Domain/Entitlements/')) {
            continue;
        }

        if (i01DirectEntitlementAccess($relative, $file->getContents())) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
})->group('T-P4-01', 'A3', 'I01');

it('catches direct model and table queries even in the grant UI', function (): void {
    $resource = 'app/Filament/Resources/ManualGrants/ManualGrantResource.php';
    $import = 'use App\Domain\Entitlements\Entitlement;';

    expect(i01DirectEntitlementAccess($resource, $import.' Entitlement::query();'))->toBeTrue()
        ->and(i01DirectEntitlementAccess($resource, "DB::table('entitlements')->where(1)"))->toBeTrue()
        ->and(i01DirectEntitlementAccess('app/Filament/Resources/ManualGrants/Other.php', $import))->toBeTrue()
        ->and(i01DirectEntitlementAccess($resource, $import.' Entitlement::ACTIVE;'))->toBeFalse();
})->group('T-P4-01', 'A3', 'I01');

function i01DirectEntitlementAccess(string $path, string $code): bool
{
    if (in_array($path, [
        'app/Filament/Resources/ManualGrants/ManualGrantResource.php',
        'app/Filament/Resources/ManualGrants/Pages/ListManualGrants.php',
    ], true)) {
        $code = str_replace('use App\Domain\Entitlements\Entitlement;', '', $code);
    }

    return preg_match('~Entitlements\\\\Entitlement\b|[\'"]entitlements[\'"]|\bentitlements\s*\(|\bEntitlement\s*::\s*\w+\s*\(|\bnew\s+Entitlement\b~i', $code) === 1;
}
