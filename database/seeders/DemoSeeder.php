<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\AI\Help\Actions\RecordAssistantFeedback;
use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Catalog\Actions\PublishCourse;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Completion\Actions\ApproveCourseCompletion;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Demo\Actions\AttachDemoLessonPdf;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Entitlements\Actions\EnrollInFreeCourse;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\Instructors\Actions\AcceptInstructorAgreement;
use App\Domain\Instructors\Actions\PublishInstructorAgreement;
use App\Domain\Instructors\Actions\SaveInstructorProfile;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * T-P9-11 (ED-022): deterministic bilingual demo dataset for local and staging demos. Run only by `demo:seed` and
 * `demo:reset` (never by DatabaseSeeder). Everything goes through the domain Actions; access rows come from enrollment
 * and order Actions, never from a direct write. Every root record is registered in `demo_anchors` so a reset removes
 * exactly this data. Orders are completed without any payment gateway (no call leaves the process), mail goes to the
 * array mailer, and every address sits on a reserved ".test" domain.
 */
final class DemoSeeder extends Seeder
{
    private User $admin;

    private User $academy;

    private User $indie;

    /** @var array<string, User> */
    private array $learners = [];

    /** @var array<string, Course> */
    private array $courses = [];

    /** @var array<string, list<CurriculumItem>> lesson items per course key */
    private array $lessonItems = [];

    /** @var array<string, CurriculumItem> */
    private array $quizItems = [];

    public int $userCount = 0;

    public function run(): void
    {
        DemoGuard::assertAllowed();

        // Nothing in the demo may reach a real inbox or queue worker.
        Config::set('mail.default', 'array');
        Config::set('queue.default', 'sync');

        $this->call([RolePermissionSeeder::class, AgeBandSeeder::class]);
        if (! Category::query()->exists()) {
            $this->call(CategorySeeder::class);
        }

        DB::transaction(function (): void {
            $this->people();
            $this->agreement();
            $this->catalog();
            $this->commerce();
            $this->learning();
            $this->engagement();
            app(AttachDemoLessonPdf::class)($this->admin, Lesson::query()->findOrFail($this->lessonItems['drawing-basics'][0]->itemable_id));
        });
    }

    /**
     * Publishes the placeholder instructor agreement (demo only; the real wording is an owner/counsel decision) so the
     * agreement page has text to show, and records the independent demo instructor's acceptance so the demo instructor
     * can still create courses.
     */
    private function agreement(): void
    {
        $this->call(InstructorAgreementSeeder::class);
        $agreement = InstructorAgreement::query()->where('version', (string) config('instructor_onboarding.placeholder_agreement_version'))->first();
        if ($agreement === null) {
            return;
        }

        $published = app(PublishInstructorAgreement::class)($this->admin, $agreement);
        app(AcceptInstructorAgreement::class)($this->indie, $published, null);
    }

    private function email(string $key): string
    {
        return $key.'@'.Config::string('demo.email_domain');
    }

    private function password(): string
    {
        return Config::string('demo.password');
    }

    /** @param list<string> $roles */
    private function person(string $key, string $name, string $nameEn, array $roles, string $locale = 'ar'): User
    {
        $user = User::factory()->create([
            'name' => $name, 'name_ar' => $name, 'name_en' => $nameEn, 'display_name' => null,
            'email' => $this->email($key), 'password' => $this->password(), 'locale' => $locale, 'status' => 'active',
        ]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        DemoAnchors::mark($user);
        $this->userCount++;

        return $user;
    }

    private function people(): void
    {
        $this->admin = $this->person('admin', 'مدير العرض', 'Demo Admin', ['admin']);
        $this->person('finance', 'مالية العرض', 'Demo Finance', ['finance']);
        $this->academy = $this->person('instructor.academy', 'سارة المدربة', 'Sara (academy instructor)', ['instructor_academy']);
        $this->indie = $this->person('instructor.indie', 'كريم المستقل', 'Karim (independent instructor)', ['instructor_independent']);
        $this->learners['nour'] = $this->person('learner.nour', 'نور', 'Nour Demo', ['student']);
        $this->learners['omar'] = $this->person('learner.omar', 'عمر', 'Omar Demo', ['student'], 'en');
        $this->learners['omar']->forceFill(['birth_year' => now()->year - 28, 'birth_month' => 1])->save();
        $this->learners['laila'] = $this->person('learner.laila', 'ليلى', 'Layla Demo', ['student']);
        $this->learners['parent'] = $this->person('parent', 'منى (ولي أمر)', 'Mona (parent)', ['parent']);

        Auth::setUser($this->admin);
        $category = (int) Category::query()->orderBy('id')->value('id');
        foreach ([[$this->academy, 'sara-demo', ['ar' => 'سارة', 'en' => 'Sara']], [$this->indie, 'karim-demo', ['ar' => 'كريم', 'en' => 'Karim']]] as [$instructor, $slug, $display]) {
            app(SaveInstructorProfile::class)($this->admin, null, [
                'user_id' => $instructor->getKey(), 'slug_ar' => $slug.'-ar', 'slug_en' => $slug, 'display_name' => $display,
                'headline' => 'Creative instructor', 'bio' => 'Demo instructor profile.', 'status' => InstructorProfile::APPROVED,
                'disciplines' => [$category],
            ]);
        }

        // The child accounts hang off the parent; the parent needs a verified address (the factory sets one).
        $parent = $this->learners['parent'];
        $kids = [
            ['child.adam', 'adam', 'آدم', 'Adam', 9],
            ['child.yara', 'yara', 'يارا', 'Yara', 12],
        ];
        foreach ($kids as [$key, $username, $nameAr, $nameEn, $age]) {
            $child = app(CreateChildAccount::class)->handle($parent, [
                'username' => 'demo.'.$username, 'password' => $this->password(), 'name_ar' => $nameAr, 'name_en' => $nameEn,
                'birth_year' => (int) now()->year - $age, 'birth_month' => 6,
            ]);
            DemoAnchors::mark($child);
            $this->learners[$username] = $child;
            $this->userCount++;
        }
    }

    /**
     * Course blueprint: key => [title ar, title en, format, access mode, price (EGP, major units) or null, owner, category offset].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string|null, 5: string, 6: string}>
     */
    private function blueprint(): array
    {
        return [
            'drawing-basics' => ['أساسيات الرسم للمبتدئين', 'Drawing Basics for Beginners', 'recorded', 'free', null, 'academy', 'beginner'],
            'kids-art' => ['فن الأطفال: ألوان ومرح', 'Kids Art: Colour and Fun', 'recorded', 'free', null, 'academy', 'beginner'],
            'acting-101' => ['مدخل إلى التمثيل', 'Introduction to Acting', 'recorded', 'paid', '450.00', 'academy', 'beginner'],
            'voice-dubbing' => ['الأداء الصوتي والدبلجة', 'Voice Acting and Dubbing', 'recorded', 'both', '600.00', 'academy', 'intermediate'],
            'stage-workshop' => ['ورشة المسرح المباشرة', 'Live Stage Workshop', 'live', 'paid', '900.00', 'academy', 'intermediate'],
            'animation-lab' => ['معمل الرسوم المتحركة', 'Animation Lab', 'hybrid', 'paid', '1200.00', 'academy', 'advanced'],
            'creative-sub' => ['المهارات الإبداعية (بالاشتراك)', 'Creative Skills (subscription)', 'recorded', 'subscription_only', null, 'academy', 'all'],
            'indie-comics' => ['صناعة القصص المصورة', 'Making Comics', 'recorded', 'both', '350.00', 'independent', 'all'],
        ];
    }

    /**
     * Realistic bilingual catalog copy. Demo markers live in the demo_anchors table, never in the visible text.
     *
     * @return array{ar: string, en: string}
     */
    private function description(string $key): array
    {
        return match ($key) {
            'drawing-basics' => ['ar' => 'تعلّم الخطوط والظلال والتكوين خطوة بخطوة، مع تمارين قصيرة تناسب المبتدئين.', 'en' => 'Learn line, shading and composition step by step, with short exercises for complete beginners.'],
            'kids-art' => ['ar' => 'أنشطة فنية ممتعة للأطفال لاكتشاف الألوان وصناعة أول لوحاتهم بثقة.', 'en' => 'Playful art activities that help children discover colour and finish their first paintings with confidence.'],
            'acting-101' => ['ar' => 'أساسيات الأداء أمام الكاميرا وعلى المسرح: الإحساس بالمشهد والصوت وحركة الجسد.', 'en' => 'The basics of performing on camera and on stage: reading a scene, using your voice and moving with intent.'],
            'voice-dubbing' => ['ar' => 'درّب صوتك على الأداء والدبلجة، من ضبط النبرة إلى مزامنة الحوار مع الصورة.', 'en' => 'Train your voice for performance and dubbing, from tone control to syncing dialogue with picture.'],
            'stage-workshop' => ['ar' => 'ورشة مباشرة مع مدرّب لبناء مشهد مسرحي كامل وتقديمه أمام زملائك.', 'en' => 'A live workshop with your instructor to build a complete stage scene and perform it for your peers.'],
            'animation-lab' => ['ar' => 'من الفكرة إلى الحركة: ارسم شخصيتك وحرّكها وجهّز عرضك الأول.', 'en' => 'From idea to motion: design a character, animate it and prepare your first showreel.'],
            'creative-sub' => ['ar' => 'مكتبة مهارات إبداعية متجددة تصلك مع اشتراكك، من الرسم إلى السرد البصري.', 'en' => 'A growing library of creative skills included with your subscription, from drawing to visual storytelling.'],
            default => ['ar' => 'اصنع قصتك المصورة من أول لوحة حتى الغلاف النهائي مع مدرّب مستقل.', 'en' => 'Build your own comic from the first panel to the final cover with an independent instructor.'],
        };
    }

    /**
     * A generated 16:9 abstract cover (GD only, no outside file or request) at a fixed per-course path, so a repeated seed overwrites
     * the file instead of piling up copies. `demo:reset` removes the files with the courses.
     */
    private function attachPlaceholderCover(Course $course, string $key, int $index): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        // Flat tones close to the brand palette (deep, mid and soft teal, warm accent).
        $tones = [[0, 112, 110], [0, 76, 75], [60, 150, 142], [176, 104, 78], [34, 92, 110]];
        [$r, $g, $b] = $tones[$index % count($tones)];
        $image = imagecreatetruecolor(1280, 720);
        $fill = static fn (int $red, int $green, int $blue, int $alpha = 0): int => (int) imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
        imagefilledrectangle($image, 0, 0, 1280, 720, $fill($r, $g, $b));
        imagealphablending($image, true);
        $shift = ($index * 97) % 240;
        imagefilledellipse($image, 220 + $shift, 640, 760, 760, $fill(255, 255, 255, 105));
        imagefilledellipse($image, 1040 - $shift, 140, 520, 520, $fill(255, 255, 255, 98));
        imagefilledellipse($image, 640, 360, 220, 220, $fill(255, 255, 255, 80));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        $path = 'courses/demo-'.$key.'.png';
        if (Storage::disk('public')->put($path, $bytes, 'public')) {
            Course::query()->whereKey($course->getKey())->update(['cover_image' => $path]);
            $course->refresh();
        }
    }

    private function catalog(): void
    {
        $categories = Category::query()->orderBy('id')->pluck('id')->all();
        $index = 0;

        foreach ($this->blueprint() as $key => [$titleAr, $titleEn, $format, $access, $price, $owner, $level]) {
            $instructor = $owner === 'academy' ? $this->academy : $this->indie;
            $course = app(SaveCourseWizard::class)($instructor, [
                'title' => ['ar' => $titleAr, 'en' => $titleEn],
                'short_description' => $this->description($key),
                'category_id' => $categories[$index % count($categories)], 'level' => $level, 'content_language' => 'both', 'format' => $format,
                'min_age' => $key === 'kids-art' ? 6 : null, 'max_age' => $key === 'kids-art' ? 14 : null,
            ], null, 1, true);
            $index++;

            // Pricing and the owner of an academy course belong to the admin (SaveCourseWizard guards them).
            $pricing = ['access_mode' => $access];
            if ($price !== null) {
                $pricing['market_prices'] = [Market::Egypt->value => $price];
            }
            $course = app(SaveCourseWizard::class)($this->admin, $pricing, $course, 3, true);
            $this->attachPlaceholderCover($course, $key, $index - 1);
            DemoAnchors::mark($course);
            $this->courses[$key] = $course;
            $this->curriculum($key, $course, $instructor);
        }

        Auth::setUser($this->admin);
        foreach ($this->courses as $course) {
            app(PublishCourse::class)($course);
        }
        foreach ($this->courses as $key => $course) {
            $this->courses[$key] = $course->refresh();
        }
    }

    private function curriculum(string $key, Course $course, User $instructor): void
    {
        $sections = app(ManageSections::class);
        $lessons = app(ManageLessons::class);
        $section = $sections->add($instructor, $course, ['title' => ['ar' => 'الوحدة الأولى', 'en' => 'Module 1']]);
        $sections->setPublished($instructor, $section, true);

        $items = [];
        foreach ([1, 2, 3] as $n) {
            $recorded = $key === 'drawing-basics' && $n === 1;
            $item = $lessons->add($instructor, $section, [
                'title' => ['ar' => 'الدرس '.$n, 'en' => 'Lesson '.$n],
                'type' => $recorded ? 'video' : 'text',
                'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Demo lesson '.$n.' - '.$course->getTranslation('title', 'en')]]]]],
                'duration_seconds' => 300,
            ]);
            if ($recorded) {
                $path = 'media/demo/'.Str::uuid().'.mp4';
                $sample = file_get_contents(database_path('seeders/fixtures/demo-playback.mp4'));
                if ($sample === false || ! Storage::disk(Config::string('media.disk'))->put($path, $sample)) {
                    throw new RuntimeException('Unable to store the demo playback fixture.');
                }
                app(AttachLessonMedia::class)($instructor, Lesson::query()->findOrFail($item->itemable_id), ['provider' => 'signed_storage', 'path' => $path]);
            }
            $items[] = $item;
        }
        $publish = array_map(static fn (CurriculumItem $item): int => (int) $item->getKey(), $items);

        if ($key === 'acting-101') {
            $this->quizAndAssignment($key, $course, $instructor, $section, $publish);
        }

        app(SetCurriculumStatus::class)($instructor, $course, $publish, CurriculumItem::STATUS_PUBLISHED);

        // Reload so the stored items carry the published status the learner-facing Actions check.
        $this->lessonItems[$key] = CurriculumItem::query()->whereKey(array_map(static fn (CurriculumItem $item): int => (int) $item->getKey(), $items))->orderBy('sort')->get()->all();
        if (isset($this->quizItems[$key])) {
            $this->quizItems[$key] = $this->quizItems[$key]->refresh();
        }

        if (in_array($key, ['stage-workshop', 'animation-lab'], true)) {
            $cohorts = app(ManageCohorts::class);
            $cohorts->create($instructor, $course, [
                'name' => 'Spring cohort', 'name_translations' => ['ar' => 'دفعة الربيع'], 'capacity' => 12,
                'starts_on' => now()->addDays(30)->toDateString(), 'ends_on' => now()->addDays(90)->toDateString(),
                'enrollment_opens_on' => now()->toDateString(), 'enrollment_closes_on' => now()->addDays(25)->toDateString(),
            ]);
        }
    }

    /** @param list<int> $publish */
    private function quizAndAssignment(string $key, Course $course, User $instructor, CourseSection $section, array &$publish): void
    {
        $items = app(ManageAssessmentItems::class);

        $quizItem = $items->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار قصير', 'en' => 'Short quiz']]);
        $quiz = Quiz::query()->findOrFail($quizItem->itemable_id);
        app(ConfigureQuiz::class)($instructor, $quiz, [
            'time_limit_minutes' => 20, 'pass_mark_percent' => 60, 'max_attempts' => 3, 'result_visibility' => 'full', 'randomize' => false, 'progression' => 'after_submission',
        ]);
        $questions = app(ManageQuestions::class);
        $questions->add($instructor, $quiz, [
            'type' => Question::SINGLE, 'points' => 1,
            'prompt' => ['ar' => 'ما الذي يساعد الممثل على الاسترخاء قبل العرض؟', 'en' => 'What helps an actor relax before a show?'],
            'options' => [
                ['text' => ['ar' => 'تمارين التنفس', 'en' => 'Breathing exercises'], 'is_correct' => true],
                ['text' => ['ar' => 'تجاهل النص', 'en' => 'Ignoring the script'], 'is_correct' => false],
                ['text' => ['ar' => 'الصراخ', 'en' => 'Shouting'], 'is_correct' => false],
            ],
        ]);
        $questions->add($instructor, $quiz, [
            'type' => Question::TRUE_FALSE, 'points' => 1, 'true_is_correct' => true,
            'prompt' => ['ar' => 'الإيماءات جزء من الأداء.', 'en' => 'Gestures are part of a performance.'],
        ]);
        $this->quizItems[$key] = $quizItem;
        $publish[] = (int) $quizItem->getKey();

        $assignmentItem = $items->add($instructor, $section, 'assignment', ['title' => ['ar' => 'مهمة: مشهد قصير', 'en' => 'Assignment: a short scene']]);
        app(ConfigureAssignment::class)($instructor, Assignment::query()->findOrFail($assignmentItem->itemable_id), [
            'instructions' => ['ar' => 'سجّل مشهدًا قصيرًا.', 'en' => 'Record a short scene.'],
            'allowed_types' => [Assignment::TYPES[0], Assignment::TYPES[2]], 'late_policy' => Assignment::LATE_NOT_ALLOWED, 'max_revisions' => 1,
        ]);
        $publish[] = (int) $assignmentItem->getKey();
    }

    private function commerce(): void
    {
        $coupons = app(SaveCoupon::class);
        $welcome = $coupons($this->admin, [
            'code' => 'DEMO-WELCOME10', 'name' => 'Demo welcome 10%', 'discount_type' => 'percent', 'percent' => 10,
            'max_redemptions' => 100, 'max_per_user' => 1, 'is_active' => true, 'course_ids' => [],
        ]);
        $flat = $coupons($this->admin, [
            'code' => 'DEMO-FLAT50', 'name' => 'Demo flat 50 EGP', 'discount_type' => 'fixed', 'amount_minor' => 5000, 'currency' => 'EGP',
            'max_redemptions' => 50, 'max_per_user' => 1, 'is_active' => true, 'course_ids' => [],
        ]);
        foreach ([$welcome, $flat] as $coupon) {
            DemoAnchors::mark($coupon);
        }

        $bundle = app(SaveBundle::class)($this->admin, [
            'name_ar' => 'حزمة المسرح والتمثيل', 'name_en' => 'Acting and Stage Bundle', 'is_active' => true,
            'prices' => [Market::Egypt->value => 120000],
            'items' => [['course_id' => $this->courses['acting-101']->getKey()], ['course_id' => $this->courses['stage-workshop']->getKey()]],
        ]);
        DemoAnchors::mark($bundle);

        $plan = app(SavePlan::class)($this->admin, [
            'name_ar' => 'اشتراك شهري إبداعي', 'name_en' => 'Creative monthly plan', 'term' => 'monthly', 'is_active' => true,
            'prices' => [Market::Egypt->value => 15000],
            'course_ids' => [$this->courses['creative-sub']->getKey(), $this->courses['voice-dubbing']->getKey()],
        ]);
        DemoAnchors::mark($plan);

        $nour = $this->learners['nour'];
        $omar = $this->learners['omar'];
        $laila = $this->learners['laila'];
        $parent = $this->learners['parent'];

        // Paid: a course, a course with a coupon, a course for a child, a bundle and a subscription.
        $this->paid('demo-order-001', $nour, [new OrderLine(ProductType::Course, (int) $this->courses['acting-101']->getKey(), $nour)]);
        $this->paid('demo-order-002', $omar, [new OrderLine(ProductType::Course, (int) $this->courses['stage-workshop']->getKey(), $omar)], 'DEMO-WELCOME10');
        $this->paid('demo-order-003', $parent, [new OrderLine(ProductType::Course, (int) $this->courses['indie-comics']->getKey(), $this->learners['adam'])]);
        $stage = $this->courses['stage-workshop'];
        $stageCohort = $stage->cohorts()->sole();
        $this->paid('demo-order-004', $laila, [new OrderLine(ProductType::Bundle, $bundle->id, $laila, [$stage->id => $stageCohort->id])]);
        $this->paid('demo-order-005', $nour, [new OrderLine(ProductType::Plan, (int) $plan->getKey(), $nour)]);

        // Other states: waiting for payment, cancelled, failed.
        $create = app(CreateOrder::class);
        $create($laila, Market::Egypt, [new OrderLine(ProductType::Course, (int) $this->courses['animation-lab']->getKey(), $laila)], 'demo-order-006');
        $cancelled = $create($omar, Market::Egypt, [new OrderLine(ProductType::Course, (int) $this->courses['acting-101']->getKey(), $omar)], 'demo-order-007');
        app(TransitionOrder::class)($cancelled, OrderStatus::Cancelled);
        $failed = $create($laila, Market::Egypt, [new OrderLine(ProductType::Course, (int) $this->courses['voice-dubbing']->getKey(), $laila)], 'demo-order-008');
        app(TransitionOrder::class)($failed, OrderStatus::Failed);

    }

    /**
     * Pays an order the way a verified gateway callback would, without any gateway: status, coupon use, access and earnings.
     *
     * @param  list<OrderLine>  $lines
     */
    private function paid(string $key, User $payer, array $lines, ?string $coupon = null): Order
    {
        $order = app(CreateOrder::class)($payer, Market::Egypt, $lines, $key);
        if ($coupon !== null) {
            $order = app(ApplyCoupon::class)($order, $payer, $coupon);
        }
        $order = app(TransitionOrder::class)($order, OrderStatus::Paid);
        app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
        app(RecordOrderEarnings::class)($order);

        return $order;
    }

    private function learning(): void
    {
        $free = app(EnrollInFreeCourse::class);
        $nour = $this->learners['nour'];
        $adam = $this->learners['adam'];
        $yara = $this->learners['yara'];
        $parent = $this->learners['parent'];

        $free($nour, $nour, $this->courses['drawing-basics']);
        $free($this->learners['omar'], $this->learners['omar'], $this->courses['drawing-basics']);
        $free($parent, $adam, $this->courses['kids-art']);
        $free($parent, $yara, $this->courses['kids-art']);

        // Different progress: one child has started, the other finished the course.
        $progress = app(RecordProgress::class);
        $progress->markComplete($adam, $this->lessonItems['kids-art'][0]);
        foreach ($this->lessonItems['kids-art'] as $item) {
            $progress->markComplete($yara, $item);
        }

        // A learner who finished the paid acting course: quiz attempt, completion and certificate.
        foreach ($this->lessonItems['acting-101'] as $item) {
            $progress->markComplete($nour, $item);
        }
        $quizItem = $this->quizItems['acting-101'];
        $attempt = app(StartQuizAttempt::class)($nour, $quizItem);
        $questions = Question::query()->where('quiz_id', $quizItem->itemable_id)->orderBy('sort')->get();
        $answers = [];
        foreach ($questions as $question) {
            $answers[(int) $question->getKey()] = ['selected' => [1]];
        }
        app(SubmitQuizAttempt::class)($nour, $attempt, $answers);

        $course = $this->courses['acting-101'];
        $completion = CourseCompletion::query()->where('course_id', $course->getKey())->where('learner_id', $nour->getKey())->first()
            ?? app(ApproveCourseCompletion::class)($this->admin, $course, $nour, 'Demo course completed');
        app(IssueCertificate::class)($completion);

        // Cohort seat for a paid live learner.
        $cohort = Cohort::query()->where('course_id', $this->courses['stage-workshop']->getKey())->first();
        if ($cohort !== null) {
            app(ReserveCohortSeat::class)($cohort, $this->learners['omar'], $this->admin);
        }
    }

    private function engagement(): void
    {
        $nour = $this->learners['nour'];
        $review = app(SubmitCourseReview::class)($nour, $this->courses['acting-101'], 5, 'A warm, practical course. The demo reviews are not real.');
        app(ApproveCourseReview::class)($this->admin, $review);

        $omar = $this->learners['omar'];
        $review = app(SubmitCourseReview::class)($omar, $this->courses['drawing-basics'], 4, 'Clear lessons and a friendly pace. Demo review.');
        app(ApproveCourseReview::class)($this->admin, $review);

        // A help request: a recorded assistant answer the learner did not understand, then the instructor's reply.
        // The transcript is a fixed demo exchange; no AI provider is called.
        $item = $this->lessonItems['drawing-basics'][0];
        $lesson = Lesson::query()->findOrFail($item->itemable_id);
        $conversation = AiConversation::query()->create([
            'student_id' => $omar->getKey(), 'course_id' => $item->course_id, 'lesson_id' => $lesson->getKey(),
            'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
        ]);
        AiMessage::query()->create(['conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_USER, 'content' => 'What is a vanishing point?', 'created_at' => now()]);
        $answer = AiMessage::query()->create([
            'conversation_id' => $conversation->getKey(), 'role' => AiMessage::ROLE_ASSISTANT, 'status' => 'answered',
            'content' => 'Demo answer: lines in a drawing meet at a point on the horizon.', 'created_at' => now(),
        ]);
        $outcome = app(RecordAssistantFeedback::class)($omar, $lesson, (int) $answer->getKey(), AiFeedback::NOT_UNDERSTOOD);
        if ($outcome->request !== null) {
            app(ReplyToHelpRequest::class)($this->academy, $outcome->request, 'Demo reply: look at the railway lines in the lesson picture.');
        }
    }
}
