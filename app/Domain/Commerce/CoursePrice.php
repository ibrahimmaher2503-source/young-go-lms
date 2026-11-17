<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The price of a course in one market, in integer minor units. One row per course and market. Written by the course
 * wizard (T-P3-03); read only by the pricing engine.
 *
 * @property int $id
 * @property int $course_id
 * @property string $market
 * @property string $currency
 * @property int $amount_minor
 */
final class CoursePrice extends Model
{
    protected $table = 'course_prices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function money(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function forMarket(Market $market): bool
    {
        return $this->market === $market->value && $this->currency === $market->currency();
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
