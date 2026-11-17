<?php

declare(strict_types=1);

use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\File;

it('keeps every resource list on the approved workspace instead of a default table page', function () {
    $lists = 0;
    foreach (File::allFiles(app_path('Filament/Resources')) as $file) {
        $class = 'App\\Filament\\Resources\\'.str_replace(['/', '.php'], ['\\', ''], str_replace('\\', '/', $file->getRelativePathname()));
        if (class_exists($class) && is_subclass_of($class, ListRecords::class)) {
            expect(is_subclass_of($class, WorkspaceListRecords::class))->toBeTrue($class.' must use WorkspaceListRecords');
            $lists++;
        }
    }
    expect($lists)->toBeGreaterThanOrEqual(3);
})->group('T-P1-08', 'D-026');
