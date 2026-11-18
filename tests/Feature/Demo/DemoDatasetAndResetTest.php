<?php

// T-P9-11 | YG-GAP-OPS-003, ED-022 | A1 A2

declare(strict_types=1);

use App\Domain\Demo\Actions\ResetDemoData;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoRefused;
use App\Domain\Entitlements\Actions\EnrollInFreeCourse;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

/** Row counts of the tables a demo reset must empty (demo_anchors included). */
function tp911Counts(): array
{
    $tables = ['users', 'courses', 'orders', 'order_items', 'cohorts', 'quizzes', 'lessons', 'curriculum_items', 'entitlements',
        'certificates', 'instructor_ledger_entries', 'help_requests', 'ai_conversations', 'notifications', 'model_has_roles', 'demo_anchors'];
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    return $out;
}

function tp911RealLearnerWithCourse(): array
{
    $course = Course::query()->create([
        'title' => ['en' => 'Real course'], 'slug_ar' => 'tp911-real-ar', 'slug_en' => 'tp911-real', 'status' => 'published', 'access_mode' => 'free',
    ]);
    $owner = User::factory()->create(['status' => 'active', 'email' => 'real.owner@example.test'])->assignRole('admin');

    return [$course, $owner, 0];
}

function tp911Seed(): void
{
    test()->artisan('demo:seed')->assertExitCode(0);
}

it('refuses demo:seed and demo:reset in production even when the config lists production', function (): void {
    $this->app['env'] = 'production';
    $before = tp911Counts();

    test()->artisan('demo:seed')->assertExitCode(1);
    test()->artisan('demo:reset')->assertExitCode(1);

    config(['demo.environments' => ['production', 'local']]);
    test()->artisan('demo:seed')->assertExitCode(1);

    expect(tp911Counts())->toBe($before);
})->group('T-P9-11', 'A1');

it('refuses an environment that is not allow-listed, such as qa, and creates nothing', function (): void {
    $this->app['env'] = 'qa';
    $before = tp911Counts();

    test()->artisan('demo:seed')->assertExitCode(1);
    test()->artisan('demo:reset')->assertExitCode(1);

    expect(tp911Counts())->toBe($before);
})->group('T-P9-11', 'A1');

it('creates the demo dataset with the documented counts, demo emails and the public demo password', function (): void {
    tp911Seed();

    // Two child accounts have no email (minors), so the ten accounts are counted through the anchors.
    $demoUsers = User::query()->where('email', 'like', '%@demo.younggo.test')->get();
    expect(DemoAnchors::ids('users'))->toHaveCount(10)
        ->and($demoUsers)->toHaveCount(8)
        ->and(DB::table('courses')->count())->toBe(8)
        ->and(DB::table('orders')->count())->toBe(8)
        ->and(DB::table('orders')->where('status', 'paid')->count())->toBe(5)
        ->and(DB::table('orders')->where('status', 'pending')->count())->toBe(1)
        ->and(DB::table('orders')->where('status', 'cancelled')->count())->toBe(1)
        ->and(DB::table('orders')->where('status', 'failed')->count())->toBe(1)
        ->and(DB::table('coupons')->count())->toBe(2)
        ->and(DB::table('help_requests')->count())->toBe(1)
        ->and(DB::table('certificates')->count())->toBeGreaterThanOrEqual(1)
        ->and(DemoAnchors::ids('users'))->toHaveCount(10)
        ->and(DB::table('demo_anchors')->count())->toBe(22);

    foreach ($demoUsers as $user) {
        expect(str_ends_with((string) $user->email, '@demo.younggo.test'))->toBeTrue()
            ->and(Hash::check((string) config('demo.password'), $user->password))->toBeTrue();
    }
})->group('T-P9-11', 'A1');

it('refuses a second demo:seed without creating anything', function (): void {
    tp911Seed();
    $after = tp911Counts();

    test()->artisan('demo:seed')->assertExitCode(1);

    expect(tp911Counts())->toBe($after);
})->group('T-P9-11', 'A1');

it('links each child account to the parent with a verified link and gives the two children different progress', function (): void {
    tp911Seed();

    $verified = DB::table('guardian_child_links')->where('status', 'verified')->count();
    expect($verified)->toBeGreaterThanOrEqual(2);

    $childIds = DB::table('guardian_child_links')->where('status', 'verified')->pluck('child_id')->unique()->values();
    expect($childIds->count())->toBeGreaterThanOrEqual(2);

    $progress = DB::table('course_progress')->whereIn('learner_id', $childIds)
        ->select('learner_id', DB::raw('count(*) as rows_count'), DB::raw('sum(percent) as total_percent'))
        ->groupBy('learner_id')->get()->map(fn ($row) => [(int) $row->learner_id, (int) $row->rows_count, (int) $row->total_percent])->all();
    expect(count(array_unique(array_map(static fn (array $row): string => json_encode([$row[1], $row[2]]), $progress))))->toBeGreaterThan(1);
})->group('T-P9-11', 'A1');

it('makes no real side effects: no payment attempts, no outbound HTTP, mail goes only to .test addresses', function (): void {
    Http::fake();
    tp911Seed();

    expect(DB::table('payment_attempts')->count())->toBe(0);
    Http::assertNothingSent();

    $addresses = DB::table('notifications')->get()->map(fn ($row) => (string) $row->data)->implode(' ');
    expect($addresses)->not->toContain('@gmail.')
        ->and(User::query()->whereNotNull('email')->where('email', 'not like', '%.test')->where('email', 'not like', '%.younggo.test')->where('id', '>', 0)->count())
        ->toBe(0);
})->group('T-P9-11', 'A1');

it('removes every demo row with --remove-only, back to the baseline counts, and keeps reference data', function (): void {
    $baseline = tp911Counts();
    tp911Seed();

    test()->artisan('demo:reset', ['--remove-only' => true])->assertExitCode(0);

    expect(tp911Counts())->toBe($baseline + ['demo_anchors' => 0]);
    expect(DB::table('roles')->count())->toBeGreaterThan(0)
        ->and(DB::table('permissions')->count())->toBeGreaterThan(0);
})->group('T-P9-11', 'A1');

it('rebuilds the same counts on demo:reset and is repeatable three times', function (): void {
    tp911Seed();
    $fresh = tp911Counts();

    for ($i = 0; $i < 3; $i++) {
        test()->artisan('demo:reset')->assertExitCode(0);
        expect(tp911Counts())->toBe($fresh);
    }
})->group('T-P9-11', 'A1');

it('never touches a real user, course, order or coupon when it resets the demo data', function (): void {
    [$course, $owner, $coupon] = tp911RealLearnerWithCourse();
    $order = DB::table('orders')->insertGetId([
        'reference' => 'REAL-911', 'payer_id' => $owner->id, 'market' => 'EG', 'currency' => 'EGP', 'subtotal_minor' => 1000, 'total_minor' => 1000,
        'status' => 'paid', 'paid_at' => now(), 'idempotency_key' => 'REAL-911', 'request_hash' => hash('sha256', 'REAL-911'),
    ]);
    tp911Seed();

    test()->artisan('demo:reset')->assertExitCode(0);

    expect(User::query()->find($owner->id))->not->toBeNull()
        ->and(Course::query()->find($course->id)?->slug_en)->toBe('tp911-real')
        ->and(DB::table('orders')->where('id', $order)->value('reference'))->toBe('REAL-911');
})->group('T-P9-11', 'A1');

it('stops the reset when a real learner holds access to a demo free course, deletes nothing and keeps the access', function (): void {
    tp911Seed();
    $demoCourseId = (int) DemoAnchors::ids('courses')[0];
    $demoCourse = Course::query()->findOrFail($demoCourseId);
    $real = User::factory()->create(['status' => 'active', 'email' => 'real.learner@example.test'])->assignRole('student');
    app(EnrollInFreeCourse::class)($real, $real, $demoCourse);
    $before = tp911Counts();

    test()->artisan('demo:reset')->assertExitCode(1);

    expect(tp911Counts())->toBe($before)
        ->and(DB::table('entitlements')->where('learner_id', $real->id)->where('course_id', $demoCourseId)->exists())->toBeTrue();
})->group('T-P9-11', 'A2');

it('stops the reset when a real row references a demo user through a restricting foreign key, and deletes nothing', function (): void {
    tp911Seed();
    $demoInstructorId = (int) DB::table('users')->where('email', 'like', '%@demo.younggo.test')->orderBy('id')->value('id');
    $real = User::factory()->create(['status' => 'active', 'email' => 'real.student@example.test'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['en' => 'Real help course'], 'slug_ar' => 'tp911-help-ar', 'slug_en' => 'tp911-help', 'status' => 'published', 'access_mode' => 'free',
    ]);
    $lesson = Lesson::query()->create(['title' => ['en' => 'Real help lesson']]);
    $conversation = AiConversation::query()->create([
        'student_id' => $real->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
    ]);
    $message = AiMessage::query()->create(['conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'x']);
    DB::table('help_requests')->insert([
        'student_id' => $real->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'locale' => 'en',
        'conversation_id' => $conversation->id, 'ai_message_id' => $message->id, 'document_version' => 1,
        'question' => 'Real question', 'ai_answer' => 'Real answer', 'status' => 'new',
        'assigned_instructor_id' => $demoInstructorId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $before = tp911Counts();

    expect(fn () => app(ResetDemoData::class)())->toThrow(DemoRefused::class);

    expect(tp911Counts())->toBe($before);
})->group('T-P9-11', 'A2');

it('keeps the seed audit rows after a reset by design', function (): void {
    tp911Seed();
    $audit = DB::table('activity_log')->count();

    test()->artisan('demo:reset', ['--remove-only' => true])->assertExitCode(0);

    expect(DB::table('activity_log')->count())->toBeGreaterThanOrEqual($audit);
})->group('T-P9-11', 'A2');
