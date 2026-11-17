<?php

declare(strict_types=1);

use App\Filament\Resources\CmsPages\Pages\ListCmsPages;
use App\Filament\Resources\HomepageBlocks\Pages\ListHomepageBlocks;
use App\Models\CmsPage;
use App\Models\HomepageBlock;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

it('publishes pages only as explicitly requested and restricts signed previews to editors', function () {
    $this->get('/pages/workshops')->assertNotFound();
    $editor = User::factory()->create(['locale' => 'en'])->assignRole('content_manager');
    $learner = User::factory()->create()->assignRole('student');

    $this->actingAs($editor)->get('/admin/cms-pages')->assertOk();
    Livewire::test(ListCmsPages::class)->callAction('create', [
        'slug' => 'workshops',
        'title' => ['ar' => 'ورش العمل', 'en' => 'Workshops'],
        'body' => ['ar' => 'محتوى تجريبي', 'en' => '<script>alert(1)</script> Draft workshops'],
        'status' => 'draft',
        'template' => 'standard',
    ])->assertHasNoActionErrors();

    $page = CmsPage::query()->where('slug', 'workshops')->sole();
    $preview = URL::temporarySignedRoute('cms.preview', now()->addMinutes(5), ['page' => $page]);
    $expired = URL::temporarySignedRoute('cms.preview', now()->subMinute(), ['page' => $page]);
    $this->get('/pages/workshops')->assertNotFound();
    $this->get($preview)->assertOk()->assertSee('Draft workshops')->assertDontSee('<script>alert(1)</script>', false);
    $this->get($preview.'&changed=yes')->assertForbidden();
    $this->get($expired)->assertForbidden();
    $this->actingAs($learner)->get($preview)->assertForbidden();
    auth()->logout();
    $this->get($preview)->assertRedirect();

    $this->actingAs($editor);
    Livewire::test(ListCmsPages::class)->callAction(TestAction::make('edit')->table($page), ['status' => 'published'])
        ->assertHasNoActionErrors();
    auth()->logout();
    $this->withSession(['locale' => 'en'])->get('/pages/workshops')->assertOk()->assertSee('Draft workshops');
})->group('T-P2-02', 'A1', 'YG-WEB-005');

it('reflects homepage block order and visibility without publishing hidden content', function () {
    $this->get('/')->assertOk();
    $later = HomepageBlock::query()->create([
        'type' => 'cta', 'title' => ['en' => 'Later block', 'ar' => 'الفقرة الأخيرة'], 'sort' => 20, 'visible' => true,
    ]);
    $earlier = HomepageBlock::query()->create([
        'type' => 'hero', 'title' => ['en' => 'Earlier block', 'ar' => 'الفقرة الأولى'], 'sort' => 5, 'visible' => true,
    ]);
    HomepageBlock::query()->create([
        'type' => 'faq', 'title' => ['en' => 'Hidden block', 'ar' => 'فقرة مخفية'], 'sort' => 0, 'visible' => false,
    ]);

    $this->withSession(['locale' => 'en'])->get('/')->assertOk()
        ->assertSeeInOrder(['Earlier block', 'Later block'])->assertDontSee('Hidden block');
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('content_manager'))
        ->get('/admin/homepage-blocks')->assertOk();
    Livewire::test(ListHomepageBlocks::class)->callAction(TestAction::make('edit')->table($later), ['sort' => 1])
        ->assertHasNoActionErrors();
    $this->get('/')->assertSeeInOrder(['Later block', 'Earlier block']);
    Livewire::test(ListHomepageBlocks::class)->callAction(TestAction::make('edit')->table($earlier), ['visible' => false])
        ->assertHasNoActionErrors();
    $this->get('/')->assertSee('Later block')->assertDontSee('Earlier block');
    auth()->logout();
    $this->withSession(['locale' => 'ar'])->get('/')->assertSee('الفقرة الأخيرة')->assertDontSee('فقرة مخفية');
})->group('T-P2-02', 'A2', 'YG-WEB-002');

it('seeds six editable drafts without publishing placeholder policies or replacing edits', function () {
    $this->seed(CmsPageSeeder::class);
    expect(CmsPage::query()->orderBy('slug')->pluck('slug')->all())->toBe(['about', 'contact', 'faq', 'privacy', 'refund', 'terms']);
    expect(CmsPage::query()->where('status', 'draft')->count())->toBe(6);
    $this->get('/pages/privacy')->assertNotFound();
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('content_manager'));
    $policy = CmsPage::query()->where('slug', 'privacy')->sole();
    Livewire::test(ListCmsPages::class)->callAction(TestAction::make('edit')->table($policy), ['status' => 'published'])
        ->assertHasActionErrors(['body.ar', 'body.en']);
    expect($policy->fresh()->status)->toBe('draft');

    $page = CmsPage::query()->where('slug', 'about')->sole();
    $page->update(['slug' => 'about-us', 'title' => ['en' => 'About us', 'ar' => 'عن المنصة']]);
    $this->seed(CmsPageSeeder::class);
    $this->assertDatabaseCount('pages', 6);
    expect($page->fresh()->slug)->toBe('about-us');
})->group('T-P2-02', 'YG-WEB-005');
