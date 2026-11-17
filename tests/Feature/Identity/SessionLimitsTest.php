<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Actions\ResetUserDevices;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\SessionLimiter;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Watermark;
use App\Filament\Resources\LearnerDevices\Pages\ListLearnerDevices;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config(['security.sessions.enabled' => true, 'security.sessions.max_concurrent' => 2, 'security.sessions.max_devices' => 3, 'security.sessions.touch_seconds' => 0]);
});

function slCheck(User $user, string $session, string $device = 'device-a'): string
{
    return app(SessionLimiter::class)->check($user, $session, $device, '203.0.113.5', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36');
}

it('signs the oldest session out when a third one starts', function (): void {
    $user = User::factory()->create()->assignRole('student');

    expect(slCheck($user, 'session-1'))->toBe('ok');
    $this->travelTo(now()->addMinute());
    expect(slCheck($user, 'session-2'))->toBe('ok');
    $this->travelTo(now()->addMinute());
    expect(slCheck($user, 'session-3'))->toBe('ok'); // overflow: session-1 is the oldest

    expect(slCheck($user, 'session-1'))->toBe('signed_out')->and(slCheck($user, 'session-2'))->toBe('ok')->and(slCheck($user, 'session-3'))->toBe('ok');
    $oldest = UserSession::query()->where('session_hash', hash('sha256', 'session-1'))->first();
    expect($oldest->signed_out_reason)->toBe('limit')->and(UserSession::query()->whereNull('signed_out_at')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'security.session_limit_signout')->count())->toBe(1);
})->group('T-P4-07', 'A1', 'YG-GAP-SEC-001');

it('does not count idle sessions or touch staff and keeps one session stable', function (): void {
    $user = User::factory()->create()->assignRole('student');
    slCheck($user, 'old');
    $this->travelTo(now()->addMinutes(200)); // far past the idle window
    slCheck($user, 'new-1');
    slCheck($user, 'new-2');

    expect(UserSession::query()->whereNull('signed_out_at')->count())->toBe(3)->and(slCheck($user, 'old'))->toBe('ok'); // idle, never signed out, simply not counted

    expect(slCheck($user, 'new-1'))->toBe('ok')->and(slCheck($user, 'new-1'))->toBe('ok')->and(UserSession::query()->where('session_hash', hash('sha256', 'new-1'))->count())->toBe(1);

    $staff = User::factory()->create()->assignRole('instructor_academy');
    expect(app(SessionLimiter::class)->applies($staff))->toBeFalse()->and(app(SessionLimiter::class)->applies($user))->toBeTrue()
        ->and(app(SessionLimiter::class)->applies(User::factory()->create()->assignRole('parent')))->toBeTrue();
    config(['security.sessions.enabled' => false]);
    expect(app(SessionLimiter::class)->applies($user))->toBeFalse();
})->group('T-P4-07', 'A1');

it('refuses a new device over the limit and lets known devices back in', function (): void {
    $user = User::factory()->create()->assignRole('student');
    foreach (['d1', 'd2', 'd3'] as $i => $device) {
        expect(slCheck($user, "s{$i}", $device))->toBe('ok');
    }

    expect(slCheck($user, 'fresh', 'd4'))->toBe('device_limit')->and(UserDevice::query()->where('user_id', $user->id)->count())->toBe(3)
        ->and(slCheck($user, 'back', 'd2'))->toBe('ok')->and(Activity::query()->where('event', 'security.device_refused')->count())->toBe(1)
        ->and(UserDevice::query()->first()->label)->toBe('Windows · Chrome');
    expect(UserDevice::query()->pluck('token_hash')->contains('d1'))->toBeFalse(); // only hashes are stored
})->group('T-P4-07', 'A1');

it('lets an admin reset devices, signs the learner out everywhere and audits it', function (): void {
    $learner = User::factory()->create()->assignRole('student');
    $admin = User::factory()->create()->assignRole('admin');
    foreach (['d1', 'd2', 'd3'] as $i => $device) {
        slCheck($learner, "s{$i}", $device);
    }
    expect(slCheck($learner, 'new', 'd4'))->toBe('device_limit');

    expect(app(ResetUserDevices::class)($admin, $learner))->toBe(3)->and(UserDevice::query()->count())->toBe(0)
        ->and(UserSession::query()->whereNull('signed_out_at')->count())->toBe(0)->and(UserSession::query()->where('signed_out_reason', 'reset')->exists())->toBeTrue()
        ->and(slCheck($learner, 's0'))->toBe('signed_out') // the old browser session must sign in again
        ->and(slCheck($learner, 'brand-new', 'd4'))->toBe('ok')
        ->and(Activity::query()->where('event', 'security.devices_reset')->count())->toBe(1);

    expect(fn () => app(ResetUserDevices::class)($learner, $learner))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResetUserDevices::class)(User::factory()->create()->assignRole('instructor_academy'), $learner))->toThrow(AuthorizationException::class);
})->group('T-P4-07', 'A2');

it('offers the reset in the learner devices screen for admins only', function (): void {
    $learner = User::factory()->create(['name' => 'Device Kid'])->assignRole('student');
    slCheck($learner, 's1', 'd1');
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->get('/admin/learner-devices')->assertOk()->assertSee('Device Kid');
    Livewire::test(ListLearnerDevices::class)->assertCanSeeTableRecords([$learner])->callTableAction('reset', $learner)->assertNotified();
    expect(UserDevice::query()->count())->toBe(0);

    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/admin/learner-devices')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('instructor_academy'))->get('/instructor/learner-devices')->assertNotFound();
})->group('T-P4-07', 'A2');

it('tracks sessions through the middleware, sets the device cookie and signs out an ended session', function (): void {
    $user = User::factory()->create()->assignRole('student');

    $sessionCookie = config('session.cookie');
    $sameBrowser = str_repeat('a', 40); // one browser session id for every request below
    $first = $this->actingAs($user)->withCookie($sessionCookie, $sameBrowser)->get('/my')->assertOk();
    $first->assertCookie('yg_device');
    expect(UserSession::query()->where('user_id', $user->id)->count())->toBe(1)->and(UserDevice::query()->where('user_id', $user->id)->count())->toBe(1);

    $encrypted = collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === 'yg_device')->getValue();
    $token = CookieValuePrefix::remove(app('encrypter')->decrypt($encrypted, false));
    $this->withCookie($sessionCookie, $sameBrowser)->withCookie('yg_device', $token)->get('/my')->assertOk();
    expect(UserDevice::query()->count())->toBe(1)->and(UserSession::query()->count())->toBe(1);

    UserSession::query()->update(['signed_out_at' => now(), 'signed_out_reason' => 'limit']);
    $this->withCookie($sessionCookie, $sameBrowser)->withCookie('yg_device', $token)->get('/my')->assertRedirect('/login')->assertSessionHas('session_notice', __('auth.signed_out_elsewhere'));

    $this->withSession(['session_notice' => 'Notice text here'])->get('/login')->assertOk()->assertSee('Notice text here')->assertSee('data-session-notice', false);
})->group('T-P4-07', 'A1');

it('refuses a fourth device at the door with a clear message', function (): void {
    $user = User::factory()->create()->assignRole('student');
    foreach (['d1', 'd2', 'd3'] as $i => $device) {
        slCheck($user, "s{$i}", $device);
    }

    $this->actingAs($user)->get('/my')->assertRedirect('/login')->assertSessionHas('session_notice', __('auth.device_limit'));
    expect(UserDevice::query()->count())->toBe(3);
})->group('T-P4-07', 'A1');

it('ends the stored session on sign-out', function (): void {
    $user = User::factory()->create()->assignRole('student');
    $this->actingAs($user)->get('/my')->assertOk();
    $hash = UserSession::query()->firstOrFail()->session_hash;

    app(SessionLimiter::class)->end('unrelated');
    expect(UserSession::query()->where('session_hash', $hash)->whereNull('signed_out_at')->exists())->toBeTrue();
    UserSession::query()->update(['session_hash' => hash('sha256', 'known')]);
    app(SessionLimiter::class)->end('known');
    expect(UserSession::query()->first()->signed_out_reason)->toBe('logout');
    event(new Logout('web', $user)); // must not fail when no session is stored
})->group('T-P4-07', 'A1');

it('builds a masked watermark label and shows it only on protected playback', function (): void {
    $learner = User::factory()->create(['email' => 'sara.ahmed@example.com'])->assignRole('student');
    $label = Watermark::label($learner);

    expect($label)->toStartWith('ID '.str_pad((string) $learner->id, 6, '0', STR_PAD_LEFT))->and($label)->toContain('sa')->and($label)->not->toContain('sara.ahmed@example.com')->and($label)->toContain('@')
        ->and(Watermark::applies('T1'))->toBeTrue()->and(Watermark::applies('T2'))->toBeTrue()->and(Watermark::applies('T3'))->toBeFalse();

    Storage::fake('local');
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'WM'], 'slug_ar' => 'w-'.uniqid(), 'slug_en' => 'w-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    Storage::disk('local')->put('media/clip.mp4', 'v');
    $protected = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Protected'], 'type' => 'video']);
    $open = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Open'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $protected->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    app(AttachLessonMedia::class)($instructor, $open->itemable, ['provider' => 'external_url', 'url' => 'https://cdn.example.com/open.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$protected->id, $open->id], 'published');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $this->actingAs($learner)->get("/learn/{$course->id}/{$protected->id}")->assertOk()->assertSee('data-watermark', false)->assertSee($label);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$open->id}")->assertOk()->assertDontSee('data-watermark', false);
})->group('T-P4-07', 'A3', 'YG-GAP-VID-003');
