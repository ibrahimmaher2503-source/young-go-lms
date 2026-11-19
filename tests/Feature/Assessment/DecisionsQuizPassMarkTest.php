<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-020 | A1

use App\Domain\Assessment\QuizPassMark;

it('defaults the pass mark to 60 and follows the configured default', function (): void {
    expect(QuizPassMark::default())->toBe(60);

    config(['assessment.default_pass_mark_percent' => 75]);
    expect(QuizPassMark::default())->toBe(75);
})->group('T-DECISIONS-2', 'O-020', 'A1');

it('falls back to 60 for an invalid configured default', function (mixed $value): void {
    config(['assessment.default_pass_mark_percent' => $value]);

    expect(QuizPassMark::default())->toBe(60);
})->with([0, 101, null])->group('T-DECISIONS-2', 'O-020', 'A1');

it('uses the platform default for a null explicit mark and the explicit mark otherwise', function (): void {
    expect(QuizPassMark::effective(null))->toBe(60)
        ->and(QuizPassMark::effective(80))->toBe(80);
})->group('T-DECISIONS-2', 'O-020', 'A1');
