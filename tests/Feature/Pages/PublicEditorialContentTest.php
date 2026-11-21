<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\PublicEditorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prepares bilingual editorial drafts and preserves existing copy and publication state on reruns', function (): void {
    $this->withoutVite();
    $this->seed(CategorySeeder::class);
    Category::query()->where('slug_en', 'drawing')->update(['seed_key' => null]);
    $this->seed(PublicEditorialSeeder::class);

    $about = CmsPage::query()->where('seed_key', 'about')->firstOrFail();
    $article = BlogPost::query()->where('slug_en', 'choose-a-creative-course')->firstOrFail();
    $drawing = Category::query()->where('slug_en', 'drawing')->firstOrFail();
    foreach (['ar', 'en'] as $locale) {
        expect($about->getTranslation('body', $locale, false))->not->toBe(__('cms.placeholder', [], $locale));
        expect($article->getTranslation('body', $locale, false))->not->toBeEmpty();
        expect($drawing->getTranslation('description', $locale, false))->not->toBeEmpty();
    }
    $this->get('/en/pages/about')->assertNotFound();
    $this->get('/en/blog/choose-a-creative-course')->assertNotFound();
    $this->assertDatabaseHas('pages', ['slug' => 'privacy', 'status' => 'draft']);

    $about->update(['body' => ['ar' => 'نص المحرر', 'en' => 'Edited copy']]);
    $drawing->update(['description' => ['ar' => 'وصف المحرر', 'en' => 'Edited discipline']]);
    $article->update(['status' => 'published', 'published_at' => now()->subMinute(), 'excerpt' => ['en' => 'Edited article excerpt']]);
    $this->seed(PublicEditorialSeeder::class);

    $this->assertDatabaseCount('pages', 6);
    $this->assertDatabaseCount('blog_posts', 3);
    expect($about->refresh()->getTranslation('body', 'en', false))->toBe('Edited copy');
    expect($article->refresh()->getTranslation('excerpt', 'en', false))->toBe('Edited article excerpt');
    expect($drawing->refresh()->getTranslation('description', 'en', false))->toBe('Edited discipline');
    $this->get('/en/blog/choose-a-creative-course')->assertOk();
})->group('T-P2-02', 'T-P2-07', 'YG-WEB-005', 'YG-WEB-006');

it('replaces public editorial placeholders and withdraws only untouched fake policy copy', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    CmsPage::query()->whereIn('seed_key', ['about', 'privacy', 'terms'])->update(['status' => CmsPage::PUBLISHED]);
    CmsPage::query()->where('seed_key', 'terms')->firstOrFail()->update(['body' => ['ar' => 'نص المحرر', 'en' => 'Editor copy']]);

    $this->seed(PublicEditorialSeeder::class);

    $this->get('/en/pages/about')->assertOk()->assertSee('Young Go is a creative learning platform')->assertDontSee(__('cms.placeholder', [], 'en'));
    $this->get('/en/pages/privacy')->assertNotFound();
    $this->get('/en/pages/terms')->assertOk()->assertSee('Editor copy');
})->group('T-P2-02', 'YG-WEB-005');
