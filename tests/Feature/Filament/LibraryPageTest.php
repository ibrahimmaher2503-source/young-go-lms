<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Library\Actions\AddLibraryItem;
use App\Domain\Library\Actions\AttachLibraryItem;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Filament\Resources\Library\Pages\ListLibraryItems;
use App\Models\Course;
use App\Models\LessonResource;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
});

function lpPdf(string $salt = ''): File
{
    $path = tempnam(sys_get_temp_dir(), 'lp');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n% {$salt}");

    return new File($path);
}

it('opens the library for admin and instructors and keeps it away from everyone else', function (): void {
    $this->actingAs(User::factory()->create()->assignRole('admin'))->get('/admin/library')->assertOk()->assertSee(__('library.add'));
    $this->actingAs(User::factory()->create()->assignRole('instructor_academy'))->get('/instructor/library')->assertOk();
    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/admin/library')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('finance'))->get('/finance/library')->assertNotFound();
})->group('T-P3-13', 'A3');

it('lists only the items an instructor may use and adds and deletes through the screen', function (): void {
    $mine = User::factory()->create()->assignRole('instructor_academy');
    $other = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('admin');
    $own = app(AddLibraryItem::class)($mine, lpPdf('a'), ['title' => ['en' => 'My sheet']]);
    $theirs = app(AddLibraryItem::class)($other, lpPdf('b'), ['title' => ['en' => 'Their sheet']]);
    $shared = app(AddLibraryItem::class)($admin, lpPdf('c'), ['title' => ['en' => 'Academy sheet'], 'scope' => 'academy']);

    $this->actingAs($mine);
    Livewire::test(ListLibraryItems::class)->assertCanSeeTableRecords([$own, $shared])->assertCanNotSeeTableRecords([$theirs])
        ->assertTableActionVisible('delete', $own)->assertTableActionHidden('delete', $shared)
        ->callTableAction('delete', $own)->assertNotified(__('library.deleted'));
    expect(LibraryItem::query()->whereKey($own->id)->exists())->toBeFalse();

    Livewire::test(ListLibraryItems::class)->callAction('add', ['file' => UploadedFile::fake()->createWithContent('sheet.pdf', (string) file_get_contents(lpPdf('d')->getPathname())), 'title' => ['en' => 'Uploaded'], 'tags' => ['Intro'], 'scope' => 'mine'])->assertNotified(__('library.added'));
    $added = LibraryItem::query()->where('owner_id', $mine->id)->firstOrFail();
    expect($added->kind)->toBe('document')->and($added->tags)->toBe(['intro']);
    expect(Storage::disk('local')->allFiles('library/uploads'))->toBe([]); // the staging copy is gone
})->group('T-P3-13', 'A2', 'A3');

it('explains why a used item cannot be deleted from the screen', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'C'], 'slug_ar' => 'c-'.uniqid(), 'slug_en' => 'c-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L'], 'type' => 'text'])->itemable;
    $item = app(AddLibraryItem::class)($instructor, lpPdf('x'), ['title' => ['en' => 'Sheet']]);
    app(AttachLibraryItem::class)->asResource($instructor, $item, $lesson);

    $this->actingAs($instructor);
    Livewire::test(ListLibraryItems::class)->callTableAction('delete', $item)->assertNotified();
    expect(LibraryItem::query()->whereKey($item->id)->exists())->toBeTrue();
})->group('T-P3-13', 'A1');

it('attaches a library material from the lesson editor', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'C'], 'slug_ar' => 'c-'.uniqid(), 'slug_en' => 'c-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $one = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'One'], 'type' => 'text'])->itemable;
    $two = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Two'], 'type' => 'text'])->itemable;
    $item = app(AddLibraryItem::class)($instructor, lpPdf('y'), ['title' => ['en' => 'Shared sheet']]);

    $this->actingAs($instructor);
    foreach ([$one, $two] as $lesson) {
        Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
            ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $lesson->id]), ['title' => ['en' => $lesson->getTranslation('title', 'en')], 'type' => 'text', 'library_resource' => $item->id])
            ->assertHasNoActionErrors();
    }

    expect(LessonResource::query()->where('library_item_id', $item->id)->count())->toBe(2)->and($item->refresh()->referenceCount())->toBe(2);
})->group('T-P3-13', 'A1');
