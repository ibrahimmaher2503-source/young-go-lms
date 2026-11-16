<?php

declare(strict_types=1);

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Domain\Shared\Search\ArabicNormalizer;
use Brick\Money\Exception\MoneyFormatException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Fixtures\SearchableRecord;

uses(TestCase::class);

it('normalizes Arabic and Latin for shared deterministic matching', function (string $input, string $expected) {
    expect(ArabicNormalizer::normalize($input))->toBe($expected);
})->with([
    ['أداء', 'اداء'],
    ['مسرحية', 'مسرحيه'],
    ['مُمثّل', 'ممثل'],
    ['  ٱلـفَنّ   ＡＢＣ  ', 'الفن abc'],
    ['ؤ ئ ى', 'و ي ي'],
])->group('T-P0-06', 'A1', 'YG-GAP-SRCH-001');

it('rejects floating point amounts at the money boundary', function () {
    Money::ofMinor(120.25, 'EGP');
})->throws(TypeError::class)->group('T-P0-06', 'A2', 'YG-GAP-CODE-001');

it('formats market prices in both locales with Western digits', function (string $locale) {
    $result = (new PriceFormatter)->format(Money::ofMinor(123450, 'EGP'), Market::Egypt, $locale);

    expect($result)->toContain('1,234.50')->not->toMatch('/[٠-٩]/u');
    expect($result)->toContain($locale === 'ar' ? 'ج.م' : 'EGP');
})->with(['ar', 'en'])->group('T-P0-06', 'A2', 'D-009');

it('regenerates persisted search text from translated and plain fields when saving', function () {
    config(['database.connections.shared_primitives' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]]);
    Schema::connection('shared_primitives')->create(
        'shared_primitives_search',
        function (Blueprint $table): void {
            $table->id();
            $table->text('title');
            $table->text('summary');
            $table->text('search_text');
        },
    );
    $record = new SearchableRecord;
    $record->setConnection('shared_primitives');
    $record->title = ['ar' => 'أداء', 'en' => 'ACTING'];
    $record->summary = '  مسرحية ';
    $record->save();

    expect($record->refresh()->search_text)->toBe('اداء acting مسرحيه');

    $record->summary = 'مُمثّل';
    $record->save();

    expect($record->refresh()->search_text)->toBe('اداء acting ممثل');
})->group('T-P0-06', 'YG-GAP-SRCH-001');

it('rejects prices too large for exact ICU formatting', function () {
    (new PriceFormatter)->format(Money::ofMinor(PHP_INT_MAX, 'EGP'), Market::Egypt, 'en');
})->throws(MoneyFormatException::class)->group('T-P0-06', 'A2', 'YG-GAP-CODE-001');
