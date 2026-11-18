<?php

declare(strict_types=1);

use App\Domain\Library\Actions\AddLibraryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

it('shows the content library accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    $admin = User::factory()->create(['locale' => $locale])->assignRole('admin');
    foreach ([['ورقة عمل الضوء والظل', 'Light and shadow worksheet', ['light', 'shadow']], ['دليل الألوان', 'Colour guide', ['colour']], ['مرجع الأدوات', 'Tools reference', []]] as $index => [$ar, $en, $tags]) {
        $path = tempnam(sys_get_temp_dir(), 'lb');
        file_put_contents($path, PdfFixture::bytes(marker: (string) $index));
        app(AddLibraryItem::class)($admin, new File($path), ['title' => ['ar' => $ar, 'en' => $en], 'tags' => $tags, 'scope' => $index === 0 ? 'academy' : 'mine']);
    }
    $this->withSession(['locale' => $locale]);
    $this->actingAs($admin);

    $page = $this->visit('/admin/library')->resize($width, $height);
    $page->assertSee(__('library.add', [], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P3-13');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-13/library-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-13', 'A3');
