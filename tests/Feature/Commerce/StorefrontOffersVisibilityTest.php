<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Seo\PublicUrl;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows subscribe CTA and plan/bundle offers on the public course page when covered by active plans or bundles', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    $subCourse = Course::query()->create([
        'title' => ['ar' => 'دورة اشتراك فقط', 'en' => 'Subscription Only Course'],
        'slug_ar' => 'sub-only-ar', 'slug_en' => 'sub-only-en', 'status' => 'published', 'access_mode' => Course::ACCESS_SUBSCRIPTION,
    ]);
    $bothCourse = Course::query()->create([
        'title' => ['ar' => 'دورة مشتركة', 'en' => 'Both Mode Course'],
        'slug_ar' => 'both-ar', 'slug_en' => 'both-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH,
    ]);
    CoursePrice::query()->create(['course_id' => $bothCourse->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 30000]);

    app(SavePlan::class)($admin, [
        'name_ar' => 'الخطة الإبداعية', 'name_en' => 'Creative Plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 19900], 'course_ids' => [$subCourse->id, $bothCourse->id],
    ]);
    $bundle = app(SaveBundle::class)($admin, [
        'name_ar' => 'باقة التمثيل والرسم', 'name_en' => 'Acting & Drawing Bundle', 'is_active' => true,
        'prices' => [Market::Egypt->value => 45000],
        'items' => [['course_id' => $subCourse->id, 'weight' => 1], ['course_id' => $bothCourse->id, 'weight' => 1]],
    ]);

    // Signed-in learner on subscription-only course sees subscribe CTA instead of disabled soon button
    $this->actingAs($student)->get(route('catalog.show', $subCourse))
        ->assertOk()
        ->assertSee('data-cta="subscribe"', false)
        ->assertSee('Creative Plan')
        ->assertSee('Acting & Drawing Bundle')
        ->assertSee(PublicUrl::bundle($bundle, 'en'), false);

    // Signed-in learner on both-mode course sees buy CTA AND plan/bundle links
    $this->actingAs($student)->get(route('catalog.show', $bothCourse))
        ->assertOk()
        ->assertSee('data-cta="buy"', false)
        ->assertSee('data-course-plans', false)
        ->assertSee('data-course-bundles', false);
});

it('lists active bundles on the public plans page and links to offers from the catalog when plans or bundles exist', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $c1 = Course::query()->create(['title' => ['ar' => 'دورة ١', 'en' => 'Course 1'], 'slug_ar' => 'c1-ar', 'slug_en' => 'c1-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH]);
    $c2 = Course::query()->create(['title' => ['ar' => 'دورة ٢', 'en' => 'Course 2'], 'slug_ar' => 'c2-ar', 'slug_en' => 'c2-en', 'status' => 'published', 'access_mode' => Course::ACCESS_BOTH]);

    app(SavePlan::class)($admin, [
        'name_ar' => 'خطة الشاملة', 'name_en' => 'All Access Plan', 'term' => 'annual', 'is_active' => true,
        'prices' => [Market::Egypt->value => 99000], 'course_ids' => [$c1->id],
    ]);
    $bundle = app(SaveBundle::class)($admin, [
        'name_ar' => 'باقة البداية', 'name_en' => 'Starter Bundle', 'is_active' => true,
        'prices' => [Market::Egypt->value => 35000],
        'items' => [['course_id' => $c1->id, 'weight' => 1], ['course_id' => $c2->id, 'weight' => 1]],
    ]);

    $this->get(route('plans.index'))
        ->assertOk()
        ->assertSee('All Access Plan')
        ->assertSee('Starter Bundle')
        ->assertSee(PublicUrl::bundle($bundle, 'en'), false);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('data-catalog-offers', false)
        ->assertSee(PublicUrl::plans('en'), false);
});
