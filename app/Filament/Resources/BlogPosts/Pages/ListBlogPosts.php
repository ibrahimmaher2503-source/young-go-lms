<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Content\Actions\SaveBlogPost;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\BlogPost;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ListBlogPosts extends WorkspaceListRecords
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)->using(fn (array $data): BlogPost => $this->savePost($data))];
    }

    /** @param array<string, mixed> $data */
    #[Unexposed]
    public function savePost(array $data, ?BlogPost $record = null): BlogPost
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        try {
            return app(SaveBlogPost::class)($actor, $record, $data);
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
