<?php

declare(strict_types=1);

// T-G7-FIXES-F | F6 validation attribute names on POST /teach (AR and EN, no raw field names)

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** Raw field tokens that must never reach a learner-facing validation message. */
const G7F_TEACH_RAW_TOKENS = ['full_name', 'discipline', 'portfolio_links', 'id_document', 'experience_years', 'privacy'];

/** @return list<string> */
function g7fTeachMessages(string $locale): array
{
    test()->actingAs(User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student'));

    $response = test()->withSession(['locale' => $locale])->post(route('instructors.apply'), []);
    $response->assertSessionHasErrors(['full_name', 'discipline', 'bio', 'portfolio_links', 'id_document', 'privacy']);

    $messages = [];
    foreach (session('errors')->getBag('default')->all() as $message) {
        $messages[] = $message;
    }

    return $messages;
}

it('F6 the English teach validation messages name each field in plain words', function (): void {
    $messages = g7fTeachMessages('en');
    $joined = implode(' | ', $messages);

    expect($messages)->not->toBeEmpty()
        ->and($joined)->toContain('full name')
        ->and($joined)->toContain('main creative field')
        ->and($joined)->toContain('portfolio links')
        ->and($joined)->toContain('ID document')
        ->and($joined)->toContain('data-use consent');
});

it('F6 no English teach validation message contains a raw field token', function (): void {
    foreach (g7fTeachMessages('en') as $message) {
        foreach (G7F_TEACH_RAW_TOKENS as $token) {
            expect(str_contains($message, $token))->toBeFalse("Raw token [{$token}] leaked in: {$message}");
        }
    }
});

it('F6 the Arabic teach validation messages use the localized attribute names', function (): void {
    $joined = implode(' | ', g7fTeachMessages('ar'));

    expect($joined)->toContain('الاسم الكامل')
        ->and($joined)->toContain('وثيقة الهوية')
        ->and($joined)->toContain('روابط الأعمال')
        ->and($joined)->toContain('المجال الإبداعي الرئيسي')
        ->and($joined)->toContain('الموافقة على استخدام البيانات');
});

it('F6 no Arabic teach validation message contains a raw field token', function (): void {
    foreach (g7fTeachMessages('ar') as $message) {
        foreach (G7F_TEACH_RAW_TOKENS as $token) {
            expect(str_contains($message, $token))->toBeFalse("Raw token [{$token}] leaked in: {$message}");
        }
    }
});

it('F6 every attribute name used on the teach form exists in both locales', function (): void {
    foreach (['full_name', 'phone', 'discipline', 'experience_years', 'bio', 'portfolio_links', 'id_document', 'privacy'] as $attribute) {
        expect(__('validation.attributes.'.$attribute, [], 'en'))->not->toBe('validation.attributes.'.$attribute)
            ->and(__('validation.attributes.'.$attribute, [], 'ar'))->not->toBe('validation.attributes.'.$attribute);
    }
});
