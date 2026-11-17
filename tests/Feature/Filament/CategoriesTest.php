<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function categoryData(string $slug = 'acting'): array
{
    return [
        'name' => ['ar' => 'أَدَاء مَسرحيّة', 'en' => 'Stage Acting'],
        'description' => ['ar' => 'تمثيل', 'en' => 'Drama'],
        'slug_ar' => 'تمثيل-'.$slug,
        'slug_en' => $slug,
        'sort' => 0,
        'status' => 'active',
    ];
}

it('persists and lists categories in tree order through the admin workspace', function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    $this->get('/admin/categories')->assertOk();
    Storage::fake('public');

    $root = Category::query()->create(categoryData('root'));
    $secondRoot = Category::query()->create([...categoryData('second-root'), 'sort' => 10]);
    $later = Category::query()->create([...categoryData('later'), 'parent_id' => $root->id, 'sort' => 20]);
    Livewire::test(ListCategories::class)
        ->callAction('create', [...categoryData('earlier'), 'parent_id' => $root->id, 'sort' => 5, 'image' => [UploadedFile::fake()->image('acting.png')]])
        ->assertHasNoActionErrors();

    $earlier = Category::query()->where('slug_en', 'earlier')->sole();
    expect($earlier->parent->is($root))->toBeTrue()
        ->and($earlier->sort)->toBe(5);
    Storage::disk('public')->assertExists($earlier->image);

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords([$root, $earlier, $later, $secondRoot], inOrder: true)
        ->callAction(TestAction::make('edit')->table($root), ['parent_id' => $secondRoot->id])
        ->assertHasActionErrors(['parent_id']);

    Livewire::test(ListCategories::class)
        ->callAction('create', [...categoryData('third-level'), 'parent_id' => $earlier->id])
        ->assertHasActionErrors(['parent_id']);

    Livewire::test(ListCategories::class)
        ->callAction(TestAction::make('edit')->table($earlier), ['sort' => 30])
        ->assertHasNoActionErrors()
        ->assertCanSeeTableRecords([$root, $later, $earlier, $secondRoot], inOrder: true);

    expect($earlier->fresh()->getTranslations('name'))->toBe(categoryData()['name']);
})->group('T-P2-01', 'A1', 'YG-WEB-003');

it('enforces slug uniqueness independently for each locale', function (string $column) {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    $first = Category::query()->create(categoryData());
    Category::query()->create([...categoryData('other'), 'slug_ar' => $first->slug_en]);

    Livewire::test(ListCategories::class)
        ->callAction(TestAction::make('edit')->table($first), ['sort' => 3])
        ->assertHasNoActionErrors()
        ->callAction('create', [...categoryData('duplicate'), $column => $first->{$column}])
        ->assertHasActionErrors([$column]);

    expect(fn () => Category::query()->create([...categoryData('duplicate'), $column => $first->{$column}]))
        ->toThrow(UniqueConstraintViolationException::class);
})->with(['slug_ar', 'slug_en'])->group('T-P2-01', 'A2');

it('indexes both locales via HasSearchText and uses normalized admin search', function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    $category = Category::query()->create(categoryData());
    $other = Category::query()->create([
        ...categoryData('drawing'),
        'name' => ['ar' => 'رسم', 'en' => 'Drawing'],
        'description' => [],
    ]);

    expect($category->fresh()->search_text)->toBe('اداء مسرحيه stage acting تمثيل drama');
    Livewire::test(ListCategories::class)->searchTable('اداء')
        ->assertCanSeeTableRecords([$category])
        ->assertCanNotSeeTableRecords([$other]);

    $category->setTranslation('name', 'ar', 'رسوم متحركة')->save();
    expect($category->fresh()->search_text)->toBe('رسوم متحركه stage acting تمثيل drama');
})->group('T-P2-01', 'A3', 'YG-GAP-SRCH-001');

it('seeds all seven disciplines once and preserves admin edits', function () {
    $this->seed(CategorySeeder::class);
    $this->assertDatabaseCount('categories', 7);
    $category = Category::query()->where('slug_en', 'acting')->sole();
    $category->update(['slug_en' => 'stage-acting', 'name' => ['ar' => 'فنون التمثيل', 'en' => 'Stage Acting']]);

    $this->seed(CategorySeeder::class);
    $this->assertDatabaseCount('categories', 7);
    expect($category->fresh()->slug_en)->toBe('stage-acting');
})->group('T-P2-01');

it('denies the category workspace to staff without category permission', function () {
    $this->actingAs(User::factory()->create()->assignRole('finance'));
    $this->get('/admin/categories')->assertForbidden();
})->group('T-P2-01');
