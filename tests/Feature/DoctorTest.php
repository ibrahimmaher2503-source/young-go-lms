<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
    expect(Artisan::call('migrate'))->toBe(0);
    Storage::fake('local');
});

it('passes with an explicit local profile and a fresh scheduler heartbeat', function () {
    expect(Artisan::call('schedule:run'))->toBe(0);
    $this->artisan('app:doctor')
        ->expectsOutputToContain('Redis: skipped')
        ->expectsOutputToContain('Queue: synchronous')
        ->expectsOutputToContain('Scheduler: OK')
        ->assertSuccessful();
})->group('T-P0-07', 'A2', 'ED-022', 'YG-GAP-OPS-003');

it('reports a stale scheduler heartbeat', function () {
    Cache::put('app:scheduler-heartbeat', now()->subMinutes(10)->timestamp);
    $this->artisan('app:doctor')->expectsOutputToContain('Scheduler: FAIL')->assertFailed();
})->group('T-P0-07', 'A2', 'ED-022');

it('fails when configured Redis cannot be reached', function () {
    Cache::put('app:scheduler-heartbeat', now()->timestamp);
    config(['queue.default' => 'redis']);
    Redis::shouldReceive('connection')->with('default')->andThrow(new RuntimeException('connection unavailable'));
    Queue::shouldReceive('size')->andReturn(0);

    $this->artisan('app:doctor')->expectsOutputToContain('Redis: FAIL')->assertFailed();
})->group('T-P0-07', 'A2', 'ED-022');

it('fails when the queue backend cannot be reached', function () {
    Cache::put('app:scheduler-heartbeat', now()->timestamp);
    config(['queue.default' => 'database']);
    Queue::shouldReceive('size')->andThrow(new RuntimeException('queue unavailable'));

    $this->artisan('app:doctor')->expectsOutputToContain('Queue: FAIL')->assertFailed();
})->group('T-P0-07', 'A2', 'ED-022');

it('fails when storage prevents writes', function () {
    Cache::put('app:scheduler-heartbeat', now()->timestamp);
    Storage::shouldReceive('disk')->with('local')->andThrow(new RuntimeException('storage unavailable'));

    $this->artisan('app:doctor')->expectsOutputToContain('Storage: FAIL')->assertFailed();
})->group('T-P0-07', 'A2', 'ED-022');
