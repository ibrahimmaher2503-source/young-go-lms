<?php

declare(strict_types=1);

// T-P5-03 | Roadmap M5.2, PRD A11 | A1 only inside the join window, A2 open and completed rules, A3 cancelled never joins

use App\Domain\Live\JoinWindow;
use App\Models\LiveSession;
use Carbon\CarbonImmutable;

function jwSession(string $status = LiveSession::SCHEDULED, int $window = 15): LiveSession
{
    return new LiveSession([
        'status' => $status,
        'starts_at' => '2027-03-10 18:00:00',
        'ends_at' => '2027-03-10 19:00:00',
        'join_window_minutes' => $window,
    ]);
}

it('opens a scheduled session only from the join window until its end', function (): void {
    $session = jwSession();
    $at = static fn (string $time): CarbonImmutable => CarbonImmutable::parse($time, 'UTC');

    expect(JoinWindow::state($session, $at('2027-03-10 17:00')))->toBe(JoinWindow::UPCOMING)
        ->and(JoinWindow::state($session, $at('2027-03-10 17:44')))->toBe(JoinWindow::UPCOMING)
        ->and(JoinWindow::state($session, $at('2027-03-10 17:45')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::state($session, $at('2027-03-10 18:30')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::state($session, $at('2027-03-10 18:59')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::state($session, $at('2027-03-10 19:00')))->toBe(JoinWindow::ENDED)
        ->and(JoinWindow::state($session, $at('2027-03-11 00:00')))->toBe(JoinWindow::ENDED);
});

it('makes a zero minute window open exactly at the start', function (): void {
    $session = jwSession(LiveSession::SCHEDULED, 0);
    $at = static fn (string $time): CarbonImmutable => CarbonImmutable::parse($time, 'UTC');

    expect(JoinWindow::state($session, $at('2027-03-10 17:59')))->toBe(JoinWindow::UPCOMING)
        ->and(JoinWindow::state($session, $at('2027-03-10 18:00')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::opensAt($session)->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 18:00');
});

it('keeps an opened session within its scheduled window', function (): void {
    $session = jwSession(LiveSession::OPEN);
    $at = static fn (string $time): CarbonImmutable => CarbonImmutable::parse($time, 'UTC');

    expect(JoinWindow::state($session, $at('2027-03-10 17:44')))->toBe(JoinWindow::UPCOMING)
        ->and(JoinWindow::state($session, $at('2027-03-10 17:45')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::state($session, $at('2027-03-10 18:59')))->toBe(JoinWindow::JOINABLE)
        ->and(JoinWindow::state($session, $at('2027-03-10 19:00')))->toBe(JoinWindow::ENDED);
});

it('never treats a finished or cancelled session as joinable', function (): void {
    $at = CarbonImmutable::parse('2027-03-10 18:30', 'UTC');

    expect(JoinWindow::state(jwSession(LiveSession::COMPLETED), $at))->toBe(JoinWindow::ENDED)
        ->and(JoinWindow::state(jwSession(LiveSession::COMPLETED), CarbonImmutable::parse('2027-03-10 09:00', 'UTC')))->toBe(JoinWindow::ENDED)
        ->and(JoinWindow::state(jwSession(LiveSession::CANCELLED), $at))->toBe(JoinWindow::CANCELLED);
});

it('computes when the join button appears', function (): void {
    $session = jwSession(LiveSession::SCHEDULED, 20);

    expect(JoinWindow::opensAt($session)->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 17:40');

    $session->join_window_minutes = 240;
    expect(JoinWindow::opensAt($session)->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 14:00');
});
