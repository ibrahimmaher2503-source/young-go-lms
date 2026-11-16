<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Spatie\Translatable\HasTranslations;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
    expect(Artisan::call('migrate'))->toBe(0);

    Route::middleware('web')->get('/localization-probe', fn () => Blade::render(
        '<html lang="{{ app()->currentLocale() }}" dir="{{ app()->isLocale(\'ar\') ? \'rtl\' : \'ltr\' }}"><body>{{ __(\'common.language\') }}</body></html>',
    ));
    Route::middleware('web')->post('/localization-probe', fn (Request $request) => $request->validate([
        'email' => ['required', 'email'],
    ]));
});

it('switches language for a guest and remembers it across requests', function () {
    $this->get('/localization-probe', ['Accept-Language' => 'en-GB'])->assertSee('lang="en" dir="ltr"', false);
    $this->post('/locale', ['locale' => 'ar'])->assertRedirect()->assertSessionHas('locale', 'ar')->assertCookie('locale', 'ar');
    $this->get('/localization-probe', ['Accept-Language' => 'en-GB'])->assertSee('lang="ar" dir="rtl"', false);
})->group('T-P1-01', 'A1', 'D-011');

it('persists a signed-in preference and gives it precedence over session and cookie', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale', ['locale' => 'en'])->assertRedirect();
    expect($user->refresh()->getAttribute('locale'))->toBe('en');

    $this->withSession(['locale' => 'ar'])->withCookie('locale', 'ar')
        ->get('/localization-probe', ['Accept-Language' => 'ar-EG'])
        ->assertSee('lang="en" dir="ltr"', false);
})->group('T-P1-01', 'A1', 'D-011');

it('honors a saved cookie before the browser and uses the configured default for unsupported languages', function () {
    config(['app.locale' => 'ar']);

    $this->get('/localization-probe', ['Accept-Language' => 'fr-FR'])->assertSee('lang="ar" dir="rtl"', false);
    $this->withCookie('locale', 'en')->get('/localization-probe', ['Accept-Language' => 'ar-EG'])
        ->assertSee('lang="en" dir="ltr"', false);
})->group('T-P1-01', 'A1', 'D-011');

it('returns validation errors in the request language and rejects unsupported locale input', function () {
    $this->postJson('/localization-probe', [], ['Accept-Language' => 'ar-EG'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'حقل البريد الإلكتروني مطلوب.');
    $this->postJson('/localization-probe', [], ['Accept-Language' => 'en-US'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'The email field is required.');
    $this->postJson('/locale', ['locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('locale');
})->group('T-P1-01', 'A2', 'D-011');

it('serves localized public route locales independently of an existing preference', function () {
    Route::middleware('web')->get('/{publicLocale}/localization-probe', fn () => app()->currentLocale())
        ->whereIn('publicLocale', ['ar', 'en']);

    $this->withSession(['locale' => 'ar'])->get('/en/localization-probe')->assertOk()->assertContent('en');
    $this->get('/xx/localization-probe')->assertNotFound();
})->group('T-P1-01', 'A1', 'ED-018');

it('resolves a stateless request without a session', function () {
    Route::middleware('api')->get('/api/localization-probe', fn () => app()->currentLocale());
    $this->get('/api/localization-probe', ['Accept-Language' => 'ar-EG'])->assertOk()->assertContent('ar');
})->group('T-P1-01', 'A1', 'D-011');

it('selects a JSON translation and falls back when it is absent', function () {
    $record = new class extends Model
    {
        use HasTranslations;

        public array $translatable = ['title'];
    };
    $record->setTranslations('title', ['en' => 'Scene', 'ar' => 'مشهد']);
    app()->setLocale('ar');
    expect($record->getAttribute('title'))->toBe('مشهد');
    $record->forgetTranslation('title', 'ar');
    expect($record->getAttribute('title'))->toBe('Scene');
})->group('T-P1-01', 'A3', 'ED-006');

it('keeps Arabic and English translation keys in parity', function () {
    $this->artisan('app:check-translations')->assertSuccessful();
})->group('T-P1-01', 'A4', 'D-011');

it('blocks a suspended account from changing its persisted locale', function () {
    $user = User::factory()->create(['locale' => 'en', 'status' => 'suspended']);
    $this->actingAs($user)->postJson('/locale', ['locale' => 'ar'])->assertForbidden();
    expect($user->refresh()->getAttribute('locale'))->toBe('en');
})->group('T-P1-02', 'A2');

it('rejects a stale password session before saving a locale', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $staleHash = $user->getAuthPassword();
    $user->forceFill(['password' => 'replacement-password'])->save();

    $this->actingAs($user)->withSession(['password_hash_web' => $staleHash])
        ->postJson('/locale', ['locale' => 'ar'])->assertUnauthorized();

    expect($user->refresh()->getAttribute('locale'))->toBe('en');
})->group('T-P1-02', 'A2');
