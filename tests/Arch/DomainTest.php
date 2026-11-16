<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
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
it('uses env only in config files', function () {
    $root = dirname(__DIR__, 2);
    $directories = ['app', 'bootstrap', 'database', 'lang', 'public', 'resources', 'routes', 'tests'];
    $violations = [];

    foreach ($directories as $directory) {
        foreach ((new Filesystem)->allFiles($root.'/'.$directory) as $file) {
            $path = $directory.'/'.$file->getRelativePathname();
            if ($file->getExtension() !== 'php' || str_starts_with(str_replace('\\', '/', $path), 'bootstrap/cache/')) {
                continue;
            }

            $tokens = array_values(array_filter(
                PhpToken::tokenize($file->getContents()),
                static fn (PhpToken $token): bool => ! $token->isIgnorable(),
            ));

            foreach ($tokens as $index => $token) {
                if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED])
                    && strtolower(ltrim($token->text, '\\')) === 'env'
                    && ($tokens[$index + 1]->text ?? null) === '('
                    && ! in_array($tokens[$index - 1]->id ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                    $violations[] = $path.':'.$token->line;
                }
            }
        }
    }

    expect($violations)->toBe([]);
})->group('T-P0-03', 'YG-GAP-CODE-001');

it('requires final domain actions', function () {
    $actions = [];

    foreach ((new Filesystem)->allFiles(dirname(__DIR__, 2).'/app/Domain') as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());
        if (str_contains($path, '/Actions/') && $file->getExtension() === 'php') {
            $class = 'App\\Domain\\'.str_replace('/', '\\', substr($path, 0, -4));
            $actions[] = (new ReflectionClass($class))->isFinal();
        }
    }

    expect(in_array(false, $actions, true))->toBeFalse();
})->group('T-P0-03', 'YG-GAP-CODE-001');
