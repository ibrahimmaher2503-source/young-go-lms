<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->coverageRoot = sys_get_temp_dir().'/young-go-coverage-'.Str::uuid();
    $this->originalBasePath = $this->app->basePath();
    $fs = new Filesystem;
    foreach (['agents/tasks', 'docs', 'tests'] as $directory) {
        $fs->ensureDirectoryExists($this->coverageRoot.'/'.$directory);
    }

    $fs->put($this->coverageRoot.'/agents/tasks/P00.md', <<<'MD'
### T-P0-07 — Fixture
- refs: ED-020, D-001, YG-GAP-OPS-005

**Acceptance**
- A1 Explicitly verified.
- A2 Requires evidence.
MD);
    $fs->put($this->coverageRoot.'/tests/FixtureTest.php', <<<'PHP'
<?php
it('has an acceptance tag', fn () => true)
    ->group('T-P0-07', 'A1', 'ED-020', 'D-001', 'YG-GAP-OPS-005');
// it('unrelated')->group('T-P0-07', 'A2');
$sample = "->group('T-P0-07', 'A2')";
PHP);
    $fs->put($this->coverageRoot.'/docs/TRACEABILITY_MATRIX.md', <<<'MD'
| Requirement | Title | Tasks / coverage | Tests (tags) |
|---|---|---|---|
| ED-020 | Testing | T-P0-07 | |
| ED-021 | Observability | T-P0-08 | External evidence |
MD);
    Artisan::all();
    $this->app->setBasePath($this->coverageRoot);
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);
    (new Filesystem)->deleteDirectory($this->coverageRoot);
});

it('reports an acceptance item with no tagged test or evidence', function () {
    $this->artisan('app:req-coverage', ['--task' => 'T-P0-07'])
        ->expectsOutput('Missing coverage: T-P0-07:A2')
        ->assertFailed();
})->group('T-P0-07', 'A3', 'ED-020', 'ED-028');

it('refreshes only scoped matrix rows using tests and accepts recorded evidence', function () {
    $fs = new Filesystem;
    $fs->put($this->coverageRoot.'/agents/PROGRESS.md', '| T-P0-07:A2 | evidence: local smoke run |');

    $this->artisan('app:req-coverage', ['--phase' => 'P0', '--refresh' => true])
        ->assertSuccessful();

    expect($fs->get($this->coverageRoot.'/docs/TRACEABILITY_MATRIX.md'))
        ->toContain('| ED-020 | Testing | T-P0-07 | `tests/FixtureTest.php` |')
        ->toContain('| ED-021 | Observability | T-P0-08 | External evidence |');
})->group('T-P0-07', 'A3', 'ED-020', 'ED-028');
