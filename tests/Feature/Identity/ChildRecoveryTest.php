<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Actions\IssueLinkCode;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Domain\Identity\Actions\ResetChildPassword;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\GuardianCopyNotification;
use App\Domain\Notifications\LearnerNotificationRouter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

final class ExampleLearnerNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Lesson reminder')->line('Your lesson starts soon.')->action('Open lesson', 'https://example.test/lesson');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['text' => 'Your lesson starts soon.'];
    }
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function crUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'Password1!secure', ...$attributes]);
    $user->assignRole($role);

    return $user;
}

function crChild(User $parent, string $username = 'recover-kid'): User
{
    return app(CreateChildAccount::class)->handle($parent, [
        'username' => $username, 'password' => 'OldChildPass1!', 'name_en' => 'Little Kid', 'name_ar' => 'طفل صغير', 'birth_year' => 2016, 'birth_month' => 3,
    ]);
}

function crAs($test, User $user)
{
    $test->flushSession();

    return $test->actingAs($user);
}

it('refuses to create a child until the parent email is verified', function (): void {
    $parent = crUser('parent', ['email_verified_at' => null]);

    expect(fn () => crChild($parent))->toThrow(ValidationException::class);
    expect(User::query()->where('username', 'recover-kid')->exists())->toBeFalse();

    crAs($this, $parent)->postJson('/guardian/children', [
        'username' => 'recover-kid', 'password' => 'OldChildPass1!', 'password_confirmation' => 'OldChildPass1!',
        'name_en' => 'Kid', 'birth_year' => 2016, 'birth_month' => 3,
    ])->assertUnprocessable()->assertJsonValidationErrors('username');
    $this->get('/family')->assertOk()->assertSee(__('pages.verify_before_child'))->assertDontSee('name="child_password"', false);

    $parent->forceFill(['email_verified_at' => now()])->save();
    expect(crChild($parent->refresh())->username)->toBe('recover-kid');
})->group('T-P1-11', 'A2', 'YG-GAP-FAM-007', 'ED-017');

it('lets a linked parent reset the child password, audited and without leaking it', function (): void {
    $parent = crUser('parent');
    $child = crChild($parent);

    app(ResetChildPassword::class)->handle($parent, $child, 'NewChildPass1!');

    $this->postJson('/login', ['login' => 'recover-kid', 'password' => 'OldChildPass1!'])->assertUnprocessable();
    $this->postJson('/login', ['login' => 'recover-kid', 'password' => 'NewChildPass1!'])->assertNoContent();
    $entry = Activity::query()->where('event', 'guardian.child_password_reset')->sole();
    expect($entry->causer_id)->toBe($parent->id)->and($entry->subject_id)->toBe($child->id)
        ->and((string) json_encode($entry->properties))->not->toContain('NewChildPass1!');
})->group('T-P1-11', 'A3', 'YG-GAP-FAM-007');

it('revokes the child’s existing sessions when the password is reset', function (): void {
    $parent = crUser('parent');
    $child = crChild($parent);

    $this->postJson('/login', ['login' => 'recover-kid', 'password' => 'OldChildPass1!'])->assertNoContent();
    $this->get('/profile')->assertOk();

    app(ResetChildPassword::class)->handle($parent, $child, 'NewChildPass1!');

    $this->app['auth']->forgetGuards();
    $this->getJson('/profile')->assertUnauthorized();
})->group('T-P1-11', 'A3', 'YG-GAP-FAM-007');

it('refuses resets by anyone who is not a linked verified parent', function (): void {
    $parent = crUser('parent');
    $child = crChild($parent);
    $otherParent = crUser('parent');
    $stranger = crUser('student');

    foreach ([$otherParent, $stranger, crUser('super_admin')] as $actor) {
        expect(fn () => app(ResetChildPassword::class)->handle($actor, $child, 'NewChildPass1!'))->toThrow(AuthorizationException::class);
    }
    expect(fn () => app(ResetChildPassword::class)->handle($parent, $stranger, 'NewChildPass1!'))->toThrow(AuthorizationException::class);

    $this->postJson('/login', ['login' => 'recover-kid', 'password' => 'OldChildPass1!'])->assertNoContent();
})->group('T-P1-11', 'A3', 'I02');

it('exposes the reset through an endpoint that needs password confirmation and hides unlinked children', function (): void {
    $parent = crUser('parent');
    $child = crChild($parent);
    $payload = ['child_password' => 'NewChildPass1!', 'child_password_confirmation' => 'NewChildPass1!'];

    crAs($this, $parent)->postJson("/guardian/children/{$child->id}/password", $payload)->assertStatus(423);
    $this->postJson('/confirm-password', ['password' => 'Password1!secure'])->assertNoContent();
    $this->postJson("/guardian/children/{$child->id}/password", ['child_password' => 'short', 'child_password_confirmation' => 'short'])->assertUnprocessable();
    $this->postJson("/guardian/children/{$child->id}/password", $payload)->assertNoContent();

    $other = crUser('parent');
    crAs($this, $other)->withSession(['auth.password_confirmed_at' => time()])
        ->postJson("/guardian/children/{$child->id}/password", $payload)->assertNotFound();
    expect(Gate::forUser($other)->inspect('guardian.child.access', $child)->status())->toBe(404);
})->group('T-P1-11', 'A3', 'I02');

it('sends a child without email an in-app notice and each verified guardian an email copy in their locale', function (): void {
    NotificationFacade::fake();
    $parent = crUser('parent', ['email' => 'mama@example.test', 'locale' => 'ar']);
    $child = crChild($parent);
    $second = crUser('parent', ['email' => 'baba@example.test', 'locale' => 'en']);
    app(RedeemLinkCode::class)->handle($second, app(IssueLinkCode::class)->handle($child));
    GuardianChildLink::query()->where('guardian_id', $second->id)->update(['status' => 'verified']);
    $unverified = crUser('parent', ['email' => 'unverified@example.test', 'email_verified_at' => null]);
    GuardianChildLink::query()->create(['guardian_id' => $unverified->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'link_code']);

    $copied = app(LearnerNotificationRouter::class)->send($child, new ExampleLearnerNotification);

    expect(collect($copied)->pluck('id')->sort()->values()->all())->toBe(collect([$parent->id, $second->id])->sort()->values()->all());
    NotificationFacade::assertSentTo($child, ExampleLearnerNotification::class, fn ($n, array $channels): bool => $channels === ['database']);
    NotificationFacade::assertSentTo($parent, GuardianCopyNotification::class, function (GuardianCopyNotification $n, array $channels) use ($parent): bool {
        $mail = $n->toMail($parent);

        return $channels === ['mail'] && $n->locale === 'ar' && str_contains((string) $mail->subject, 'طفل صغير') && str_contains((string) $mail->subject, 'Lesson reminder');
    });
    NotificationFacade::assertSentTo($second, GuardianCopyNotification::class, fn (GuardianCopyNotification $n): bool => $n->locale === 'en');
    NotificationFacade::assertNotSentTo($unverified, GuardianCopyNotification::class);
    NotificationFacade::assertNotSentTo($child, GuardianCopyNotification::class);
})->group('T-P1-11', 'A4', 'YG-GAP-FAM-008', 'ED-017');

it('keeps normal delivery for learners who have their own email', function (): void {
    NotificationFacade::fake();
    $learner = crUser('student', ['email' => 'own@example.test']);

    expect(app(LearnerNotificationRouter::class)->send($learner, new ExampleLearnerNotification))->toBe([]);
    NotificationFacade::assertSentTo($learner, ExampleLearnerNotification::class);
    NotificationFacade::assertNotSentTo($learner, GuardianCopyNotification::class);
})->group('T-P1-11', 'A4', 'YG-GAP-FAM-008', 'ED-017');

it('stores the child’s notification in the database for the in-app inbox', function (): void {
    $parent = crUser('parent', ['email' => 'inbox@example.test']);
    $child = crChild($parent);

    app(LearnerNotificationRouter::class)->send($child, new ExampleLearnerNotification);

    expect($child->notifications()->count())->toBe(1)
        ->and($child->notifications()->first()->data)->toBe(['text' => 'Your lesson starts soon.'])
        ->and($parent->notifications()->count())->toBe(0);
})->group('T-P1-11', 'A4', 'YG-GAP-FAM-008', 'ED-017');
