<?php

declare(strict_types=1);

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
