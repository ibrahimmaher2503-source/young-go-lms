<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->fixturePath = sys_get_temp_dir().'/young-go-quality-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->fixturePath.'/views', 0755, true);
    File::makeDirectory($this->fixturePath.'/lang/ar', 0755, true);
    File::makeDirectory($this->fixturePath.'/lang/en', 0755, true);
    $this->app->useLangPath($this->fixturePath.'/lang');
    config(['view.paths' => [$this->fixturePath.'/views']]);
});

afterEach(function (): void {
    $path = realpath($this->fixturePath);
    if ($path !== false && dirname($path) === realpath(sys_get_temp_dir()) && str_starts_with(basename($path), 'young-go-quality-')) {
        File::deleteDirectory($path);
    }
});

it('registers the required quality commands', function (): void {
    expect(Artisan::all())->toHaveKeys(['app:check-rtl', 'app:check-translations']);
})->group('T-P0-03', 'YG-GAP-CODE-001');

it('rejects physical direction utilities including variants', function (string $utility): void {
    File::put($this->fixturePath.'/views/page.blade.php', '<div class="'.$utility.'"></div>');
    $this->artisan('app:check-rtl')->assertFailed();
})->with(['ml-4', 'hover:mr-2', '-left-4', 'text-right', 'rounded-l-lg', 'border-r', 'md:pl-2'])
    ->group('T-P0-03', 'T-P0-03:A3', 'YG-GAP-CODE-001');

it('accepts logical utilities and unrelated color names', function (): void {
    File::put($this->fixturePath.'/views/page.blade.php', '<div class="ms-4 me-2 ps-3 pe-2 text-start rounded-s-lg border-e border-lime-500"></div>');
    $this->artisan('app:check-rtl')->assertSuccessful();
})->group('T-P0-03', 'T-P0-03:A3', 'YG-GAP-CODE-001');

it('reports a missing nested translation key in either locale', function (string $locale): void {
    File::put($this->fixturePath.'/lang/ar/messages.php', "<?php return ['button' => ['save' => 'حفظ']];");
    File::put($this->fixturePath.'/lang/en/messages.php', "<?php return ['button' => ['save' => 'Save']];");
    File::put($this->fixturePath.'/lang/'.$locale.'/messages.php', "<?php return ['button' => []];");
    $this->artisan('app:check-translations')->expectsOutputToContain('messages.button.save')->assertFailed();
})->with(['ar', 'en'])->group('T-P0-03', 'T-P0-03:A4', 'YG-GAP-QA-001');

it('accepts matching PHP and JSON translation keys', function (): void {
    foreach (['ar', 'en'] as $locale) {
        File::put($this->fixturePath.'/lang/'.$locale.'/messages.php', "<?php return ['button' => ['save' => 'Save']];");
        File::put($this->fixturePath.'/lang/'.$locale.'.json', '{"Welcome":"Welcome"}');
    }
    $this->artisan('app:check-translations')->assertSuccessful();
})->group('T-P0-03', 'T-P0-03:A4', 'YG-GAP-QA-001');

it('rejects missing JSON keys', function (): void {
    File::put($this->fixturePath.'/lang/ar.json', '{"Welcome":"مرحبا"}');
    File::put($this->fixturePath.'/lang/en.json', '{}');
    $this->artisan('app:check-translations')->expectsOutputToContain('Welcome')->assertFailed();
})->group('T-P0-03', 'T-P0-03:A4', 'YG-GAP-QA-001');

it('rejects malformed translation files', function (string $filename, string $contents): void {
    File::put($this->fixturePath.'/lang/'.$filename, $contents);
    $this->artisan('app:check-translations')->assertFailed();
})->with([
    ['ar.json', '{invalid'],
    ['ar/messages.php', '<?php return "invalid";'],
    ['ar/messages.php', '<?php return [;'],
])->group('T-P0-03', 'T-P0-03:A4', 'YG-GAP-QA-001');

it('fails when required locale directories are missing', function (): void {
    $this->app->useLangPath($this->fixturePath.'/missing');
    $this->artisan('app:check-translations')->assertFailed();
})->group('T-P0-03', 'T-P0-03:A4', 'YG-GAP-QA-001');
