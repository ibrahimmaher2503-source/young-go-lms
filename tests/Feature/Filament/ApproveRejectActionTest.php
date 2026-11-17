<?php

declare(strict_types=1);

use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function reviewActions(array &$calls, bool $throw = false): array
{
    $record = static function (string $decision) use (&$calls, $throw): Closure {
        return static function ($subject, User $actor, ?string $reason) use (&$calls, $decision, $throw): void {
            $calls[] = [$decision, $subject->getKey(), $actor->getKey(), $reason];
            if ($throw) {
                throw new RuntimeException('domain failure');
            }
        };
    };

    return ApproveRejectAction::make('review.example', 'example', $record('approved'), $record('rejected'));
}

beforeEach(function (): void {
    $this->reviewer = User::factory()->create();
    $this->subject = User::factory()->create();
    Auth::login($this->reviewer);
    Gate::define('review.example', fn (User $actor): bool => $actor->is($this->reviewer));
});

it('builds approve and reject actions with a required bounded reject reason', function (): void {
    $calls = [];
    [$approve, $reject] = reviewActions($calls);

    expect($approve)->toBeInstanceOf(Action::class)->and($approve->getName())->toBe('approve')
        ->and($reject->getName())->toBe('reject');

    $rejectReason = (new ReflectionProperty(Action::class, 'schema'))->getValue($reject);
    $textarea = collect(is_array($rejectReason) ? $rejectReason : [])->first();
    expect($textarea)->toBeInstanceOf(Textarea::class)
        ->and($textarea->isRequired())->toBeTrue()
        ->and($textarea->getMinLength())->toBe(10)
        ->and($textarea->getMaxLength())->toBe(1000);
})->group('T-P1-08', 'A2');

it('runs the domain mutation and audits approval with the actor', function (): void {
    $calls = [];
    [$approve] = reviewActions($calls);

    $approve->getActionFunction()($this->subject, ['reason' => null]);

    expect($calls)->toBe([['approved', $this->subject->id, $this->reviewer->id, null]]);
    $activity = Activity::query()->where('event', 'example.approved')->sole();
    expect($activity->causer_id)->toBe($this->reviewer->id)->and($activity->subject_id)->toBe($this->subject->id);
})->group('T-P1-08', 'A2', 'PRD-22');

it('requires a reason to reject and stores it in the audit context', function (): void {
    $calls = [];
    [, $reject] = reviewActions($calls);

    expect(fn () => $reject->getActionFunction()($this->subject, ['reason' => ' short ']))
        ->toThrow(ValidationException::class);
    expect($calls)->toBe([]);

    $reject->getActionFunction()($this->subject, ['reason' => 'Curriculum PDF is missing chapter two.']);

    expect($calls[0][0])->toBe('rejected')
        ->and(Activity::query()->where('event', 'example.rejected')->sole()->properties->get('context')['reason'])
        ->toBe('Curriculum PDF is missing chapter two.');
})->group('T-P1-08', 'A2');

it('rechecks authorization at execution and hides actions from denied staff', function (): void {
    $calls = [];
    [$approve] = reviewActions($calls);
    Auth::login(User::factory()->create());

    expect($approve->record($this->subject)->isAuthorized())->toBeFalse();
    expect(fn () => $approve->getActionFunction()($this->subject, []))->toThrow(AuthorizationException::class);
    expect($calls)->toBe([])->and(Activity::query()->count())->toBe(0);
})->group('T-P1-08', 'A1');

it('writes no audit entry when the domain mutation fails', function (): void {
    $calls = [];
    [$approve] = reviewActions($calls, throw: true);

    expect(fn () => $approve->getActionFunction()($this->subject, []))->toThrow(RuntimeException::class);
    expect(Activity::query()->where('event', 'example.approved')->exists())->toBeFalse();
})->group('T-P1-08', 'A2');
