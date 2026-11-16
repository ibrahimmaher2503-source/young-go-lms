<?php

declare(strict_types=1);

use App\Domain\Catalog\AgeBandResolver;
use App\Domain\Catalog\AgeRange;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Catalog\Rules\AgeRangeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('resolves configured age bands using birth month boundaries', function (int $birthYear, int $birthMonth, string $expectedLabel) {
    $this->travelTo(now()->setDate(2026, 11, 16));

    AgeBand::create(['label' => ['en' => 'Under ten'], 'min' => 0, 'max' => 9, 'sort' => 1]);
    AgeBand::create(['label' => ['en' => 'Ten to fifteen'], 'min' => 10, 'max' => 15, 'sort' => 2]);

    $band = app(AgeBandResolver::class)->resolve($birthYear, $birthMonth);

    expect($band?->getTranslation('label', 'en'))->toBe($expectedLabel);
})->with([
    'minimum boundary' => [2016, 11, 'Ten to fifteen'],
    'one month before boundary' => [2016, 12, 'Under ten'],
    'maximum boundary' => [2011, 11, 'Ten to fifteen'],
])->group('T-P1-07', 'A1', 'YG-GAP-FAM-003');

it('resolves ages across a calendar year boundary', function () {
    $this->travelTo(now()->setDate(2027, 1, 1));
    AgeBand::create(['label' => ['en' => 'Under ten'], 'min' => 0, 'max' => 9, 'sort' => 1]);
    AgeBand::create(['label' => ['en' => 'Ten to fifteen'], 'min' => 10, 'max' => 15, 'sort' => 2]);

    $resolver = app(AgeBandResolver::class);

    expect($resolver->resolve(2017, 1)?->getTranslation('label', 'en'))->toBe('Ten to fifteen')
        ->and($resolver->resolve(2017, 2)?->getTranslation('label', 'en'))->toBe('Under ten');
})->group('T-P1-07', 'A1', 'YG-GAP-FAM-003');

it('uses stored translated labels and changed ranges without code changes', function () {
    $this->travelTo(now()->setDate(2026, 11, 16));

    $band = AgeBand::create([
        'label' => ['ar' => 'مراهقون', 'en' => 'Teens'],
        'min' => 13,
        'max' => 18,
        'sort' => 4,
    ]);

    expect($band->getTranslation('label', 'ar'))->toBe('مراهقون')
        ->and(app(AgeBandResolver::class)->resolve(2008, 11)?->is($band))->toBeTrue();

    $band->update(['min' => 19, 'max' => 25, 'sort' => 1]);

    expect(app(AgeBandResolver::class)->resolve(2008, 11))->toBeNull()
        ->and(app(AgeBandResolver::class)->resolve(2006, 11)?->is($band))->toBeTrue();
})->group('T-P1-07', 'A2', 'D-005', 'ED-006');

it('returns no band when birth details are incomplete or not configured', function () {
    expect(app(AgeBandResolver::class)->resolve(null, null))->toBeNull()
        ->and(app(AgeBandResolver::class)->resolve(2010, null))->toBeNull()
        ->and(app(AgeBandResolver::class)->resolve(2010, 1))->toBeNull();
})->group('T-P1-07', 'A1');

it('rejects an invalid birth month instead of guessing', function () {
    expect(fn () => app(AgeBandResolver::class)->resolve(2010, 13))
        ->toThrow(InvalidArgumentException::class);
})->group('T-P1-07', 'A1', 'YG-GAP-FAM-003');

it('models nullable inclusive age ranges', function () {
    $range = new AgeRange(10, 15);

    expect($range->includes(10))->toBeTrue()
        ->and($range->includes(15))->toBeTrue()
        ->and($range->includes(9))->toBeFalse()
        ->and($range->includes(16))->toBeFalse()
        ->and((new AgeRange(null, 5))->includes(0))->toBeTrue()
        ->and((new AgeRange(18, null))->includes(100))->toBeTrue()
        ->and((new AgeRange(null, null))->includes(100))->toBeTrue();
})->group('T-P1-07', 'A1');

it('rejects invalid age ranges', function (?int $min, ?int $max) {
    expect(fn () => new AgeRange($min, $max))->toThrow(DomainException::class);
})->with([
    'negative minimum' => [-1, 18],
    'maximum before minimum' => [19, 18],
])->group('T-P1-07', 'A1');

it('validates course and band age range ordering while allowing open ends', function () {
    $invalidCourseRange = Validator::make(
        ['min_age' => 13, 'max_age' => 12],
        ['max_age' => ['nullable', 'integer', 'min:0', new AgeRangeRule]],
    );
    $validOpenRange = Validator::make(
        ['min_age' => 13, 'max_age' => null],
        ['max_age' => ['nullable', 'integer', 'min:0', new AgeRangeRule]],
    );
    $invalidBandRange = Validator::make(
        ['min' => 10, 'max' => 9],
        ['max' => ['nullable', 'integer', 'min:0', new AgeRangeRule('min', 'max')]],
    );

    expect($invalidCourseRange->fails())->toBeTrue()
        ->and($validOpenRange->passes())->toBeTrue()
        ->and($invalidBandRange->fails())->toBeTrue();
})->group('T-P1-07', 'A1');
