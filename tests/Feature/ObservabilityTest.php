<?php

declare(strict_types=1);

use App\Domain\Shared\Observability\ErrorReporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Pulse;

it('reports application errors once as local JSON without sensitive exception text', function () {
    $path = tempnam(sys_get_temp_dir(), 'young-go-log-');

    try {
        config(['logging.channels.single.path' => $path]);
        Log::forgetChannel('single');

        expect(app(ErrorReporter::class))->toBeInstanceOf(ErrorReporter::class);
        report(new RuntimeException('must-not-be-written-to-log'));

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        expect($lines)->toHaveCount(1);
        expect($lines[0])->not->toContain('must-not-be-written-to-log');
        $entry = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        expect($entry['level_name'])->toBe('ERROR')
            ->and($entry['context']['exception_type'])->toBe(RuntimeException::class);
    } finally {
        Log::forgetChannel('single');
        unlink($path);
    }
})->group('T-P0-07', 'ED-021');

it('keeps the Pulse dashboard unregistered pending an authorization policy', function () {
    expect(Route::has('pulse'))->toBeFalse();
    $this->get('/pulse')->assertNotFound();
})->group('T-P0-07', 'ED-021');

it('persists Pulse metrics to its configured database', function () {
    config([
        'database.connections.pulse_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'pulse.storage.database.connection' => 'pulse_test',
        'pulse.ingest.driver' => 'storage',
    ]);

    expect(Artisan::call('migrate', [
        '--database' => 'pulse_test',
        '--path' => 'database/migrations/2026_11_16_000100_create_pulse_tables.php',
    ]))->toBe(0);

    $pulse = app(Pulse::class)->startRecording();
    $pulse->record('ci_smoke', 'local_metric', 7);
    $pulse->ingest();

    expect(DB::connection('pulse_test')->table('pulse_entries')->where('type', 'ci_smoke')->value('value'))->toBe(7);
})->group('T-P0-07', 'ED-021');
