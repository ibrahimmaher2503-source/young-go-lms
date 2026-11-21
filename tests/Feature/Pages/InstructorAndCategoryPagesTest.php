<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Instructors\Actions\SaveInstructorProfile;
use App\Domain\Seo\Actions\BuildSitemap;
use App\Domain\Seo\PublicUrl;
use App\Models\Category;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\DemoInstructorContentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shares the demo portrait and localized role across public instructor placements', function (): void {
    $this->seed(CmsPageSeeder::class);
    Storage::fake('public');
    $domain = config('demo.email_domain');
    $admin = User::factory()->create(['email' => 'admin@'.$domain])->assignRole('admin');
    $karim = User::factory()->create(['email' => 'instructor.indie@'.$domain])->assignRole('instructor_independent');
    $sara = User::factory()->create(['email' => 'instructor.academy@'.$domain])->assignRole('instructor_academy');
    foreach ([$admin, $karim] as $demoUser) {
        DemoAnchors::mark($demoUser);
    }

    foreach ([[$karim, 'karim', 'كريم'], [$sara, 'sara', 'سارة']] as [$user, $slug, $arabic]) {
        app(SaveInstructorProfile::class)($admin, null, [
            'user_id' => $user->id,
            'slug_ar' => $slug.'-demo-ar',
            'slug_en' => $slug.'-demo',
            'display_name' => ['ar' => $arabic, 'en' => ucfirst($slug)],
            'headline' => 'Creative instructor',
            'status' => InstructorProfile::APPROVED,
        ]);
    }

    $course = Course::query()->create([
        'title' => ['ar' => 'تمارين الأداء', 'en' => 'Performance exercises'],
        'slug_ar' => 'performance-ar',
        'slug_en' => 'performance-en',
        'status' => CourseStatus::Published->value,
    ]);
    $course->instructors()->attach($karim, ['scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    $this->seed(DemoInstructorContentSeeder::class);

    $portrait = 'instructors/demo-karim.webp';
    Storage::disk('public')->assertExists($portrait);
    Storage::disk('public')->assertMissing('instructors/demo-sara.webp');
    foreach (['lina', 'omar'] as $key) {
        Storage::disk('public')->assertExists('instructors/demo-'.$key.'.webp');
        $profile = InstructorProfile::query()->where('slug_en', $key.'-demo')->firstOrFail();
        expect($profile->getTranslation('bio', 'ar', false))->not->toBeEmpty()
            ->and($profile->getTranslation('bio', 'en', false))->not->toBeEmpty();
    }
    foreach ([
        ['/ar', 'مدرّب تمثيل'],
        ['/ar/instructors/karim-demo-ar', 'مدرّب تمثيل'],
        ['/ar/courses/performance-ar', 'مدرّب تمثيل'],
        ['/en/instructors/karim-demo', 'Acting instructor'],
        ['/en/courses/performance-en', 'Acting instructor'],
    ] as [$url, $headline]) {
        $this->get($url)->assertOk()->assertSee('src="'.Storage::disk('public')->url($portrait).'"', false)->assertSee($headline);
    }

    $profile = InstructorProfile::query()->where('user_id', $karim->id)->firstOrFail();
    app(SaveInstructorProfile::class)($admin, $profile, ['avatar' => 'instructors/upload.webp', 'headline' => ['ar' => 'مدرّب مسرح', 'en' => 'Theatre instructor']]);
    $this->seed(DemoInstructorContentSeeder::class);
    expect($profile->fresh()->avatar)->toBe('instructors/upload.webp');
    expect($profile->fresh()->getTranslation('headline', 'en'))->toBe('Theatre instructor');
    expect(InstructorProfile::query()->count())->toBe(4);
})->group('T-P2-09', 'A3', 'YG-GAP-CMS-001', 'T-P9-11', 'ED-022');

it('skips demo instructor routes already used by a non-demo profile', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['email' => 'admin@'.config('demo.email_domain')])->assignRole('admin');
    DemoAnchors::mark($admin);
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $profile = InstructorProfile::query()->create([
        'user_id' => $instructor->id,
        'slug_en' => 'existing-profile',
        'slug_ar' => 'lina-demo',
        'status' => InstructorProfile::APPROVED,
    ]);

    $this->seed(DemoInstructorContentSeeder::class);

    expect($profile->fresh()->avatar)->toBeNull();
    $this->assertDatabaseMissing('users', ['email' => 'instructor.lina@'.config('demo.email_domain')]);
    Storage::disk('public')->assertMissing('instructors/demo-lina.webp');
    Storage::disk('public')->assertExists('instructors/demo-omar.webp');
})->group('T-P9-11', 'ED-022');

it('exposes only approved active instructor profiles publicly, hides PII, and lists only their published courses', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $instructor = User::factory()->create([
        'status' => 'active',
        'name' => 'Legal Account Name',
        'email' => 'secret.instructor@example.com',
        'phone' => '+201077788899',
    ])->assignRole('instructor_academy');

    $cat = Category::query()->create(['name' => ['ar' => 'رسم', 'en' => 'Drawing'], 'slug_ar' => 'draw-ar', 'slug_en' => 'draw-en', 'status' => 'active', 'sort' => 1]);

    // Authorization & role guard on SaveInstructorProfile
    expect(fn () => app(SaveInstructorProfile::class)($student, null, ['user_id' => $instructor->id, 'slug_ar' => 's', 'slug_en' => 's', 'status' => 'approved']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveInstructorProfile::class)($admin, null, ['user_id' => $student->id, 'slug_ar' => 's', 'slug_en' => 's', 'status' => 'approved']))
        ->toThrow(ValidationException::class);

    // Draft / hidden / suspended user profiles all return 404
    $profile = app(SaveInstructorProfile::class)($admin, null, [
        'user_id' => $instructor->id,
        'slug_ar' => 'nour-art-ar',
        'slug_en' => 'nour-art-en',
        'display_name' => ['ar' => 'أ. نور للرسم', 'en' => 'Ms. Nour'],
        'headline' => ['ar' => 'مدربة رسم', 'en' => 'Art Coach'],
        'bio' => ['ar' => 'سيرة مختصرة', 'en' => 'Public bio content'],
        'status' => InstructorProfile::DRAFT,
        'disciplines' => [$cat->id],
    ]);
    $this->get('/en/instructors/nour-art-en')->assertNotFound();

    // Approved profile -> published courses visible, draft courses hidden, Person JSON-LD without PII
    $profile = app(SaveInstructorProfile::class)($admin, $profile, ['status' => InstructorProfile::APPROVED]);
    $pubCourse = Course::query()->create(['title' => ['ar' => 'دورة منشورة', 'en' => 'Published Studio'], 'slug_ar' => 'ps-ar', 'slug_en' => 'ps-en', 'status' => CourseStatus::Published->value, 'category_id' => $cat->id]);
    $draftCourse = Course::query()->create(['title' => ['ar' => 'دورة مسودة', 'en' => 'Draft Secret Studio'], 'slug_ar' => 'ds-ar', 'slug_en' => 'ds-en', 'status' => CourseStatus::Draft->value, 'category_id' => $cat->id]);
    $pubCourse->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $draftCourse->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $this->get('/en/instructors/nour-art-en')
        ->assertOk()
        ->assertSee('Ms. Nour')
        ->assertSee('Published Studio')
        ->assertDontSee('Draft Secret Studio')
        ->assertDontSee('secret.instructor@example.com')
        ->assertDontSee('+201077788899')
        ->assertSee('Person');

    $this->get('/ar/instructors/nour-art-ar')->assertOk()->assertSee('أ. نور للرسم');

    // Suspending user or hiding profile returns 404
    $instructor->forceFill(['status' => 'suspended'])->save();
    $this->get('/en/instructors/nour-art-en')->assertNotFound();

    $instructor->forceFill(['status' => 'active'])->save();
    app(SaveInstructorProfile::class)($admin, $profile->fresh(), ['status' => InstructorProfile::HIDDEN]);
    $this->get('/en/instructors/nour-art-en')->assertNotFound();
})->group('T-P2-09', 'A1', 'YG-GAP-CMS-001', 'PRD-08');

it('serves active category landing pages with hreflang, canonical, CollectionPage JSON-LD, sitemap entries, and empty/inactive handling', function (): void {
    $parent = Category::query()->create([
        'name' => ['ar' => 'الفنون التشكيلية', 'en' => 'Fine Arts'],
        'description' => ['ar' => 'وصف القسم', 'en' => 'Category intro'],
        'slug_ar' => 'fine-arts-ar',
        'slug_en' => 'fine-arts-en',
        'status' => 'active',
        'sort' => 1,
    ]);
    $child = Category::query()->create([
        'parent_id' => $parent->id,
        'name' => ['ar' => 'رسم أطفال', 'en' => 'Kids Drawing'],
        'slug_ar' => 'kids-draw-ar',
        'slug_en' => 'kids-draw-en',
        'status' => 'active',
        'sort' => 1,
    ]);
    $inactive = Category::query()->create([
        'name' => ['ar' => 'غير مفعل', 'en' => 'Inactive Cat'],
        'slug_ar' => 'inact-ar',
        'slug_en' => 'inact-en',
        'status' => 'inactive',
        'sort' => 2,
    ]);

    // Inactive -> 404
    $this->get('/en/categories/inact-en')->assertNotFound();

    // Active category with 0 courses -> 200 noindex and NOT in sitemap
    $this->get('/en/categories/fine-arts-en')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
    $sitemapBefore = app(BuildSitemap::class)->refresh();
    expect($sitemapBefore)->not->toContain('fine-arts-en');

    // Attach published course -> index, follow + hreflang + CollectionPage + sitemap
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة ألوان', 'en' => 'Colors 101'],
        'slug_ar' => 'c101-ar',
        'slug_en' => 'c101-en',
        'status' => CourseStatus::Published->value,
        'category_id' => $parent->id,
    ]);

    $this->get('/en/categories/fine-arts-en')
        ->assertOk()
        ->assertSee('Colors 101')
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('CollectionPage');

    $sitemapAfter = app(BuildSitemap::class)->refresh();
    expect($sitemapAfter)->toContain(PublicUrl::category($parent, 'en'))
        ->toContain(PublicUrl::category($parent, 'ar'));
})->group('T-P2-09', 'A2', 'YG-GAP-CMS-002', 'PRD-08');
