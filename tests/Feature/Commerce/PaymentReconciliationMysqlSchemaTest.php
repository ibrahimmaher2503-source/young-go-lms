<?php

declare(strict_types=1);

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Schema;

it('compiles the reconciliation foreign key within the MySQL identifier limit', function (): void {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_SERVER_VERSION)->andReturn('8.4.0');
    $connection = new MySqlConnection($pdo, 'migration_test', config: ['driver' => 'mysql']);
    $migration = require database_path('migrations/2026_11_19_410000_create_payment_reconciliation_tables.php');
    $statements = $connection->pretend(function () use ($connection, $migration): void {
        $schema = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());
        try {
            $migration->up();
        } finally {
            Schema::swap($schema);
        }
    });
    $foreignKey = collect($statements)->pluck('query')->first(
        fn (string $sql): bool => str_contains($sql, 'foreign key (`payment_reconciliation_id`)'),
    );

    expect($foreignKey)->toBeString();
    preg_match('/add constraint `([^`]+)`/', $foreignKey, $matches);
    expect($matches[1] ?? '')->not->toBeEmpty()
        ->and(strlen($matches[1]))->toBeLessThanOrEqual(64)
        ->and($foreignKey)->toContain('references `payment_reconciliations` (`id`) on delete restrict');
})->group('T-P8-11', 'YG-GAP-PAY-003');
