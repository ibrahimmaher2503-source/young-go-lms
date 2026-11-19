<?php

declare(strict_types=1);

// T-G7-FIXES-F | F11 wishlist cover or placeholder, price and heart aria-pressed state

use App\Domain\Commerce\CoursePrice;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
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

function g7fWishCourse(string $tag, bool $priced, ?string $cover = null): Course
{
    $course = Course::query()->create([
        'title' => ['en' => 'Wish '.$tag, 'ar' => 'أمنية '.$tag],
        'slug_ar' => 'g7f-w-ar-'.$tag.'-'.uniqid(), 'slug_en' => 'g7f-w-en-'.$tag.'-'.uniqid(),
        'status' => 'published', 'access_mode' => $priced ? 'paid' : Course::ACCESS_FREE,
        'cover_image' => $cover,
    ]);
    if ($priced) {
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 25000]);
    }

    return $course;
}

it('F11 each saved course on the wishlist has a cover or placeholder block and a price when priced', function (): void {
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    $priced = g7fWishCourse('priced', true);
    $free = g7fWishCourse('free', false);
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $priced->id]))->assertOk();
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $free->id]))->assertOk();

    $crawler = new Crawler($this->actingAs($learner)->get(route('wishlist.index'))->assertOk()->getContent());

    $hearts = $crawler->filter('[data-wishlist-button]')->count();
    $covers = $crawler->filter('[data-card-cover-placeholder]')->count() + $crawler->filter('img[width][height]')->count();

    expect($hearts)->toBe(2)
        ->and($covers)->toBeGreaterThanOrEqual($hearts)
        ->and($crawler->filter('[data-course-price]')->count())->toBe(1);
});

it('F11 the wishlist heart is pressed and filled for saved courses', function (): void {
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    $course = g7fWishCourse('saved', true);
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $course->id]))->assertOk();

    $crawler = new Crawler($this->actingAs($learner)->get(route('wishlist.index'))->assertOk()->getContent());
    $button = $crawler->filter('#wishlist-'.$course->id.' [data-wishlist-button]');

    expect($button->attr('aria-pressed'))->toBe('true')
        ->and($button->attr('class'))->toContain('bg-danger')
        ->and($button->attr('class'))->toContain('text-on-danger');
});

it('F11 an unsaved heart on the catalog is not pressed and uses the body text colour', function (): void {
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    $saved = g7fWishCourse('kept', true);
    $unsaved = g7fWishCourse('later', true);
    $this->actingAs($learner)->postJson(route('wishlist.toggle', ['course' => $saved->id]))->assertOk();

    $crawler = new Crawler($this->actingAs($learner)->get(route('catalog.index'))->assertOk()->getContent());

    $savedButton = $crawler->filter('#wishlist-'.$saved->id.' [data-wishlist-button]');
    $unsavedButton = $crawler->filter('#wishlist-'.$unsaved->id.' [data-wishlist-button]');

    expect($savedButton->count())->toBe(1)
        ->and($savedButton->attr('aria-pressed'))->toBe('true')
        ->and($unsavedButton->count())->toBe(1)
        ->and($unsavedButton->attr('aria-pressed'))->toBe('false')
        ->and($unsavedButton->attr('class'))->toContain('text-body')
        ->and($unsavedButton->attr('class'))->not->toContain('bg-danger');
});
