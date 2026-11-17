<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveCategory
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?Category $category = null): Category
    {
        Gate::authorize($category ? 'category.update' : 'category.create');

        return DB::transaction(static function () use ($data, $category): Category {
            $id = $category?->getKey();
            $parentId = $data['parent_id'] ?? null;

            // Every move and child creation locks both affected nodes before checking depth.
            $nodes = Category::query()->whereKey(array_values(array_filter([$id, $parentId])))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $record = $id === null ? new Category : $nodes->get($id);
            abort_if($record === null, 404);

            if ($parentId !== null) {
                $parent = $nodes->get($parentId);

                if ($parent === null || $parent->parent_id !== null || $parent->is($record)
                    || ($record->exists && $record->children()->lockForUpdate()->first() !== null)) {
                    throw ValidationException::withMessages(['parent_id' => __('categories.invalid_parent')]);
                }
            }

            $record->fill($data)->save();

            return $record;
        }, attempts: 3);
    }
}
