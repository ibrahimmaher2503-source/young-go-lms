<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Rules;

use App\Domain\Catalog\AgeRange;
use Closure;
use DomainException;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class AgeRangeRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly string $minimumField = 'min_age',
        private readonly string $maximumField = 'max_age',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minimum = $this->data[$this->minimumField] ?? null;
        $maximum = $this->data[$this->maximumField] ?? null;

        if (($minimum !== null && filter_var($minimum, FILTER_VALIDATE_INT) === false)
            || ($maximum !== null && filter_var($maximum, FILTER_VALIDATE_INT) === false)) {
            return;
        }

        try {
            new AgeRange($minimum === null ? null : (int) $minimum, $maximum === null ? null : (int) $maximum);
        } catch (DomainException) {
            $fail('validation.age_range')->translate();
        }
    }
}
