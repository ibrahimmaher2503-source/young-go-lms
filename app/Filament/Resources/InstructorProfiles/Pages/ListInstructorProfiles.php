<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorProfiles\Pages;

use App\Domain\Instructors\Actions\SaveInstructorProfile;
use App\Filament\Resources\InstructorProfiles\InstructorProfileResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\InstructorProfile;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ListInstructorProfiles extends WorkspaceListRecords
{
    protected static string $resource = InstructorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)->using(fn (array $data): InstructorProfile => $this->saveProfile($data))];
    }

    /** @param array<string, mixed> $data */
    #[Unexposed]
    public function saveProfile(array $data, ?InstructorProfile $record = null): InstructorProfile
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        try {
            return app(SaveInstructorProfile::class)($actor, $record, $data);
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
