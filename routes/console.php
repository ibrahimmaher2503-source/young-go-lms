<?php

declare(strict_types=1);

use App\Console\Commands\Doctor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static fn (): bool => Cache::put(Doctor::HEARTBEAT_KEY, now()->timestamp, 300))
    ->name('app:scheduler-heartbeat')
    ->everyMinute();

Schedule::command('entitlements:expire')->hourly()->name('entitlements:expire')->withoutOverlapping();
