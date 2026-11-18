<?php

declare(strict_types=1);

use App\Console\Commands\Doctor;
use App\Domain\Seo\Actions\BuildSitemap;
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

Schedule::command('live:send-reminders')->everyFiveMinutes()->name('live:send-reminders')->withoutOverlapping();
Schedule::command('quiz:expire-attempts')->everyMinute()->name('quiz:expire-attempts')->withoutOverlapping();
Schedule::command('entitlements:expire')->hourly()->name('entitlements:expire')->withoutOverlapping();
Schedule::command('help:escalate-overdue')->everyFifteenMinutes()->name('help:escalate-overdue')->withoutOverlapping();
Schedule::command('seo:sitemap')->dailyAt('02:30')->name('seo:sitemap')->withoutOverlapping();
// T-P2-07: a scheduled blog post goes public with no save, so drop the cached sitemap every hour.
Schedule::call(static fn () => app(BuildSitemap::class)->forget())->hourly()->name('seo:sitemap-scheduled-posts');
// T-P8-12: cancel abandoned pending orders (releases coupon uses); never touches an order with an open payment or proof.
Schedule::command('orders:expire-abandoned')->hourly()->name('orders:expire-abandoned')->withoutOverlapping();
// T-P8-11: reconcile yesterday's payments with the gateway; report only, never changes an order.
Schedule::command('payments:reconcile')->dailyAt('04:30')->name('payments:reconcile')->withoutOverlapping();
// T-P8-10: freeze last month's instructor statements on the 2nd (idempotent; a settlement is still a manual finance record).
Schedule::command('statements:generate')->monthlyOn(2, '03:15')->name('statements:generate')->withoutOverlapping();
Schedule::command('subscriptions:process')->hourly()->name('subscriptions:process')->withoutOverlapping();
Schedule::command('ai:prune')->dailyAt('03:30')->name('ai:prune')->withoutOverlapping();
// T-P9-08: per-class retention pruning (includes the AI class again; both jobs are idempotent). Never touches financial, certificate or audit data.
Schedule::command('privacy:prune')->dailyAt('03:45')->name('privacy:prune')->withoutOverlapping();
