<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * I01 / T-P4-01 A3: protected access is decided by the Entitlements module alone. No other code may query the
 * entitlements table or use the Entitlement model; it must ask EntitlementService.
 */
it('lets only the Entitlements module touch the entitlements table', function (): void {
    $allowed = [
        str_replace('/', DIRECTORY_SEPARATOR, 'app/Domain/Entitlements/'),
        str_replace('/', DIRECTORY_SEPARATOR, 'app/Console/Commands/ExpireEntitlementsCommand.php'),
        str_replace('/', DIRECTORY_SEPARATOR, 'app/Filament/Resources/ManualGrants/'),
    ];
    $offenders = [];

    foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $file) {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        if (collect($allowed)->contains(fn (string $prefix): bool => str_starts_with($relative, $prefix))) {
            continue;
        }

        $code = $file->getContents();
        if (preg_match('/Entitlements\\\\Entitlement\b|[\'"]entitlements[\'"]|\bentitlements\(\)/', $code)) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
})->group('T-P4-01', 'A3', 'I01');

it('catches a direct query if one is added', function (): void {
    $sample = 'DB::table(\'entitlements\')->where(1)';

    expect(preg_match('/Entitlements\\\\Entitlement\b|[\'"]entitlements[\'"]|\bentitlements\(\)/', $sample))->toBe(1);
})->group('T-P4-01', 'A3', 'I01');
