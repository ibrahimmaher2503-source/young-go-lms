<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Domain\Catalog\Actions\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Validation\ValidationException;

final class ListCategories extends WorkspaceListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->using(fn (array $data): Category => $this->saveCategory($data)),
        ];
    }

    /** @param array<string, mixed> $data */
    #[Unexposed]
    public function saveCategory(array $data, ?Category $record = null): Category
    {
        try {
            return app(SaveCategory::class)($data, $record);
        } catch (ValidationException $exception) {
            $prefix = $this->getMountedActionSchema()?->getStatePath();
            if ($prefix === null) {
                throw $exception;
            }

            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$prefix.'.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
