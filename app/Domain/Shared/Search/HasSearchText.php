<?php

declare(strict_types=1);

namespace App\Domain\Shared\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** @mixin Model */
trait HasSearchText
{
    /** @return list<string> */
    abstract protected function searchTextAttributes(): array;

    public static function bootHasSearchText(): void
    {
        static::saving(static function (self $model): void {
            $values = [];
            foreach ($model->searchTextAttributes() as $attribute) {
                $value = self::searchTextAttributeValue($model, $attribute);
                foreach (Arr::flatten(is_array($value) ? $value : [$value]) as $part) {
                    if (is_string($part)) {
                        $values[] = $part;
                    }
                }
            }

            $model->setAttribute('search_text', ArabicNormalizer::normalize(implode(' ', $values)));
        });
    }

    private static function searchTextAttributeValue(Model $model, string $attribute): mixed
    {
        if (method_exists($model, 'isTranslatableAttribute')
            && method_exists($model, 'getTranslations')
            && $model->isTranslatableAttribute($attribute)) {
            return $model->getTranslations($attribute);
        }

        return $model->getAttribute($attribute);
    }
}
