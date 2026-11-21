<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\InstructorFinance\Actions\GenerateInstructorStatement;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use RuntimeException;

/**
 * T-P9-11: the demo data the acceptance journeys J05, J06, J10, J12 (and the J01 coupon) need on top of DemoSeeder. Called
 * at the end of DemoSeeder::run(), so `demo:seed` and `demo:reset` apply it and a production run is refused by DemoGuard.
 * Everything goes through the domain Actions; the few direct writes (the flag, the AI provider choice, the order dates) are
 * demo-only and explained where they happen. Fake data only: no real account number, provider key or address.
 */
final class DemoAcceptanceSeeder extends Seeder
{
    private User $admin;

    private User $indie;

    private User $academy;

    /** @var array<string, Course> */
    private array $courses = [];

    public function run(): void
    {
        DemoGuard::assertAllowed();
        Config::set('mail.default', 'array');
        Config::set('queue.default', 'sync');

        $this->load();
        Auth::setUser($this->admin);

        DB::transaction(function (): void {
            $this->assistant();
            $this->liveSessions();
            $this->payouts();
            $this->revision();
            $this->coupon();
        });
    }

    private function load(): void
    {
        $user = fn (string $key): User => User::query()->where('email', $key.'@'.Config::string('demo.email_domain'))->first()
            ?? throw new RuntimeException('Run DemoSeeder first: the demo account '.$key.' is missing.');
        $this->admin = $user('admin');
        $this->indie = $user('instructor.indie');
        $this->academy = $user('instructor.academy');

        $anchored = Course::query()->whereIn('id', DemoAnchors::ids('courses'))->get();
        // English titles of the DemoSeeder courses this seeder uses; kept local so it does not depend on DemoSeeder internals.
        $titles = ['stage-workshop' => 'Live Stage Workshop', 'indie-comics' => 'Making Comics', 'acting-101' => 'Introduction to Acting'];
        foreach ($titles as $key => $title) {
            $course = $anchored->first(static fn (Course $c): bool => $c->getTranslation('title', 'en') === $title);
            if ($course instanceof Course) {
                $this->courses[$key] = $course;
            }
        }
    }

    private function course(string $key): Course
    {
        return $this->courses[$key] ?? throw new RuntimeException('The demo course '.$key.' is missing.');
    }

    /**
     * J05: switch the lesson assistant on with the built-in FAKE provider, and only with it. The gated-flag Action needs a
     * super admin (the demo has none) and the gate ability is deliberately undefined in production, so the demo writes the
     * flag itself, with an approval row for traceability. This runs only behind DemoGuard. If the fake provider is not the
     * active one (staging: set AI_PROVIDER=fake) or a real provider is configured, the flag stays off. `ai.minors` is
     * forced off; eligibility still lets in only adult learners with an approved PDF (Omar in the demo).
     */
    private function assistant(): void
    {
        $registry = app(ProviderRegistry::class);
        if (in_array(ProviderRegistry::FAKE, $registry->keys(), true)) {
            $settings = app(AiSettings::class);
            $settings->provider = ProviderRegistry::FAKE;
            $settings->fallback_provider = null;
            $settings->save();
        }

        Feature::for(null)->deactivate(PlatformFlag::AiMinors->value);
        if (ProviderRegistry::currentKey() !== ProviderRegistry::FAKE) {
            Feature::for(null)->deactivate(PlatformFlag::AiAssistant->value);
            $this->command?->warn('Demo assistant left off: the fake AI provider is not active here (set AI_PROVIDER=fake). A real provider is never used.');

            return;
        }

        DB::table('flag_approvals')->insert([
            'flag' => PlatformFlag::AiAssistant->value, 'approval_ref' => 'demo-seed: fake provider only', 'approved_by' => $this->admin->getKey(), 'at' => now(),
        ]);
        Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    }

    /**
     * J06: a long session of the live workshop, relative to the moment of the reset: the join button appears 5 minutes after
     * it and the session runs 8 hours, so the demo works for a working day. DemoSeeder's own sessions stay (one that ends
     * an hour after the reset, and the weekly one next week).
     */
    private function liveSessions(): void
    {
        $course = $this->course('stage-workshop');
        $section = CourseSection::query()->where('course_id', $course->getKey())->orderBy('sort')->firstOrFail();
        $cohort = $course->cohorts()->sole();

        $item = app(ScheduleLiveSession::class)($this->academy, $section, [
            'title' => ['ar' => 'استوديو مفتوح: تدريب اليوم الكامل', 'en' => 'Open studio: full-day rehearsal'],
            'description' => ['ar' => 'جلسة طويلة للتدريب الحر مع المدرّب.', 'en' => 'A long open rehearsal session with the instructor.'],
            'starts_at' => now('Africa/Cairo')->addMinutes(45)->format('Y-m-d H:i:s'),
            'ends_at' => now('Africa/Cairo')->addHours(8)->format('Y-m-d H:i:s'),
            'timezone' => 'Africa/Cairo',
            'provider' => 'manual_zoom',
            'meeting_url' => 'https://zoom.example.test/demo-open-studio',
            'join_window_minutes' => 40,
            'cohort_id' => $cohort->getKey(),
            'instructor_id' => $this->academy->getKey(),
        ]);
        app(SetCurriculumStatus::class)($this->academy, $course, [(int) $item->getKey()], 'published');
    }

    /**
     * J10: Karim gets a fake payout profile and enough paid sales for a settlement: three sales of last month (their statement
     * is generated) next to the sale DemoSeeder made this month. The batch minimum is 500 EGP; each 350 EGP sale pays him 70%,
     * so four sales clear it. The sales go through the order Actions; only the payment date is moved into last month.
     * Four-eyes (O-012): the finance user who creates a batch cannot approve it, and the demo has one finance user, so the
     * demo admin also receives the settlement permissions directly (removed with the demo accounts on reset).
     */
    private function payouts(): void
    {
        if (! PayoutProfile::query()->where('user_id', $this->indie->getKey())->exists()) {
            app(SavePayoutProfile::class)($this->indie, [
                'method' => PayoutProfile::BANK, 'holder_name' => 'Karim Demo', 'bank_name' => 'Demo Bank (fictional)', 'account_number' => 'DEMO0000000001',
            ]);
        }

        $comics = $this->course('indie-comics');
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        foreach (['nour', 'omar', 'laila'] as $n => $key) {
            $buyer = User::query()->where('email', 'learner.'.$key.'@'.Config::string('demo.email_domain'))->firstOrFail();
            $order = app(CreateOrder::class)($buyer, Market::Egypt, [new OrderLine(ProductType::Course, (int) $comics->getKey(), $buyer)], 'demo-order-'.(9 + $n));
            if ($order->status === OrderStatus::Pending) {
                // The payment date is the one direct write: the sale happened last month so a closed month has a statement.
                $order->forceFill(['paid_at' => $lastMonth->copy()->addDays(9 + $n * 4)->setTime(12, 0)])->save();
                $order = app(TransitionOrder::class)($order, OrderStatus::Paid);
                app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
                app(RecordOrderEarnings::class)($order);
            }
        }

        app(GenerateInstructorStatement::class)->system((int) $this->indie->getKey(), $lastMonth->format('Y-m'), Market::Egypt->currency());

        $this->admin->givePermissionTo(['settlement.manage', 'panel.finance.access']);
    }

    /** J12: one submitted revision of the published independent course, waiting for an admin. */
    private function revision(): void
    {
        $course = $this->course('indie-comics');
        if (CourseRevision::query()->where('course_id', $course->getKey())->where('status', CourseRevisionStatus::Pending->value)->exists()) {
            return;
        }

        $revision = app(OpenCourseRevision::class)($this->indie, $course);
        $payload = $revision->payload;
        $payload['course']['short_description'] = [
            'ar' => 'ابنِ قصتك المصورة من الفكرة الأولى حتى الغلاف النهائي، مع ورقة عمل جديدة لكل وحدة.',
            'en' => 'Build your own comic from the first idea to the final cover, now with a new worksheet for every module.',
        ];
        $revision = app(SaveCourseRevision::class)($this->indie, $revision, $payload);
        app(SubmitCourseRevision::class)($this->indie, $revision);
    }

    /**
     * J01: DEMO-WELCOME10 allowed one use per learner and Omar had already used it on the live workshop, so it was refused for
     * him on the acting course. Three uses per learner keep the code demonstrable (the code and the discount stay frozen).
     */
    private function coupon(): void
    {
        $coupon = Coupon::query()->where('code', 'DEMO-WELCOME10')->first();
        if (! $coupon instanceof Coupon) {
            return;
        }

        app(SaveCoupon::class)($this->admin, [
            'code' => $coupon->code, 'name' => (string) $coupon->name, 'discount_type' => $coupon->discount_type->value, 'percent' => $coupon->percent,
            'max_redemptions' => $coupon->max_redemptions, 'max_per_user' => 3, 'is_active' => true, 'course_ids' => [],
        ], $coupon);
    }
}
