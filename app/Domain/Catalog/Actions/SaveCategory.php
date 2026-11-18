<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Media\ImageReencoder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SaveCategory
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?Category $category = null, ?string $imageBytes = null): Category
    {
        Gate::authorize($category ? 'category.update' : 'category.create');

        $file = $data['image'] ?? null;
        $newPath = null;
        if ($imageBytes !== null) {
            $newPath = $this->storeImage($imageBytes);
            $data['image'] = $newPath;
        } elseif ($file !== null && $file !== $category?->getAttribute('image')) {
            throw ValidationException::withMessages(['image' => __('media.invalid_path')]);
        }

        try {
            return $this->persist($data, $category);
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }
    }

    private function storeImage(string $bytes): string
    {
        if ($bytes === '' || strlen($bytes) > 4096 * 1024) {
            throw ValidationException::withMessages(['image' => __('media.too_large')]);
        }

        if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['image' => __('media.invalid_type')]);
        }

        try {
            $image = ImageReencoder::reencode($bytes);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['image' => __('media.invalid_type')]);
        }

        $path = 'categories/'.Str::uuid().'.'.$image['extension'];
        if (! Storage::disk('public')->put($path, $image['bytes'], 'public')) {
            throw ValidationException::withMessages(['image' => __('media.store_failed')]);
        }

        return $path;
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data, ?Category $category): Category
    {
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
