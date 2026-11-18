<?php

// T-P9-10 | YG-GAP-OPS-001, YG-GAP-OPS-002, ED-027 | A1 A2

declare(strict_types=1);

use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Support\Actions\StartViewAs;
use App\Domain\Support\Learner360Mask;
use App\Domain\Support\Queries\Learner360Record;
use App\Domain\Support\Queries\LearnerDirectory;
use App\Domain\Support\ViewAsRefusal;
use App\Domain\Support\ViewAsRefusedException;
use App\Filament\Pages\LearnerThreeSixtyPage;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function tp910Staff(string $role): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole($role);
}

function tp910Learner(): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
}

function tp910Reason(): string
{
    return 'Learner asked for help with the lesson access.';
}

function tp910Events(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

function tp910Refusal(User $staff, User $learner): ?string
{
    try {
        app(StartViewAs::class)($staff, $learner, tp910Reason());
    } catch (ViewAsRefusedException $e) {
        return $e->refusal->value;
    }

    return null;
}

// --- Start, reason and refusals ---------------------------------------------------------------

it('starts a session with a trimmed reason, a 20 minute expiry and one audit row, and the staff member stays signed in as themselves', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $this->actingAs($support);

    $session = app(StartViewAs::class)($support, $learner, '   '.tp910Reason().'   ');

    expect($session->reason)->toBe(tp910Reason())
        ->and((int) Carbon::parse($session->expires_at)->diffInMinutes($session->started_at, true))->toBe(20)
        ->and(auth()->id())->toBe($support->id)
        ->and(tp910Events('support.view_as_started'))->toBe(1);
})->group('T-P9-10', 'A1');

it('refuses a reason shorter than 10 or longer than 500 characters and writes nothing', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();

    foreach (['too short', str_repeat('x', 501)] as $reason) {
        expect(fn () => app(StartViewAs::class)($support, $learner, $reason))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('reason'));
    }

    expect(ViewAsSession::query()->count())->toBe(0);
})->group('T-P9-10', 'A1');

it('refuses view-as for an account without the permission and for the staff member themselves', function (): void {
    $student = tp910Learner();
    expect(tp910Refusal($student, tp910Learner()))->toBe(ViewAsRefusal::Permission->value);

    $instructor = tp910Staff('instructor_academy');
    expect(tp910Refusal($instructor, tp910Learner()))->toBe(ViewAsRefusal::Permission->value);

    $support = tp910Staff('support');
    expect(tp910Refusal($support, $support))->toBe(ViewAsRefusal::OwnAccount->value);

    expect(tp910Events('support.view_as_refused'))->toBeGreaterThanOrEqual(3)
        ->and(ViewAsSession::query()->count())->toBe(0);
})->group('T-P9-10', 'A1');

it('refuses staff targets, parent targets, suspended and anonymized accounts', function (): void {
    $support = tp910Staff('support');

    expect(tp910Refusal($support, tp910Staff('admin')))->toBe(ViewAsRefusal::StaffAccount->value)
        ->and(tp910Refusal($support, tp910Staff('instructor_academy')))->toBe(ViewAsRefusal::StaffAccount->value);

    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    expect(tp910Refusal($support, $parent))->toBe(ViewAsRefusal::NotLearner->value);

    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('student');
    expect(tp910Refusal($support, $suspended))->toBe(ViewAsRefusal::Inactive->value);

    $anonymized = User::factory()->create(['status' => 'active', 'anonymized_at' => now()])->assignRole('student');
    expect(tp910Refusal($support, $anonymized))->toBe(ViewAsRefusal::Anonymized->value);
})->group('T-P9-10', 'A1');

it('refuses while a deletion request is requested or approved, and allows it once cancelled', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $request = DB::table('data_requests')->insertGetId([
        'subject_id' => $learner->id, 'requested_by' => $learner->id, 'type' => 'deletion', 'status' => 'requested',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(tp910Refusal($support, $learner))->toBe(ViewAsRefusal::PrivacyRequest->value);

    DB::table('data_requests')->where('id', $request)->update(['status' => 'cancelled']);
    expect(tp910Refusal($support, $learner))->toBeNull();
})->group('T-P9-10', 'A1');

it('refuses a second start while one session is open, and an expired unclosed session does not block', function (): void {
    $support = tp910Staff('support');
    $first = app(StartViewAs::class)($support, tp910Learner(), tp910Reason());

    expect(tp910Refusal($support, tp910Learner()))->toBe(ViewAsRefusal::AlreadyActive->value);

    Carbon::setTestNow(now()->addMinutes(25));
    $second = app(StartViewAs::class)($support, tp910Learner(), tp910Reason());

    expect($first->refresh()->end_cause)->toBe(ViewAsSession::END_EXPIRED)
        ->and($second->ended_at)->toBeNull();
})->group('T-P9-10', 'A1');

// --- Time limit and write block ---------------------------------------------------------------

it('returns to normal after the time limit, closes the session as expired and audits the stop once', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $this->actingAs($support);
    $session = app(StartViewAs::class)($support, $learner, tp910Reason());

    $this->delete('/guardian/context')->assertForbidden();

    Carbon::setTestNow($session->expires_at->copy()->addMinute());

    $this->get('/support/view-as')->assertForbidden();
    $this->get('/support/view-as')->assertForbidden();

    $session->refresh();
    expect($session->ended_at)->not->toBeNull()
        ->and($session->end_cause)->toBe(ViewAsSession::END_EXPIRED)
        ->and(tp910Events('support.view_as_stopped'))->toBe(1);
})->group('T-P9-10', 'A1');

it('blocks writes during view-as, lets GET through, and lets only the stop route pass', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $this->actingAs($support);
    app(StartViewAs::class)($support, $learner, tp910Reason());

    $this->delete('/guardian/context')->assertForbidden();
    $this->patch(route('profile.update'), ['name' => 'Changed Name'])->assertForbidden();
    expect(DB::table('users')->where('id', $learner->id)->value('name'))->toBe($learner->name);

    $this->get('/support/view-as')->assertOk();

    $this->post(route('support.view-as.stop'))->assertRedirect();

    expect(ViewAsSession::query()->whereNull('ended_at')->count())->toBe(0)
        ->and(tp910Events('support.view_as_stopped'))->toBe(1);
    expect(ViewAsSession::query()->latest('id')->first()->end_cause)->toBe(ViewAsSession::END_MANUAL);

    $this->delete('/guardian/context')->assertNoContent();
})->group('T-P9-10', 'A1');

it('records one page view per GET of the view-as home and course pages', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $this->actingAs($support);
    $session = app(StartViewAs::class)($support, $learner, tp910Reason());

    $this->get('/support/view-as')->assertOk();
    $this->get('/support/view-as')->assertOk();

    expect(tp910Events('support.view_as_page_viewed'))->toBe(2)
        ->and($session->refresh()->page_views)->toBe(2);
})->group('T-P9-10', 'A1');

// --- Learner 360 gating and masking ------------------------------------------------------------

it('lets only admin, super admin and support open the Learner 360 page', function (): void {
    foreach (['admin', 'super_admin', 'support'] as $role) {
        $this->actingAs(tp910Staff($role));
        expect(LearnerThreeSixtyPage::canAccess())->toBeTrue();
    }
    foreach (['student', 'instructor_academy', 'finance', 'marketing', 'content_manager'] as $role) {
        $this->actingAs(tp910Staff($role));
        expect(LearnerThreeSixtyPage::canAccess())->toBeFalse();
    }
})->group('T-P9-10', 'A1');

it('finds a learner by exact email or id only after two characters and never lists staff', function (): void {
    $support = tp910Staff('support');
    // Ids of one character are below the two-character minimum, so the learner gets a longer explicit id.
    $learner = User::factory()->create(['id' => 8100100, 'status' => 'active', 'email' => 'jo.learner@example.test', 'username' => 'jo_learner'])->assignRole('student');
    tp910Staff('admin');

    $directory = app(LearnerDirectory::class);
    expect(array_column($directory->search($support, 'x'), 'id'))->toBe([])
        ->and(array_column($directory->search($support, 'jo.learner@example.test'), 'id'))->toContain($learner->id)
        ->and(array_column($directory->search($support, (string) $learner->id), 'id'))->toContain($learner->id);
})->group('T-P9-10', 'A1');

it('masks email and phone for support and shows admin the full values, with the section keys each role may see', function (): void {
    expect(Learner360Mask::email('jordan@example.com'))->toBe('j***@e***.com')
        ->and(Learner360Mask::phone('+201001234123'))->toEndWith('123')
        ->and(Learner360Mask::phone('+201001234123'))->toContain('••••');

    $support = tp910Staff('support');
    $admin = tp910Staff('admin');
    $learner = User::factory()->create(['status' => 'active', 'email' => 'jordan.real@example.com', 'phone' => '+201001234123'])->assignRole('student');

    $forSupport = app(Learner360Record::class)->for($support, $learner);
    $forAdmin = app(Learner360Record::class)->for($admin, $learner);

    expect($forSupport['profile']['email'])->not->toBe('jordan.real@example.com')
        ->and($forSupport['profile']['masked'])->toBeTrue()
        ->and($forAdmin['profile']['email'])->toBe('jordan.real@example.com')
        ->and(array_key_exists('privacy', $forSupport) ? $forSupport['privacy'] : null)->toBeNull()
        ->and(array_key_exists('audit', $forSupport) ? $forSupport['audit'] : null)->toBeNull()
        ->and($forAdmin['privacy'])->not->toBeNull()
        ->and($forAdmin['audit'])->not->toBeNull()
        ->and($forAdmin['devices'])->not->toBeNull();
})->group('T-P9-10', 'A1');

it('never returns private learner text, the provider id or payout data in the record array', function (): void {
    $support = tp910Staff('support');
    $learner = tp910Learner();
    $marker = 'PRIVATE_T910_QUESTION_MARKER';
    $course = Course::query()->create(['title' => ['en' => 'T910 course'], 'slug_ar' => 't910-c', 'slug_en' => 't910-c', 'status' => 'published', 'access_mode' => 'free']);
    $lesson = Lesson::query()->create(['title' => ['en' => 'T910 lesson']]);
    $conversation = AiConversation::query()->create([
        'student_id' => $learner->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
    ]);
    $message = AiMessage::query()->create(['conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'answer']);
    DB::table('help_requests')->insert([
        'student_id' => $learner->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'locale' => 'en',
        'conversation_id' => $conversation->id, 'ai_message_id' => $message->id, 'document_version' => 1,
        'question' => $marker, 'ai_answer' => 'PRIVATE_T910_ANSWER', 'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $json = json_encode(app(Learner360Record::class)->for($support, $learner));

    expect($json)->not->toContain($marker)
        ->and($json)->not->toContain('PRIVATE_T910_ANSWER')
        ->and($json)->not->toContain('request_hash')
        ->and($json)->not->toContain('provider_id');
})->group('T-P9-10', 'A1');

it('refuses view-as for a child account for support, but allows admin with sensitive learner access', function (): void {
    $child = User::factory()->create(['status' => 'active', 'email' => null])->assignRole('student');

    expect(tp910Refusal(tp910Staff('support'), $child))->toBe(ViewAsRefusal::MinorRestricted->value);

    $admin = tp910Staff('admin');
    $session = app(StartViewAs::class)($admin, $child, tp910Reason());
    expect($session->learner_id)->toBe($child->id);
})->group('T-P9-10', 'A2');
