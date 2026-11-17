<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\PublishCourse;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Settings\PlatformFlag;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function directoryCourse(string $slug, array $attributes = []): Course
{
    $category = Category::query()->firstOrCreate(['slug_en' => 'studio'], [
        'slug_ar' => 'استديو', 'name' => ['ar' => 'استديو', 'en' => 'Studio'],
    ]);

    return Course::query()->create([
        'title' => ['ar' => 'دورة الرسم', 'en' => ucwords(str_replace('-', ' ', $slug))],
        'slug_en' => $slug, 'slug_ar' => 'دورة-'.$slug, 'category_id' => $category->id,
        ...$attributes,
    ]);
}

it('scopes instructor listings, detail URLs and update authorization to assignments', function () {
    $instructor = User::factory()->create(['locale' => 'en'])->assignRole('instructor_academy');
    $other = User::factory()->create()->assignRole('instructor_academy');
    $assigned = directoryCourse('assigned-workshop');
    $outside = directoryCourse('outside-workshop');
    $assigned->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => 'manage']);
    $outside->instructors()->attach($other, ['role' => 'instructor', 'scope' => 'manage']);

    $this->actingAs($instructor)->get('/instructor/courses')->assertOk()
        ->assertSee('Assigned Workshop')->assertDontSee('Outside Workshop');
    $this->get('/instructor/courses/'.$assigned->id)->assertOk();
    $this->get('/instructor/courses/'.$outside->id)->assertNotFound();
    $viewOnly = directoryCourse('view-only');
    $viewOnly->instructors()->attach($instructor);
    $this->get('/instructor/courses/'.$viewOnly->id)->assertOk();
    expect(Gate::allows('update', $assigned))->toBeTrue()
        ->and(Gate::allows('update', $viewOnly))->toBeFalse()
        ->and(Gate::allows('update', $outside))->toBeFalse();

    Filament::setCurrentPanel(Filament::getPanel('instructor'));
    Livewire::test(ListCourses::class)
        ->assertCanSeeTableRecords([$assigned, $viewOnly])->assertCanNotSeeTableRecords([$outside])
        ->searchTable('assigned')->assertCanSeeTableRecords([$assigned])->assertCanNotSeeTableRecords([$viewOnly, $outside]);
})->group('T-P3-01', 'A1', 'I02', 'D-007');

it('denies independent instructor publication even when granted publish permission', function () {
    $instructor = User::factory()->create()->assignRole('instructor_independent')->givePermissionTo('course.publish');
    $academy = User::factory()->create()->assignRole('admin');
    $course = directoryCourse('independent-workshop', ['owner_type' => 'independent', 'owner_instructor_id' => $instructor->id]);
    $course->instructors()->attach($instructor, ['role' => 'owner', 'scope' => 'manage']);

    $this->actingAs($instructor);
    expect(fn () => app(PublishCourse::class)($course))->toThrow(AuthorizationException::class);
    expect($course->fresh()->status)->toBe(CourseStatus::Draft);

    $this->actingAs($academy);
    app(PublishCourse::class)($course);
    expect($course->fresh()->status)->toBe(CourseStatus::Published);
})->group('T-P3-01', 'A2', 'D-007');

it('shows visible course counts from the database and keeps the AI hook behind the global flag', function () {
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('admin'));
    $published = directoryCourse('paint-basics', ['status' => CourseStatus::Published, 'access_mode' => 'free']);
    directoryCourse('acting-intermediate', ['status' => CourseStatus::Published, 'access_mode' => 'paid']);
    directoryCourse('animation-short', ['status' => CourseStatus::Upcoming, 'access_mode' => 'paid']);
    directoryCourse('voice-intro', ['status' => CourseStatus::Suspended, 'access_mode' => 'free']);
    directoryCourse('draft-workshop');

    $response = $this->get('/admin/courses')->assertOk();
    $document = new DOMDocument;
    $document->loadHTML((string) $response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $html = new DOMXPath($document);
    $values = [];
    foreach ($html->query('//*[contains(concat(" ", normalize-space(@class), " "), " yg-summary-item ")]') as $card) {
        $values[] = implode(' ', preg_split('/\s+/', trim($card->textContent)) ?: []);
    }
    expect($values)->toBe(['Active 2', 'Paid 2', 'Free 2', 'Upcoming 1', 'Suspended 1']);
    expect($published->fresh()->ai_enabled)->toBeTrue()
        ->and($published->fresh()->hasAiEnabled())->toBeFalse();
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    expect($published->fresh()->hasAiEnabled())->toBeTrue();
    $published->update(['ai_enabled' => false]);
    expect($published->fresh()->hasAiEnabled())->toBeFalse();
})->group('T-P3-01', 'A3');
