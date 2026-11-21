<?php

declare(strict_types=1);

namespace App\Filament\Resources\Library\Pages;

use App\Domain\Library\Actions\AddLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Filament\Resources\Library\LibraryResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\File\File;

final class ListLibraryItems extends WorkspaceListRecords
{
    protected static string $resource = LibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label(__('library.add'))->icon('heroicon-o-plus')
                ->schema([
                    FileUpload::make('file')->label(__('library.file'))->required()->disk(Config::string('media.disk'))->visibility('private')
                        ->maxSize(Config::integer('media.max_kilobytes'))->preserveFilenames(false)
                        ->storeFiles(false)->preventFilePathTampering(),
                    TextInput::make('title.ar')->label(__('library.name').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                    TextInput::make('title.en')->label(__('library.name').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                    TagsInput::make('tags')->label(__('library.tags')),
                    Select::make('scope')->label(__('library.scope'))->native(false)->default('mine')
                        ->options(static fn (): array => ['mine' => __('library.scopes.instructor')] + (($user = Auth::user()) instanceof User && LibraryAccess::managesAll($user) ? ['academy' => __('library.scopes.academy')] : [])),
                ])
                ->action(function (array $data, Action $action): void {
                    $user = Auth::user();
                    abort_unless($user instanceof User, 401);
                    $staged = $data['file'] ?? null;
                    $staged = is_array($staged) ? (reset($staged) ?: null) : $staged;
                    if ($staged === null || $staged === '') {
                        return;
                    }
                    if (! $staged instanceof UploadedFile) {
                        Notification::make()->danger()->title(__('media.invalid_type'))->send();
                        $action->halt();

                        return;
                    }

                    try {
                        app(AddLibraryItem::class)($user, new File($staged->getRealPath() ?: $staged->getPathname()), ['title' => $data['title'] ?? [], 'tags' => $data['tags'] ?? [], 'scope' => $data['scope'] ?? 'mine']);
                        Notification::make()->success()->title(__('library.added'))->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                        $action->halt();
                    } finally {
                        if ($staged instanceof TemporaryUploadedFile) {
                            $staged->delete();
                        }
                    }
                }),
        ];
    }
}
