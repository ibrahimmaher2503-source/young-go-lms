<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Domain\Catalog\Actions\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Http\UploadedFile;
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
            $image = $data['image'] ?? null;
            $bytes = null;
            if ($image instanceof UploadedFile) {
                if (! in_array(strtolower($image->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    throw ValidationException::withMessages(['image' => __('media.invalid_type')]);
                }

                $bytes = $image->get();
                if (! is_string($bytes)) {
                    throw ValidationException::withMessages(['image' => __('media.invalid_type')]);
                }
            }

            return app(SaveCategory::class)($data, $record, $bytes);
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
