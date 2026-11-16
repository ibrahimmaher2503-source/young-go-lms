<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
    expect(Artisan::call('migrate'))->toBe(0);
});

it('persists only the allowed profile settings', function () {
    $user = User::factory()->create()->refresh();

    $this->actingAs($user)->patchJson('/profile', [
        'name_en' => 'New Name',
        'display_name' => 'New Name',
        'locale' => 'ar',
        'timezone' => 'Africa/Cairo',
        'notification_preferences' => ['email' => false],
        'status' => 'deactivated',
        'roles' => ['super_admin'],
        'password' => 'unauthorized',
    ])->assertNoContent();

    $user->refresh();
    expect($user->getAttribute('display_name'))->toBe('New Name')
        ->and($user->getAttribute('locale'))->toBe('ar')
        ->and($user->getAttribute('status'))->toBe('active')
        ->and(json_decode($user->getRawOriginal('notification_preferences'), true))->toBe(['email' => false])
        ->and(Hash::check('password', $user->password))->toBeTrue();
})->group('T-P1-02', 'A5', 'YG-GAP-ID-001');

it('lets a learner update certificate names until locked and rejects later changes', function () {
    $user = User::factory()->create()->refresh();
    $this->actingAs($user)->patchJson('/profile/certificate-names', [
        'certificate_name_en' => 'Certificate Name',
    ])->assertNoContent();

    $user->setAttribute('certificate_names_locked_at', now())->save();

    $this->patchJson('/profile/certificate-names', [
        'certificate_name_en' => 'Changed Name',
        'certificate_names_locked_at' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors('certificate_name_en');
    expect($user->refresh()->getAttribute('certificate_name_en'))->toBe('Certificate Name');
})->group('T-P1-02', 'A5', 'YG-GAP-ID-001');

it('requires the current password and persists a hashed replacement', function () {
    $user = User::factory()->create()->refresh();
    $this->actingAs($user)->putJson('/profile/password', [
        'current_password' => 'incorrect',
        'password' => 'replacement-secure',
        'password_confirmation' => 'replacement-secure',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->putJson('/profile/password', [
        'current_password' => 'password',
        'password' => 'replacement-secure',
        'password_confirmation' => 'replacement-secure',
    ])->assertNoContent();
    expect(Hash::check('replacement-secure', $user->refresh()->password))->toBeTrue();
})->group('T-P1-02', 'A5');

it('keeps the initiating session active and rejects a stale password session', function () {
    $user = User::factory()->create()->refresh();
    $oldHash = $user->getAuthPassword();

    $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
        ->putJson('/profile/password', [
            'current_password' => 'password',
            'password' => 'replacement-secure',
            'password_confirmation' => 'replacement-secure',
        ])->assertNoContent();

    $this->actingAs($user->fresh())->patchJson('/profile', ['display_name' => 'Current session'])
        ->assertNoContent();
    $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])
        ->patchJson('/profile', ['display_name' => 'Stale session'])->assertUnauthorized();

    expect($user->refresh()->getAttribute('display_name'))->toBe('Current session');
})->group('T-P1-02', 'A5');

it('rejects a new password above the bcrypt byte limit', function () {
    $user = User::factory()->create()->refresh();
    $password = str_repeat("\u{0627}", 37);

    $this->actingAs($user)->putJson('/profile/password', [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->group('T-P1-02', 'A5');

it('publishes a small re-encoded avatar under a generated name and keeps its source private', function () {
    $user = User::factory()->create()->refresh();
    Storage::fake('local');
    Storage::fake('public');

    $original = UploadedFile::fake()->image('original.jpg', 640, 480)->get();
    $metadata = "Exif\0\0II\x2A\0\x08\0\0\0\0\0\0\0\0\0";
    $tagged = substr($original, 0, 2)."\xFF\xE1".pack('n', strlen($metadata) + 2).$metadata.substr($original, 2);

    $this->actingAs($user)->postJson('/profile/avatar', [
        'avatar' => UploadedFile::fake()->createWithContent('original.jpg', $tagged),
    ])->assertNoContent();

    $path = $user->refresh()->getAttribute('avatar_path');
    expect($path)->toMatch('#^avatars/[a-f0-9-]+\.jpg$#');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertExists($path);
    foreach (['local', 'public'] as $disk) {
        expect(Storage::disk($disk)->get($path))->not->toContain($metadata);
    }

    $image = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($image[0])->toBe(256)->and($image[1])->toBe(192);
})->group('T-P1-02', 'A5', 'YG-GAP-SEC-004');

it('blocks an inactive account from profile mutation', function () {
    $user = User::factory()->create()->refresh();
    $user->setAttribute('status', 'suspended')->save();

    $this->actingAs($user)->patchJson('/profile', ['display_name' => 'Blocked'])->assertForbidden();
    expect($user->refresh()->getAttribute('display_name'))->not->toBe('Blocked');
})->group('T-P1-02', 'A2', 'A5');
