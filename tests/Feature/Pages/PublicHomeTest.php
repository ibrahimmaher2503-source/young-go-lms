<?php

declare(strict_types=1);

use App\Domain\Seo\PublicUrl;
use App\Domain\Settings\GeneralSettings;
use App\Models\CmsPage;
use App\Models\Course;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the branded public shell and only shows published featured content', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    CmsPage::query()->where('slug', 'about')->update(['status' => CmsPage::PUBLISHED]);

    foreach ([['Featured painting', 'published', true], ['Draft painting', 'draft', true], ['Ordinary painting', 'published', false]] as [$title, $status, $featured]) {
        Course::query()->create([
            'title' => ['en' => $title, 'ar' => $title],
            'slug_ar' => str($title)->slug()->toString(),
            'slug_en' => str($title)->slug()->toString(),
            'status' => $status,
            'access_mode' => Course::ACCESS_FREE,
            'featured' => $featured,
        ]);
    }

    $this->get('/en')->assertOk()
        ->assertSee('data-public-nav', false)
        ->assertSee(__('app.name'))
        ->assertSee('public-home-hero-640.webp', false)
        ->assertSee('/en/pages/about')
        ->assertSee('/contact')
        ->assertDontSee('/en/pages/privacy')
        ->assertSee('Featured painting')
        ->assertDontSee('Draft painting')
        ->assertDontSee('Ordinary painting');
})->group('T-P2-03', 'A1', 'D-002', 'YG-WEB-001', 'YG-WEB-010');

it('links to the catalog discipline section and uses saved footer links', function (string $locale): void {
    $this->withoutVite();
    config(['public-site.social_links.facebook' => 'https://facebook.example/old']);
    $settings = app(GeneralSettings::class);
    $settings->social_facebook = 'https://facebook.example/current';
    $settings->social_linkedin = 'https://linkedin.example/current';
    $settings->save();

    $catalog = PublicUrl::catalog($locale);
    $this->get('/'.$locale)->assertOk()
        ->assertSee($catalog.'#catalog-filters', false)
        ->assertSee('https://facebook.example/current', false)
        ->assertSee('https://linkedin.example/current', false)
        ->assertDontSee('https://facebook.example/old', false);
    $this->get($catalog)->assertOk()->assertSee('id="catalog-filters"', false);
})->with(['ar', 'en'])->group('T-P2-03', 'A1', 'YG-WEB-001', 'YG-GAP-UX-001');
