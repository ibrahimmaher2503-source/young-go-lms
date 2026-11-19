<?php

declare(strict_types=1);

// T-G7-FIXES-F | F9 learner layout on notification preferences and privacy pages (tab bar, aria-current, link to parent hidden for adults)

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

/** Adult learner with a verified email and an adult birth year, so no parent link is needed. */
function g7fAdultLearner(): User
{
    return User::factory()->create([
        'status' => 'active', 'locale' => 'en', 'email' => 'g7f-adult-'.uniqid().'@example.test',
        'email_verified_at' => now(), 'birth_year' => 1990,
    ])->assignRole('student');
}

/** Learner under the adult age: a minor still needs the link to a parent. */
function g7fMinorLearner(): User
{
    return User::factory()->create([
        'status' => 'active', 'locale' => 'en', 'email' => 'g7f-minor-'.uniqid().'@example.test',
        'email_verified_at' => now(), 'birth_year' => 2015,
    ])->assignRole('student');
}

function g7fCrawl(string $html): Crawler
{
    return new Crawler($html);
}

it('F9 the adult notification preferences page renders the learner shell with the profile tab current', function (): void {
    $html = $this->actingAs(g7fAdultLearner())->get(route('profile.notifications.show'))->assertOk()
        ->assertSee('data-bottom-nav', false)
        ->assertSee('max-w-6xl', false)
        ->getContent();

    $bottom = g7fCrawl($html)->filter('[data-bottom-nav] a[aria-current="page"]');
    expect($bottom->count())->toBe(1)
        ->and($bottom->attr('href'))->toEndWith('/profile');
});

it('F9 the adult privacy page renders the learner shell with the profile tab current', function (): void {
    $html = $this->actingAs(g7fAdultLearner())->get(route('profile.privacy.show'))->assertOk()
        ->assertSee('data-bottom-nav', false)
        ->assertSee('max-w-6xl', false)
        ->getContent();

    $bottom = g7fCrawl($html)->filter('[data-bottom-nav] a[aria-current="page"]');
    expect($bottom->count())->toBe(1)
        ->and($bottom->attr('href'))->toEndWith('/profile');
});

it('F9 the learner shell main container matches the payments page width', function (): void {
    $learner = g7fAdultLearner();

    $this->actingAs($learner)->get(route('profile.notifications.show'))->assertOk()->assertSee('max-w-6xl', false);
    $this->actingAs($learner)->get('/my/payments')->assertOk()->assertSee('max-w-6xl', false);
});

it('F9 an adult learner does not see the link to a parent on the notification and privacy pages', function (): void {
    $learner = g7fAdultLearner();

    $this->actingAs($learner)->get(route('profile.notifications.show'))->assertOk()->assertDontSee(url('/link'), false);
    $this->actingAs($learner)->get(route('profile.privacy.show'))->assertOk()->assertDontSee(url('/link'), false);
});

it('F9 a minor learner still sees the link to a parent in the tab bar', function (): void {
    $html = $this->actingAs(g7fMinorLearner())->get(route('profile.privacy.show'))->assertOk()->getContent();

    expect(g7fCrawl($html)->filter('[data-bottom-nav] a[href$="/link"]')->count())->toBe(1);
});

it('F9 a parent keeps the parent tabs and the profile tab is current', function (): void {
    $parent = User::factory()->create(['status' => 'active', 'locale' => 'en', 'email_verified_at' => now()])->assignRole('parent');

    $html = $this->actingAs($parent)->get(route('profile.notifications.show'))->assertOk()->getContent();

    $crawler = g7fCrawl($html);
    expect($crawler->filter('[data-bottom-nav] a[href$="/parent"]')->count())->toBe(1)
        ->and($crawler->filter('[data-bottom-nav] a[href$="/link"]')->count())->toBe(0)
        ->and($crawler->filter('[data-bottom-nav] a[aria-current="page"]')->attr('href'))->toEndWith('/profile');
});
