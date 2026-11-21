<?php

// T-P9-MG | Staff list pages: search, filter, sort and pagination on real Filament list pages | A1

declare(strict_types=1);

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\HelpRequests\Pages\ListHelpRequests;
use App\Filament\Resources\ManualGrants\Pages\ListManualGrants;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $this->admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('super_admin');
    $this->actingAs($this->admin);
});

/** A fresh list component, used to clear table filters between assertions. */
function slm_fresh($component)
{
    return Livewire::test(get_class($component->instance()))->set('tableRecordsPerPage', 50);
}

/**
 * Pagination facts for a Filament table: rows on page 1, rows on page 2, and the total.
 *
 * @return array{page1: int, page2: int, total: int, perPage: int}
 */
function slm_pages(string $class): array
{
    // A default-size page (no wide page option), so the paginator really splits the rows.
    $component = Livewire::test($class);
    $first = $component->instance()->getTableRecords();
    $component->call('gotoPage', 2, 'page');
    $second = $component->instance()->getTableRecords();

    return [
        'page1' => $first->count(),
        'page2' => $second->count(),
        'total' => $first->total(),
        'perPage' => $first->perPage(),
    ];
}

it('course list: search by title, filter by access mode, sort by title and paginate', function (): void {
    $free = [];
    $paid = [];
    foreach (range(1, 6) as $i) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $free[] = Course::query()->create([
            'title' => ['en' => 'Slm Course Alpha '.$n, 'ar' => 'Slm Course Alpha '.$n],
            'slug_ar' => 'slm-a-ar-'.$n.'-'.uniqid(), 'slug_en' => 'slm-a-en-'.$n.'-'.uniqid(),
            'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
        ]);
        $paid[] = $course = Course::query()->create([
            'title' => ['en' => 'Slm Course Beta '.$n, 'ar' => 'Slm Course Beta '.$n],
            'slug_ar' => 'slm-b-ar-'.$n.'-'.uniqid(), 'slug_en' => 'slm-b-en-'.$n.'-'.uniqid(),
            'status' => 'published', 'access_mode' => Course::ACCESS_PAID,
        ]);
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);
    }

    $component = Livewire::test(ListCourses::class)->set('tableRecordsPerPage', 50);

    $component->searchTable('Slm Course Beta 03')
        ->assertCanSeeTableRecords([$paid[2]])
        ->assertCanNotSeeTableRecords(array_merge($free, [$paid[0], $paid[1], $paid[3], $paid[4], $paid[5]]));
    $component->searchTable(null);

    $component->filterTable('access_mode', Course::ACCESS_FREE)
        ->assertCanSeeTableRecords($free)
        ->assertCanNotSeeTableRecords($paid);
    $component = slm_fresh($component);

    $sorted = collect(array_merge($free, $paid))->sortBy(fn (Course $c) => $c->getTranslation('title', 'en'))->values()->all();
    $component->sortTable('title', 'asc')->assertCanSeeTableRecords($sorted, inOrder: true);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(12)
        ->and($pages['page1'])->toBe(min(12, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(12);
})->group('T-P9-MG');

// Pagination: 12 created staff plus the signed-in super admin, so the total is 13.
it('staff users list: search by name, filter by role, sort by joined date and paginate', function (): void {
    $instructorRole = Role::findByName('instructor_academy');
    $alpha = [];
    $beta = [];
    foreach (range(1, 6) as $i) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $alpha[] = User::factory()->create([
            'name' => 'Slm Staff Alpha '.$n, 'email' => 'slm-alpha-'.$n.'@example.test', 'status' => 'active',
            'created_at' => now()->subMinutes(30 - $i),
        ])->assignRole('instructor_academy');
        $beta[] = User::factory()->create([
            'name' => 'Slm Staff Beta '.$n, 'email' => 'slm-beta-'.$n.'@example.test', 'status' => 'active',
            'created_at' => now()->subMinutes(10 - $i),
        ])->assignRole('admin');
    }

    $component = Livewire::test(ListStaffUsers::class)->set('tableRecordsPerPage', 50);

    $component->searchTable('Slm Staff Beta 04')
        ->assertCanSeeTableRecords([$beta[3]])
        ->assertCanNotSeeTableRecords(array_merge($alpha, [$beta[0], $beta[1], $beta[2], $beta[4], $beta[5]]));
    $component->searchTable(null);

    $component->filterTable('role', $instructorRole->id)
        ->assertCanSeeTableRecords($alpha)
        ->assertCanNotSeeTableRecords($beta);
    $component = slm_fresh($component);

    $ordered = array_merge($alpha, $beta);
    $component->sortTable('created_at', 'asc')->assertCanSeeTableRecords($ordered, inOrder: true);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(13)
        ->and($pages['page1'])->toBe(min(13, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(13);
})->group('T-P9-MG');

it('orders list: search by reference, filter by status, sort by reference and created date and paginate', function (): void {
    $course = Course::query()->create([
        'title' => ['en' => 'Slm Order Course', 'ar' => 'Slm Order Course'],
        'slug_ar' => 'slm-order-ar-'.uniqid(), 'slug_en' => 'slm-order-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_PAID,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);

    $orders = [];
    foreach (range(1, 12) as $i) {
        $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
        $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'slm-order-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        if ($i % 2 === 0) {
            $order = app(TransitionOrder::class)($order, OrderStatus::Cancelled, $this->admin);
        }
        // Distinct created_at per order so the in-order sort checks have one correct answer.
        Order::query()->whereKey($order->id)->update(['created_at' => now()->subMinutes(20 - $i)]);
        $orders[$i] = $order;
    }

    $component = Livewire::test(ListOrders::class)->set('tableRecordsPerPage', 50);

    $component->searchTable($orders[3]->reference)
        ->assertCanSeeTableRecords([$orders[3]])
        ->assertCanNotSeeTableRecords([$orders[1], $orders[2], $orders[4]]);
    $component->searchTable(null);

    $pending = array_values(array_filter($orders, fn ($o, $i) => $i % 2 === 1, ARRAY_FILTER_USE_BOTH));
    $cancelled = array_values(array_filter($orders, fn ($o, $i) => $i % 2 === 0, ARRAY_FILTER_USE_BOTH));
    $component->filterTable('status', OrderStatus::Pending->value)
        ->assertCanSeeTableRecords($pending)
        ->assertCanNotSeeTableRecords($cancelled);
    $component = slm_fresh($component);

    $byReference = collect($orders)->sortBy(fn ($o) => $o->reference)->values()->all();
    $component->sortTable('reference', 'asc')->assertCanSeeTableRecords($byReference, inOrder: true);
    $component->sortTable('reference', 'desc')->assertCanSeeTableRecords(array_reverse($byReference), inOrder: true);
    $component = slm_fresh($component);

    $byCreated = array_values($orders);
    $component->sortTable('created_at', 'asc')->assertCanSeeTableRecords($byCreated, inOrder: true);
    $component->sortTable('created_at', 'desc')->assertCanSeeTableRecords(array_reverse($byCreated), inOrder: true);
    $component = slm_fresh($component);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(12)
        ->and($pages['page1'])->toBe(min(12, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(12);
})->group('T-P9-MG');

it('enrollment grants list: search by learner email, filter by source, sort by start date and paginate', function (): void {
    $course = Course::query()->create([
        'title' => ['en' => 'Slm Grant Course', 'ar' => 'Slm Grant Course'],
        'slug_ar' => 'slm-grant-ar-'.uniqid(), 'slug_en' => 'slm-grant-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_PAID,
    ]);

    $grants = [];
    foreach (range(1, 12) as $i) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $learner = User::factory()->create(['status' => 'active', 'email' => 'slm-learner-'.$n.'@example.test'])->assignRole('student');
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'slm-grant-'.$n, null, null, $this->admin);
        $grants[$i] = Entitlement::query()->where('learner_id', $learner->id)->firstOrFail();
        Entitlement::query()->whereKey($grants[$i]->id)->update(['starts_at' => now()->subMinutes(20 - $i)]);
    }

    $component = Livewire::test(ListManualGrants::class)->set('tableRecordsPerPage', 50);

    $component->searchTable('slm-learner-07@example.test')
        ->assertCanSeeTableRecords([$grants[7]])
        ->assertCanNotSeeTableRecords([$grants[1], $grants[6], $grants[8]]);
    $component->searchTable(null);

    $component->filterTable('source_type', EntitlementSource::ManualGrant->value)
        ->assertCanSeeTableRecords(array_values($grants))
        ->assertCanNotSeeTableRecords([]);
    $component->filterTable('source_type', EntitlementSource::Free->value)
        ->assertCanNotSeeTableRecords(array_values($grants));
    $component = slm_fresh($component);

    $component->sortTable('starts_at', 'asc')->assertCanSeeTableRecords(array_values($grants), inOrder: true);
    $component->sortTable('starts_at', 'desc')->assertCanSeeTableRecords(array_reverse(array_values($grants)), inOrder: true);
    $component = slm_fresh($component);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(12)
        ->and($pages['page1'])->toBe(min(12, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(12);
})->group('T-P9-MG');

it('help request list: search by student name, filter by status, sort by created and due date and paginate', function (): void {
    $course = Course::query()->create([
        'title' => ['en' => 'Slm Help Course', 'ar' => 'Slm Help Course'],
        'slug_ar' => 'slm-help-ar-'.uniqid(), 'slug_en' => 'slm-help-en-'.uniqid(),
        'status' => 'published', 'ai_enabled' => true,
    ]);
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Sec']]);
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Les'], 'type' => 'video'])->itemable;

    $requests = [];
    foreach (range(1, 12) as $i) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $student = User::factory()->create(['status' => 'active', 'name' => 'Slm Student '.$n])->assignRole('student');
        $status = $i % 2 === 0 ? HelpRequestStatus::InstructorReplied : HelpRequestStatus::New;

        $conv = AiConversation::query()->create([
            'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'document_version' => 1, 'locale' => 'en', 'style_tier' => 'standard', 'video_position' => 60, 'status' => 'active',
        ]);
        $msg = AiMessage::query()->create([
            'conversation_id' => $conv->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'Sample AI Answer',
            'status' => 'answered', 'source_pages' => [1], 'prompt_version' => 1, 'model' => 'fake',
        ]);
        $requests[$i] = HelpRequest::query()->create([
            'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'assigned_instructor_id' => $instructor->id, 'conversation_id' => $conv->id, 'ai_message_id' => $msg->id,
            'status' => $status, 'locale' => 'en', 'video_position' => 60, 'document_version' => 1,
            'ai_status' => 'answered', 'source_pages' => [1], 'question' => 'Sample Question',
            'ai_answer' => 'Sample AI Answer', 'due_at' => now()->addHours(24),
        ]);
    }

    // Distinct created_at and due_at per request so the in-order sort checks have one correct answer.
    foreach ($requests as $i => $request) {
        HelpRequest::query()->whereKey($request->id)->update([
            'created_at' => now()->subMinutes(20 - $i),
            'due_at' => now()->addHours(48 - $i),
        ]);
    }

    $component = Livewire::test(ListHelpRequests::class)->set('tableRecordsPerPage', 50);

    $component->searchTable('Slm Student 05')
        ->assertCanSeeTableRecords([$requests[5]])
        ->assertCanNotSeeTableRecords([$requests[1], $requests[4], $requests[6]]);
    $component->searchTable(null);

    $newOnes = array_values(array_filter($requests, fn ($r, $i) => $i % 2 === 1, ARRAY_FILTER_USE_BOTH));
    $replied = array_values(array_filter($requests, fn ($r, $i) => $i % 2 === 0, ARRAY_FILTER_USE_BOTH));
    $component->filterTable('status', HelpRequestStatus::New->value)
        ->assertCanSeeTableRecords($newOnes)
        ->assertCanNotSeeTableRecords($replied);
    $component = slm_fresh($component);

    $component->sortTable('created_at', 'asc')->assertCanSeeTableRecords(array_values($requests), inOrder: true);
    $component->sortTable('created_at', 'desc')->assertCanSeeTableRecords(array_reverse(array_values($requests)), inOrder: true);
    $component = slm_fresh($component);

    // due_at is 48 - i hours away, so ascending due date is the reverse of creation order.
    $component->sortTable('due_at', 'asc')->assertCanSeeTableRecords(array_reverse(array_values($requests)), inOrder: true);
    $component->sortTable('due_at', 'desc')->assertCanSeeTableRecords(array_values($requests), inOrder: true);
    $component = slm_fresh($component);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(12)
        ->and($pages['page1'])->toBe(min(12, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(12);
})->group('T-P9-MG');

it('settlement batches list: search by reference, filter by status, sort by created date and paginate', function (): void {
    $settlements = [];
    foreach (range(1, 12) as $i) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $settlements[$i] = Settlement::query()->create([
            'reference' => 'SLM-S-'.$n,
            'request_key' => 'slm-settle-'.$n,
            'currency' => 'EGP',
            'status' => $i % 2 === 0 ? SettlementStatus::Cancelled->value : SettlementStatus::Draft->value,
            'instructor_count' => 1,
            'entry_count' => 0,
            'total_minor' => 0,
            'created_by' => $this->admin->id,
            'created_at' => Carbon::now()->subMinutes(20 - $i),
            'updated_at' => Carbon::now()->subMinutes(20 - $i),
        ]);
    }

    $component = Livewire::test(ListSettlements::class)->set('tableRecordsPerPage', 50);

    $component->searchTable('SLM-S-06')
        ->assertCanSeeTableRecords([$settlements[6]])
        ->assertCanNotSeeTableRecords([$settlements[5], $settlements[7]]);
    $component->searchTable(null);

    $draft = array_values(array_filter($settlements, fn ($s, $i) => $i % 2 === 1, ARRAY_FILTER_USE_BOTH));
    $cancelled = array_values(array_filter($settlements, fn ($s, $i) => $i % 2 === 0, ARRAY_FILTER_USE_BOTH));
    $component->filterTable('status', SettlementStatus::Draft->value)
        ->assertCanSeeTableRecords($draft)
        ->assertCanNotSeeTableRecords($cancelled);
    $component = slm_fresh($component);

    $component->sortTable('created_at', 'asc')->assertCanSeeTableRecords(array_values($settlements), inOrder: true);

    $pages = slm_pages(get_class($component->instance()));
    expect($pages['total'])->toBe(12)
        ->and($pages['page1'])->toBe(min(12, $pages['perPage']))
        ->and($pages['page1'] + $pages['page2'])->toBe(12);
})->group('T-P9-MG');
