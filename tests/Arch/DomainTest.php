<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Fixtures\InvalidDomainAction;

function domainDependencies(string $target): void
{
    expect($target)->not->toUse(['App\\Http', 'Illuminate\\Http', 'Livewire', 'Filament'])->toBe($target);
}

it('keeps domain independent of presentation and HTTP', function () {
    domainDependencies('App\\Domain');
})->group('T-P0-03', 'A5', 'YG-GAP-CODE-001');

it('rejects a domain class that depends on an HTTP request', function () {
    expect(fn () => domainDependencies(InvalidDomainAction::class))
        ->toThrow(ExpectationFailedException::class);
})->group('T-P0-03', 'A5', 'YG-GAP-CODE-001');

arch('application classes use strict types')->expect('App')->toUseStrictTypes();
arch('application classes use config rather than env')->expect('App')->not->toUse('env');

it('requires final domain actions', function () {
    $actions = [];

    foreach (File::allFiles(dirname(__DIR__, 2).'/app/Domain') as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());
        if (str_contains($path, '/Actions/') && $file->getExtension() === 'php') {
            $class = 'App\\Domain\\'.str_replace('/', '\\', substr($path, 0, -4));
            $actions[] = (new ReflectionClass($class))->isFinal();
        }
    }

    expect(in_array(false, $actions, true))->toBeFalse();
})->group('T-P0-03', 'YG-GAP-CODE-001');
