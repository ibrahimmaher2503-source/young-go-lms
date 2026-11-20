<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\Category;

final class CategoryImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Categories;
    }

    public function sourceTable(): string
    {
        return 'category';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'parent' => $row['parent'] ?? 0,
            'slug' => $row['slug'] ?? null,
            'font_awesome_class' => $row['font_awesome_class'] ?? null,
            'thumbnail' => $row['thumbnail'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $nameText = trim((string) ($row['name'] ?? ''));

        if ($nameText === '') {
            return RowImportResult::rejected('empty_name', 'Legacy category has an empty name.');
        }

        $rawParent = (int) ($row['parent'] ?? 0);
        $parentId = null;

        if ($rawParent > 0) {
            if ((string) $rawParent === $sourceId) {
                return RowImportResult::rejected('circular_parent_category', 'Legacy category cannot be its own parent.');
            }

            $parentId = $context->mappedId(LegacyEntity::Categories, $rawParent);
            if ($parentId === null) {
                return RowImportResult::rejected('missing_parent_category', "Parent legacy category {$rawParent} is not mapped.");
            }

            if ($context->idMap->isShadowId($parentId)) {
                $parentMeta = $context->mappedMetadata(LegacyEntity::Categories, $rawParent);
                if (($parentMeta['has_parent'] ?? false) === true) {
                    return RowImportResult::rejected('category_depth_exceeded', 'Target categories support at most 2 levels.');
                }
            } else {
                $parent = Category::query()->find($parentId);
                if ($parent === null) {
                    return RowImportResult::rejected('missing_parent_category', "Target parent category {$parentId} not found.");
                }
                if ($parent->parent_id !== null) {
                    return RowImportResult::rejected('category_depth_exceeded', 'Target categories support at most 2 levels.');
                }
            }
        }

        $legacySlug = trim((string) ($row['slug'] ?? ''));
        $slugSource = $legacySlug !== '' ? $legacySlug : $nameText;
        $existingTargetId = $context->existingRecord?->target_id;
        $metadata = [
            'legacy_slug' => $legacySlug !== '' ? $legacySlug : ScriptNameMapper::slugify($nameText, 'category-'.$sourceId),
            'legacy_code' => $row['code'] ?? null,
            'has_parent' => $parentId !== null,
        ];

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(
                LegacyEntity::Categories,
                $sourceId,
                Category::class,
                $existingTargetId,
                $metadata,
            );

            return $existingTargetId !== null
                ? RowImportResult::updated(Category::class, $shadowId, $metadata)
                : RowImportResult::imported(Category::class, $shadowId, $metadata);
        }

        $category = $existingTargetId !== null ? Category::query()->find($existingTargetId) : null;
        $isNew = $category === null;
        if ($isNew) {
            $category = new Category;
            $category->slug_ar = ScriptNameMapper::uniqueSlug('categories', 'slug_ar', $slugSource, 'cat-'.$sourceId);
            $category->slug_en = ScriptNameMapper::uniqueSlug('categories', 'slug_en', $slugSource, 'cat-'.$sourceId);
        }

        $category->forceFill([
            'parent_id' => $parentId,
            'name' => ScriptNameMapper::bilingual($nameText, $context->defaultLocale),
            'icon' => filled($row['font_awesome_class'] ?? null) ? trim((string) $row['font_awesome_class']) : null,
            'image' => filled($row['thumbnail'] ?? null)
                ? trim((string) $row['thumbnail'])
                : (filled($row['sub_category_thumbnail'] ?? null) ? trim((string) $row['sub_category_thumbnail']) : null),
            'sort' => (int) $sourceId,
            'status' => 'active',
        ]);

        if ($isNew && ($createdAt = LegacyDateParser::toUtc($row['date_added'] ?? null)) !== null) {
            $category->created_at = $createdAt->toMutable();
        }
        $category->save();

        return $isNew
            ? RowImportResult::imported(Category::class, (int) $category->getKey(), $metadata)
            : RowImportResult::updated(Category::class, (int) $category->getKey(), $metadata);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Category::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $category = Category::query()->find($targetId);
        if ($category === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedName = trim((string) ($sourceRow['name'] ?? ''));
        $locale = ScriptNameMapper::detectLocale($expectedName, 'ar');
        $actualName = (string) $category->getTranslation('name', $locale, false);
        if ($actualName !== $expectedName) {
            $mismatches[] = "name[{$locale}] expected '{$expectedName}', got '{$actualName}'";
        }

        return $mismatches;
    }
}
