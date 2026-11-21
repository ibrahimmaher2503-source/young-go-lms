<?php

// T-P9-08 | YG-GAP-PRV-001, O-025 | A1 A2

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Privacy\Actions\AnonymizeAccount;
use App\Domain\Privacy\Actions\ReconcileOrphanedUploads;
use App\Domain\Privacy\ProtectedData;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    // Storage::fake() wipes a shared root that other concurrent test runs also use, so each test gets its own
    // local and public roots, removed again in afterEach.
    $root = storage_path('framework/testing/disks/tp908-'.Str::lower(Str::random(12)));
    tp908StorageRoot($root);
    Storage::set('local', Storage::createLocalDriver(['driver' => 'local', 'root' => $root.'/local']));
    Storage::set('public', Storage::createLocalDriver(['driver' => 'local', 'root' => $root.'/public']));
});

afterEach(function (): void {
    if (($root = tp908StorageRoot()) !== null) {
        (new Filesystem)->deleteDirectory($root);
        tp908StorageRoot(null);
    }
});

/**
 * Storage adapter whose delete() can be told to throw or return false for given paths. Everything else
 * is the real fake disk, because it shares the driver of the disk it wraps.
 */
final class TP908FlakyDisk extends FilesystemAdapter
{
    /** @var array<string, 'throw'|'false'> */
    public array $failures = [];

    public function delete($paths)
    {
        $path = (string) $paths;

        return match ($this->failures[$path] ?? null) {
            'throw' => throw new RuntimeException("Adapter unavailable while deleting [{$path}]."),
            'false' => false,
            default => parent::delete($paths),
        };
    }
}

function tp908StorageRoot(?string $set = null): ?string
{
    static $root = null;
    if (func_num_args() > 0) {
        $root = $set;
    }

    return $root;
}

function tp908StorageFlaky(string $disk, array $failures): TP908FlakyDisk
{
    $real = Storage::disk($disk);
    $flaky = new TP908FlakyDisk($real->getDriver(), $real->getAdapter(), []);
    $flaky->failures = $failures;
    Storage::set($disk, $flaky);

    return $flaky;
}

function tp908StoragePut(string $disk, string $path, int $ageMinutes = 0): void
{
    Storage::disk($disk)->put($path, 'content of '.$path);
    if ($ageMinutes > 0) {
        touch(Storage::disk($disk)->path($path), time() - $ageMinutes * 60);
    }
}

function tp908StorageStudent(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

function tp908StorageAdmin(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('admin');
}

function tp908StorageAssignmentId(): int
{
    $admin = tp908StorageAdmin();
    $course = Course::query()->create([
        'title' => ['ar' => 'الرسم', 'en' => 'Drawing'],
        'slug_ar' => 'storage-reconcile-ar', 'slug_en' => 'storage-reconcile-en',
    ]);
    $course->instructors()->attach($admin->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);

    return (int) app(ManageAssessmentItems::class)->add($admin, $section, 'assignment', ['title' => ['ar' => 'واجب', 'en' => 'Assignment']])->itemable_id;
}

/** @param list<string> $paths one submission revision holding a file row per path */
function tp908StorageAttachSubmission(User $learner, int $assignmentId, array $paths): void
{
    $submissionId = DB::table('assignment_submissions')->insertGetId([
        'learner_id' => $learner->id, 'assignment_id' => $assignmentId, 'status' => 'submitted',
    ]);
    $revisionId = DB::table('submission_revisions')->insertGetId(['submission_id' => $submissionId, 'number' => 1]);
    foreach ($paths as $path) {
        DB::table('submission_files')->insert([
            'revision_id' => $revisionId, 'kind' => 'document', 'display_name' => basename($path), 'path' => $path,
            'mime' => 'application/pdf', 'size' => 10, 'sha256' => hash('sha256', $path), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function tp908StorageApplication(User $user, string $path): void
{
    DB::table('instructor_applications')->insert([
        'user_id' => $user->id, 'full_name' => 'Storage Check', 'discipline' => 'Drawing', 'bio' => 'Bio',
        'portfolio_links' => '[]', 'locale' => 'en', 'id_document_path' => $path, 'id_document_mime' => 'application/pdf',
        'id_document_size' => 10, 'id_document_sha256' => hash('sha256', $path), 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * A learner with referenced files on every target, plus stale and fresh unreferenced files and files the
 * reconciler must always skip. Stale files are aged on the real filesystem; fresh files keep their write time.
 *
 * @return array{learner: User, assignmentId: int}
 */
function tp908StorageFixture(): array
{
    $learner = tp908StorageStudent();
    $assignmentId = tp908StorageAssignmentId();
    tp908StorageAttachSubmission($learner, $assignmentId, ['submissions/keep-work.pdf']);
    tp908StorageApplication($learner, 'instructor-applications/keep-id.pdf');
    DB::table('users')->where('id', $learner->id)->update(['avatar_path' => 'avatars/keep.jpg']);

    // Referenced: must survive every run, even with --apply and --grace=0.
    tp908StoragePut('local', 'submissions/keep-work.pdf');
    tp908StoragePut('local', 'instructor-applications/keep-id.pdf', 120);
    tp908StoragePut('local', 'avatars/keep.jpg', 120);
    tp908StoragePut('public', 'avatars/keep.jpg', 120);

    // Stale orphans, one per target directory.
    tp908StoragePut('local', 'submissions/orphan-old.pdf', 120);
    tp908StoragePut('local', 'instructor-applications/orphan-old.pdf', 120);
    tp908StoragePut('local', 'avatars/orphan-old.jpg', 120);
    tp908StoragePut('public', 'avatars/orphan-old.jpg', 120);

    // Fresh orphan: inside the default 60 minute grace window.
    tp908StoragePut('local', 'submissions/fresh-orphan.pdf');

    // Never flagged: ignored file names and the staging placeholder.
    tp908StoragePut('local', 'submissions/.gitignore', 120);
    tp908StoragePut('local', 'submissions/.DS_Store', 120);
    tp908StoragePut('local', 'privacy/staging-placeholder.txt', 120);

    return ['learner' => $learner, 'assignmentId' => $assignmentId];
}

// --- A1: post-commit storage failures in AnonymizeAccount ---

it('keeps anonymizing and keeps deleting other files when one local adapter delete throws', function (): void {
    Exceptions::fake();
    $learner = tp908StorageStudent();
    $admin = tp908StorageAdmin();
    $assignmentId = tp908StorageAssignmentId();
    tp908StorageAttachSubmission($learner, $assignmentId, ['submissions/work.pdf']);
    tp908StorageApplication($learner, 'instructor-applications/id.pdf');
    DB::table('users')->where('id', $learner->id)->update(['avatar_path' => 'avatars/me.jpg']);
    tp908StoragePut('local', 'submissions/work.pdf');
    tp908StoragePut('local', 'instructor-applications/id.pdf');
    tp908StoragePut('local', 'avatars/me.jpg');
    tp908StoragePut('public', 'avatars/me.jpg');
    tp908StorageFlaky('local', ['instructor-applications/id.pdf' => 'throw']);

    $report = app(AnonymizeAccount::class)($admin, $learner);

    expect($report)->toHaveKey('account', 1)
        ->and(DB::table('users')->where('id', $learner->id)->value('status'))->toBe('anonymized')
        ->and(DB::table('submission_files')->value('path'))->toBe('')
        ->and(Storage::disk('local')->exists('submissions/work.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('avatars/me.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('avatars/me.jpg'))->toBeFalse()
        ->and(Storage::disk('local')->exists('instructor-applications/id.pdf'))->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), 'Adapter unavailable'));
})->group('T-P9-08', 'A1');

it('reports a false return as deferred, and still deletes the other private and public copies', function (): void {
    Exceptions::fake();
    $learner = tp908StorageStudent();
    $admin = tp908StorageAdmin();
    $assignmentId = tp908StorageAssignmentId();
    tp908StorageAttachSubmission($learner, $assignmentId, ['submissions/stuck.pdf', 'submissions/work.pdf']);
    DB::table('users')->where('id', $learner->id)->update(['avatar_path' => 'avatars/me.jpg']);
    tp908StoragePut('local', 'submissions/stuck.pdf');
    tp908StoragePut('local', 'submissions/work.pdf');
    tp908StoragePut('local', 'avatars/me.jpg');
    tp908StoragePut('public', 'avatars/me.jpg');
    tp908StorageFlaky('local', ['submissions/stuck.pdf' => 'false']);

    $report = app(AnonymizeAccount::class)($admin, $learner);

    expect($report)->toHaveKey('account', 1)
        ->and(DB::table('users')->where('id', $learner->id)->value('status'))->toBe('anonymized')
        ->and(Storage::disk('local')->exists('submissions/stuck.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->exists('submissions/work.pdf'))->toBeFalse()
        ->and(Storage::disk('public')->exists('avatars/me.jpg'))->toBeFalse();
    Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), 'deferred to reconciliation'));
})->group('T-P9-08', 'A1');

it('leaves failed deletions for the reconciler to find once the account rows no longer reference them', function (): void {
    Exceptions::fake();
    $learner = tp908StorageStudent();
    $admin = tp908StorageAdmin();
    $assignmentId = tp908StorageAssignmentId();
    tp908StorageAttachSubmission($learner, $assignmentId, ['submissions/stuck.pdf']);
    tp908StorageApplication($learner, 'instructor-applications/stuck-id.pdf');
    DB::table('users')->where('id', $learner->id)->update(['avatar_path' => 'avatars/stuck.jpg']);
    tp908StoragePut('local', 'submissions/stuck.pdf');
    tp908StoragePut('local', 'instructor-applications/stuck-id.pdf');
    tp908StoragePut('local', 'avatars/stuck.jpg');
    $flaky = tp908StorageFlaky('local', [
        'submissions/stuck.pdf' => 'false',
        'instructor-applications/stuck-id.pdf' => 'throw',
        'avatars/stuck.jpg' => 'throw',
    ]);

    app(AnonymizeAccount::class)($admin, $learner);

    $dry = app(ReconcileOrphanedUploads::class)(null, false, 0);
    expect($dry['submission_files'])->toBe(1)
        ->and($dry['instructor_applications'])->toBe(1)
        ->and($dry['avatars_private'])->toBe(1)
        ->and($dry['avatars_public'])->toBe(0)
        ->and(Storage::disk('local')->exists('submissions/stuck.pdf'))->toBeTrue();

    $flaky->failures = [];
    $applied = app(ReconcileOrphanedUploads::class)(null, true, 0);
    expect(array_sum($applied))->toBe(3)
        ->and(Storage::disk('local')->exists('submissions/stuck.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('instructor-applications/stuck-id.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('avatars/stuck.jpg'))->toBeFalse();
})->group('T-P9-08', 'A1', 'A2');

// Regression for BUG-P9-08-01: the media disk is resolved inside deleteFiles(), so a misconfigured disk is reported after the commit.
it('does not fail the caller when the media disk cannot be resolved after commit', function (): void {
    Exceptions::fake();
    $learner = tp908StorageStudent();
    $admin = tp908StorageAdmin();
    Config::set('media.disk', 'tp908-missing-disk');

    $report = app(AnonymizeAccount::class)($admin, $learner);

    expect($report)->toHaveKey('account', 1)
        ->and(DB::table('users')->where('id', $learner->id)->value('status'))->toBe('anonymized');
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'tp908-missing-disk'));
})->group('T-P9-08', 'A1');

// --- A2: ReconcileOrphanedUploads and privacy:reconcile-storage ---

it('uses a 60 minute orphan grace and registers the storage reconciliation at 04:00 with --apply', function (): void {
    expect(Config::integer('privacy.orphan_grace_minutes'))->toBe(60);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'privacy:reconcile-storage'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->expression)->toBe('0 4 * * *')
        ->and($event->command)->toContain('--apply')
        ->and($event->description)->toBe('privacy:reconcile-storage');
})->group('T-P9-08', 'A2');

it('reports stale orphans in dry run and deletes nothing', function (): void {
    tp908StorageFixture();

    $this->artisan('privacy:reconcile-storage')
        ->expectsOutputToContain('Found 4 orphaned upload(s) (dry-run):')
        ->expectsOutputToContain(' - instructor_applications: 1')
        ->assertExitCode(0);

    Storage::disk('local')->assertExists('submissions/orphan-old.pdf');
    Storage::disk('local')->assertExists('avatars/orphan-old.jpg');
    Storage::disk('public')->assertExists('avatars/orphan-old.jpg');
    Storage::disk('local')->assertExists('submissions/fresh-orphan.pdf');
})->group('T-P9-08', 'A2');

it('with --apply deletes only stale unreferenced files and keeps referenced, fresh, ignored and placeholder files', function (): void {
    tp908StorageFixture();

    $this->artisan('privacy:reconcile-storage', ['--apply' => true])
        ->expectsOutputToContain('Deleted 4 orphaned upload(s) (apply):')
        ->assertExitCode(0);

    Storage::disk('local')->assertMissing('submissions/orphan-old.pdf');
    Storage::disk('local')->assertMissing('instructor-applications/orphan-old.pdf');
    Storage::disk('local')->assertMissing('avatars/orphan-old.jpg');
    Storage::disk('public')->assertMissing('avatars/orphan-old.jpg');

    Storage::disk('local')->assertExists('submissions/keep-work.pdf');
    Storage::disk('local')->assertExists('instructor-applications/keep-id.pdf');
    Storage::disk('local')->assertExists('avatars/keep.jpg');
    Storage::disk('public')->assertExists('avatars/keep.jpg');
    Storage::disk('local')->assertExists('submissions/fresh-orphan.pdf');
    Storage::disk('local')->assertExists('submissions/.gitignore');
    Storage::disk('local')->assertExists('submissions/.DS_Store');
    Storage::disk('local')->assertExists('privacy/staging-placeholder.txt');
})->group('T-P9-08', 'A2');

it('keeps a fresh orphan under the default grace and deletes it once the grace window has passed', function (): void {
    tp908StorageFixture();

    $this->artisan('privacy:reconcile-storage', ['--apply' => true])->assertExitCode(0);
    Storage::disk('local')->assertExists('submissions/fresh-orphan.pdf');

    $this->travel(61)->minutes();
    $this->artisan('privacy:reconcile-storage', ['--apply' => true])
        ->expectsOutputToContain('Deleted 1 orphaned upload(s) (apply):')
        ->assertExitCode(0);
    Storage::disk('local')->assertMissing('submissions/fresh-orphan.pdf');
    Storage::disk('local')->assertExists('submissions/keep-work.pdf');
})->group('T-P9-08', 'A2');

it('with --grace=0 treats fresh unreferenced files as orphans, still keeping referenced ones', function (): void {
    tp908StorageFixture();

    $this->artisan('privacy:reconcile-storage', ['--grace' => '0'])
        ->expectsOutputToContain('Found 5 orphaned upload(s) (dry-run):')
        ->assertExitCode(0);
    Storage::disk('local')->assertExists('submissions/fresh-orphan.pdf');

    $this->artisan('privacy:reconcile-storage', ['--apply' => true, '--grace' => '0'])
        ->expectsOutputToContain('Deleted 5 orphaned upload(s) (apply):')
        ->assertExitCode(0);
    Storage::disk('local')->assertMissing('submissions/fresh-orphan.pdf');
    Storage::disk('local')->assertExists('submissions/keep-work.pdf');
    Storage::disk('local')->assertExists('instructor-applications/keep-id.pdf');
})->group('T-P9-08', 'A2');

it('rejects a negative or non-numeric --grace with exit code 2 and deletes nothing', function (): void {
    tp908StorageFixture();

    $this->artisan('privacy:reconcile-storage', ['--apply' => true, '--grace' => '-1'])
        ->expectsOutputToContain('non-negative integer')
        ->assertExitCode(2);

    $this->artisan('privacy:reconcile-storage', ['--apply' => true, '--grace' => 'soon'])
        ->assertExitCode(2);

    Storage::disk('local')->assertExists('submissions/orphan-old.pdf');
    Storage::disk('public')->assertExists('avatars/orphan-old.jpg');
})->group('T-P9-08', 'A2');

it('touches only the upload disks and no protected tables when it applies', function (): void {
    tp908StorageFixture();
    $before = [
        'users' => DB::table('users')->count(),
        'submission_files' => DB::table('submission_files')->count(),
        'instructor_applications' => DB::table('instructor_applications')->count(),
        'activity_log' => DB::table('activity_log')->count(),
    ];

    $this->artisan('privacy:reconcile-storage', ['--apply' => true])->assertExitCode(0);

    expect([
        'users' => DB::table('users')->count(),
        'submission_files' => DB::table('submission_files')->count(),
        'instructor_applications' => DB::table('instructor_applications')->count(),
        'activity_log' => DB::table('activity_log')->count(),
    ])->toBe($before)
        ->and(array_intersect(['submission_files', 'instructor_applications', 'users', 'avatars'], ProtectedData::TABLES))->toBe([]);
})->group('T-P9-08', 'A2');

it('writes one retention audit row when an actor applies deletions, and none on a dry run', function (): void {
    $admin = tp908StorageAdmin();
    tp908StorageFixture();

    app(ReconcileOrphanedUploads::class)($admin, false, 60);
    expect(Activity::query()->where('event', 'privacy.retention_pruned')->count())->toBe(0);

    app(ReconcileOrphanedUploads::class)($admin, true, 60);
    expect(Activity::query()->where('event', 'privacy.retention_pruned')->count())->toBe(1);
})->group('T-P9-08', 'A2');
