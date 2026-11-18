# تقرير التحقق من الاختبارات وإغلاق المهام والمشاكل المكتشفة (Test Closure & Issues Report)

> **تاريخ الفحص:** 2026-11-18  
> **النطاق:** التحقق الفعلي من الاختبارات القائمة، إكمال النواقص، كتابة اختبارات المهام غير المغطاة من `docs/test-prompts/*.md` (بدون تعديل أي ملف إنتاجي في `app/`, `database/`, `routes/`, `config/`, `resources/`, `lang/`), تشغيل جميع بوابات الجودة `G1`–`G11`, تحديث `docs/TRACEABILITY_MATRIX.md` و`agents/PROGRESS.md`، وإجراء مراجعة مستقلة عبر `research` subagent.

---

## ملخص تنفيذي للمالك (Arabic Summary)

1. **ما تم إنجازه في الاختبارات والتغطية (G10):**
   - تم جرد جميع المهام المفتوحة في `agents/PROGRESS.md` المعتمدة على الاختبارات وملفات `docs/test-prompts/` (**27 مهمة + 4 مهام كان بها خلل وسوم تغطية = 31 مهمة**).
   - تم إصلاح وسوم التغطية المكسورة في الملفات الموجودة بالفعل (دون كتابة تكرار غير مفيد)، وإكمال اختبارات النقص في `T-P2-05` و`T-P5-05`، وإنشاء ملفات الاختبار الناقصة لباقي المهام (`T-P2-06..09`, `T-P6-01..06`, `T-P7-01..03`, `T-P8-01..05`, `T-P9-01..04`) مع اختبارات المتصفح ولقطات الشاشة لكل من `T-P2-07`, `T-P2-09`, `T-P5-05`, `T-P6-02`, `T-P6-06`, `T-P7-03`, `T-P9-01`, `T-P9-02`, `T-P9-03`.
   - نجح أمر التغطية `php artisan app:req-coverage --task=<ID> --refresh` لـ **31 / 31 مهمة بنسبة 100%** وتم تحديث [TRACEABILITY_MATRIX.md](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/docs/TRACEABILITY_MATRIX.md). **لم يعد هناك أي عائق خاص بـ "اختبارات معلقة" على هذه المهام الـ 31.**

2. **لماذا بقيت المهام على حالة `review` ولم يتم نقلها إلى `done` بشكل شكلي؟**
   وفقاً لقواعد [VERIFICATION_GATES.md](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/agents/VERIFICATION_GATES.md) و[ORCHESTRATOR.md](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/agents/ORCHESTRATOR.md) وطلبك الصريح بعدم الإغلاق إلا بعد التأكد أن "كل حاجة تمام" والإبلاغ عن أي مشاكل في ملف منفصل:
   - **بقي جميع الـ 31 مهمة في حالة `review`** مع استبدال عبارة `"tests: pending (other model)"` و`"G10 FAIL"` بنتائج الاختبارات الفعلية، لأن كل مهمة من الـ 31 تصطدم بواحد أو أكثر من:
     1. **أخطاء برمجية مؤكدة في كود الإنتاج** (أخطرها `BUG-007` الذي يُسقط أي تحديث لحالة الطلبات `Order::updating`, و`BUG-008` في زر فتح مساعد الذكاء الاصطناعي لدروس YouTube، و`BUG-P7-06-01` في خصائص ARIA، و`BUG-009` في نوافذ Filament للتقييم والشهادات، و`GAP-P6-F3`، و`GAP-P9-04`، و`GAP-P2-06`، و6 أخطاء PHPStan في `app/**`).
     2. **مشكلة بيئة محلية على Windows** في ملف `qpdf.exe` (رمز خروج `0xC0000135`) تتسبب في فشل 23 اختبار PDF قديم/توثيق.
     3. **اعتماديات سابقة ما زالت `review` أو `blocked`** ومراجعات بصرية `D-026` وموافقات المالك المفتوحة `O-*`.

---

## 1. نتائج التشغيل الفعلي للبوابات والاختبارات (Verified Results)

| البوابة / الحزمة | الأمر / النطاق | النتيجة الدقيقة المُرصَدة | الحالة |
|---|---|---|---|
| **G1 Format (ملفاتنا)** | `pint --test` على 40 ملف اختبار أنشأناه/عدّلناه | 0 أخطاء تنسيق بعد التنسيق | **PASS** (Scoped) |
| **G1 Format (شامل)** | `php vendor/bin/pint --test` على المستودع كاملًا | إخفاق في ملفات إنتاج واختبارات قديمة غير ممسوسة (`app/`, `config/`, `lang/`, `routes/`, `scripts/`, `tests/`) | **FAIL** (Global) |
| **G2 PHPStan (شامل)** | `vendor/bin/phpstan analyse --memory-limit=2G` | **6 أخطاء** في ملفات غير ممسوسة: `AskLessonAssistant.php:371-372`, `DuplicateCourse.php:190`, `RichContent.php:141`, `ManageCurriculum.php:274`, `AssistantController.php:91` | **FAIL** (Global) |
| **G2 PHPStan (نطاقات نظيفة)** | `phpstan analyse` على `Seo`, `Notifications`, `Shared/Markets`, `Instructors`, `Engagement`, `Privacy` | 0 أخطاء (`[OK] No errors`) | **PASS** (Scoped) |
| **G3 Unit + Arch + Invariants + Root** | الدفعة 1 (`tests/Unit`, `tests/Arch`, `tests/Feature/Invariants`, `tests/Feature/*.php`) | **134 passed** (542 assertions), exit `0` | **PASS** |
| **G3 Feature الدفعة 2** | `AI`, `Assessment`, `Assignments`, `Audit`, `Catalog`, `Cohorts`, `Commerce`, `Content` | **284 passed**, 9 todos (`BUG-*`), 2 skipped (`qpdf.exe`), **1 failed** (`AI/PdfIngestionTest` بسبب `qpdf.exe`) (2,448 assertions) | **WARN (`qpdf`)** |
| **G3 Feature الدفعة 3** | `Engagement..Seo` + نظيف `Curriculum`/`Media` | **690 passed**, 2 todos, **5 failed** (`3 CurriculumPageTest` منها 1 `qpdf`، و`1 ContentLibraryTest` `qpdf`، و`1 WorkspaceSurfacesTest`) (4,443 assertions) | **WARN (Pre-existing + `qpdf`)** |
| **G3 Media `PrivateFilesTest`** | الدفعة 4 معزولة (`tests/Feature/Media/PrivateFilesTest.php`) | **7 passed**, **20 failed** (جميع الـ 20 بسبب `qpdf.exe` `-1073741515`) (24 assertions) | **ENV BLOCKED (`qpdf`)** |
| **إجمالي غير المتصفح (G3/G4/G5)** | مجموع الدفعات 1–4 لجميع ملفات `Unit / Arch / Invariants / Feature` | **1,115 passed, 11 todos, 2 skipped, 26 failed** (**23 بسبب `qpdf.exe`** + **3 إخفاقات قائمة مسبقًا** في اختبارات قديمة) | **معزول بدقة** |
| **G4 & G5 منفصلة** | `pest tests/Arch tests/Feature/Invariants` | **17 passed** (91 assertions), exit `0` | **PASS** |
| **G6 Translations / RTL / Build** | `app:check-translations`, `app:check-rtl`, `npm run build` | الكل اجتاز (`exit 0`, AA contrast passed 34 pairs, Vite built) | **PASS** |
| **G7 Browser (ما تم تشغيله)** | `BlogAndPublicPagesBrowserTest` (**8 pass**), `LiveSessionBrowserTest` (**8 pass**), `AssistantPanelBrowserTest` (**4 pass**), `Batch3DScreensBrowserTest` (**12 pass**), `AssessmentControlsBrowserTest` (timed-quiz **8 pass** / staff modals **24 fail**) | لقطات AR/EN حُفظت في `tests/Browser/screenshots/T-P*/` وفحوصات axe/RTL نجحت في اختباراتنا | **جزئي / BUG-009 + D-026 مفتوح** |
| **G8 AI Eval (fake)** | `php artisan ai:evaluate --suite=all --provider=fake --no-store` | **PASS** (100% على جميع المعايير الـ 59 حالة؛ `arabic_answer_quality` تقييم بشري، `configured` معطل حتى `O-014`) | **PASS (Fake only)** |
| **G9 Payments** | `pest --group=payments` | **19 passed, 7 todos (`BUG-007`), 1 skipped (`qpdf`)** (193 assertions) | **PASS with `BUG-007` todos** |
| **G10 Coverage & Traceability** | `app:req-coverage --task=<ID> --refresh` | **31 / 31 PASS (`COVERAGE_FAILED=0`)** | **PASS (31/31)** |
| **G11 Doctor** | `php artisan app:doctor` | Database/Storage/Mail/Migrations: OK; **`Scheduler: FAIL`** (`exit 1`) | **FAIL (Local Scheduler)** |

---

## 2. مصفوفة المهام الـ 31 بعد إغلاق فجوة الاختبارات

جميع المهام التالية أصبحت **مغطاة باختبارات ناجحة (`G10 PASS`)** ولم تعد متوقفة على كتابة الاختبارات، وبقيت في `review` للأسباب المحددة أمام كل منها:

| المهمة | الاختبارات التي نجحت + اللقطات | G10 | الحالة الحالية | السبب غير الاختباري الذي يمنع نقلها لـ `done` |
|---|---|---|---|---|
| `T-P2-05` | `PublicCourseDetailTest`, `PublicCatalogBrowserTest` (10 pass) | PASS | `review` | اعتماد `T-P2-04` (`blocked`), واجهة السعر/التسجيل, `GAP-P2-06` (`$profiles`), مراجعة D-026 |
| `T-P2-06` | `SeoInfrastructureTest` | PASS | `review` | تدقيق `research` = `CHANGES REQUIRED` (`GAP-P2-06`), اعتماد `T-P2-02`/`05` (`review`) |
| `T-P2-07` | `BlogTest`, `BlogAndPublicPagesBrowserTest` + screenshots | PASS | `review` | اعتماد `T-P2-06`/`T-P2-03` (`review`), روابط التنقل العامة, مراجعة D-026 |
| `T-P2-08` | `ContactAndNewsletterTest` | PASS | `review` | اعتماد `T-P2-03` (`O-027`), صياغة الخصوصية مسودة (`YG-GAP-PRV-004`), لقطات G7 |
| `T-P2-09` | `InstructorAndCategoryPagesTest`, `BlogAndPublicPagesBrowserTest` + screenshots | PASS | `review` | اعتماد `T-P2-04` (`blocked`)/`T-P2-06` (`review`), مراجعة D-026 |
| `T-P4-10` | `InstructorInsightsTest` | PASS | `review` | [REVIEW_QUEUE_P4](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/docs/reviews/REVIEW_QUEUE_P4_2026-11-17.md) #8 (جدول HTML بدل Filament + أعمدة P6 ناقصة) & #9 (حالة AI في المعاينة) + G7 |
| `T-P5-01` | `CohortsTest`, `CohortEdgeCasesTest` | PASS | `review` | [REVIEW_QUEUE_P5](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/docs/reviews/REVIEW_QUEUE_P5_2026-11-17.md) #1 (تسرب جلسات المجموعات الأخرى في `EntitlementService`) & #4 (تواريخ التسجيل والاسم المترجم) + G7 |
| `T-P5-02` | `LiveSessionsTest` | PASS | `review` | `REVIEW_QUEUE_P5` #8 (حقل الوصف في نموذج الجدولة), اعتماد `T-P5-01`/`T-P3-04`, لقطات G7 |
| `T-P5-05` | `AttendanceTest` (14), `RecordJoinTest` (6), `LiveReplayTest` (9), `LiveSessionBrowserTest` (8) + screenshots | PASS | `review` | اعتماد `T-P5-03` (`review`) ومقارنة G7 للقطات التمثيل |
| `T-P6-01` | `QuizAuthoringTest` (24), `QuizIsolationTest` (7) | PASS | `review` | P6 F8 (`manage-quiz.blade.php` ألوان خام غير tokenized), `BUG-009` (زر `add-assessment`), `O-021`/`O-022` |
| `T-P6-02` | `QuizRuntimeTest` (5), `AssessmentControlsBrowserTest` timed-quiz (8/8) + screenshots | PASS | `review` | **`GAP-P6-F3`** (`EntitlementService.php:250-254` لا يمنع `after_submission`), `BUG-009` (`release-quiz`), D-026 |
| `T-P6-03` | `AssignmentSubmissionsTest` (3 pass, 1 qpdf skip) | PASS | `review` | فشل `qpdf.exe` المحلي لاختبار رفع PDF الحي + لقطات G7 |
| `T-P6-04` | `ReviewGradingQueueTest` (2), `AssignmentCompletionTest` (6) | PASS | `review` | لقطات G7 + اعتماد `T-P6-02`/`T-P6-03` |
| `T-P6-05` | `CompletionEngineTest` (2) | PASS | `review` | P6 F8 (`completion.blade.php` ألوان gray/white خام), `BUG-009`, لقطات G7 |
| `T-P6-06` | `CertificateIssuanceTest` (3), `CertificateVerifyAndPdfTest` (3), `CertificateVerificationBrowserTest` + screenshots | PASS | `review` | P6 F5 (توليد PDF عربي حقيقي غير محقّق فعليًا في الاختبار بعد استثناء Fake), `BUG-009`, مقارنة `reference 22` المفتوحة في `PROGRESS.md:12` |
| `T-P7-01` | `AssistantCoreGuardTest` (3), `AssistantEnginePipelineTest`, `ai:evaluate` PASS | PASS | `review` | **2 أخطاء PHPStan (`AskLessonAssistant.php:371-372`)**، فجوة commit قبل البث، `O-014` (`B-08`) |
| `T-P7-02` | `AssistantInputGuardsCacheQuotasTest` (4), `ai:evaluate` PASS | PASS | `review` | `O-014`/`O-015`/`O-028`، تباين كشف القاصرين (`StyleTier` مقابل `AssistantEligibility`) وتزامن `BudgetGate` |
| `T-P7-03` | `AssistantPanelTest` (1), `AssistantPanelBrowserTest` (4) + screenshots | PASS | `review` | **1 خطأ PHPStan (`AssistantController.php:91`)** + **`BUG-008`** (`learn/show.blade.php:101-128` يغفل زر AI للـ `embed`/YouTube أقل من `2xl`) + D-026 |
| `T-P7-04` | `AssistantFeedbackTest`, `LearnerHelpThreadTest`, `HelpRequestQueueTest`, `HelpRequestScreensTest` | PASS | `review` | مراجعة D-026 + اعتماد `T-P7-03` |
| `T-P7-05` | `AssistantEvaluationTest`, `AssistantTestConsoleTest`, `AssistantHarnessUnitTest`, `AssistantTestConsoleScreensTest` | PASS | `review` | `O-014` (تقييم مزود حقيقي), تقييم جودة العربية يدويًا, D-026 |
| `T-P7-06` | `AdminResilienceTest`, `AdminUsageAndInsightsTest`, `AdminControlsSettingsTest`, `AssistantAdminBrowserTest` | PASS | `review` | **`BUG-P7-06-01`** (`availability.blade.php` استخدام `aria-pressed` على `role="switch"`), `O-014`/`O-015`/`O-028`, D-026 |
| `T-P8-01` | `OrderEngineTest` (3 pass, 1 BUG-007 todo) | PASS | `review` | **`BUG-007`** الحرج في `Order.php:52`, اعتماد `T-P3-03` (`in_progress`) و`T-P4-01` (`review`) |
| `T-P8-02` | `PaymentGatewayCheckoutTest` (3 pass) | PASS | `review` | **`BUG-007`**, `B-07` (`O-006` بيانات Paymob الحقيقية غائبة), واجهة الدفع `T-P8-12` |
| `T-P8-03` | `PaymobWebhookFulfillmentTest` (2 pass, 1 BUG-007 todo), `CommerceEdgeCasesTest` | PASS | `review` | **`BUG-007`** يمنع أي استيفاء ناجح للطلب (`TransitionOrder`), `B-07` (`O-006`) |
| `T-P8-04` | `ManualPaymentProofsTest` (1 pass, 1 qpdf skip), `CommerceEdgeCasesTest` (2 pass, 2 BUG-007 todo) | PASS | `review` | **`BUG-007`** يمنع اعتماد إثبات التحويل, `qpdf.exe`, لقطات G7 |
| `T-P8-05` | `CouponsTest` (1 pass, 1 BUG-007 todo), `CommerceEdgeCasesTest` (5 pass, 1 BUG-007 todo) | PASS | `review` | **`BUG-007`** يمنع `ApplyCoupon`/`RemoveCoupon` من تحديث إجمالي الطلب, لقطات G7 |
| `T-P8-13` | `DisabledSaudiMarketTest` (1), `I15SaudiMarketTest` (1) | PASS | `review` | تدقيق `research` = `CHANGES REQUIRED` (اعتماد `T-P8-01`, اختبار webhook للطلب السعودي في P8-03/13), `O-013` وتفعيل `T-P12-01` |
| `T-P9-01` | `InstructorOnboardingTest` (3 pass), `Batch3DScreensBrowserTest` (4) + screenshots | PASS | `review` | مقارنة D-026 ووجود بعض نصوص الاتفاقية كمسودة (`OPEN`) |
| `T-P9-02` | `WishlistAndReviewsTest` (3 pass), `Batch3DScreensBrowserTest` (4) + screenshots | PASS | `review` | لقطات قائمة المراجعة الإدارية و`wishlist` + مقارنة D-026 |
| `T-P9-03` | `ConsentAndMarketingTagsTest` (2 pass), `Batch3DScreensBrowserTest` (4) + screenshots | PASS | `review` | اعتماد `T-P2-03` (`O-027`), صياغة الكوكيز القانونية (`YG-GAP-PRV-004`), مقارنة D-026 |
| `T-P9-04` | `PhoneChannelTest` (3 pass), `NotificationRoutingTest` | PASS | `review` | تدقيق `research` = `CHANGES REQUIRED` (`GAP-P9-04`: عدم حماية القاصر عندما `ai.adult_age=null` وفشل صامت لـ audit), `O-021` مزود WhatsApp/SMS |

---

## 3. قائمة المشاكل التقنية المطلوب إصلاحها في كود الإنتاج (Actionable Code Bugs)

### أ. أخطاء حرجة وعالية الأولوية
1. **`BUG-007` — تعطل أي تحديث على جدول الطلبات (`orders`):**
   - **المكان:** [Order.php:52](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Commerce/Order.php#L52)
   - **السبب:** حدث `Order::updating` يقرأ `OrderStatus::tryFrom((string) $order->getOriginal('status'))`، بينما يُرجع Laravel 13 كائن `OrderStatus` Enum مباشرةً عند قراءة `getOriginal('status')`، مما يرمي `Error: Object of class App\Domain\Commerce\OrderStatus could not be converted to string`.
   - **التأثير:** يُعطّل كليًا `TransitionOrder` و`ApplyCoupon` و`RemoveCoupon` واعتماد الدفع اليدوي `ApproveManualPaymentProof` واستيفاء `ProcessPaymentWebhook` عند النجاح (7 اختبارات معلقة في `->todo()`).
   - **الإصلاح المقترح:** استخدام `$raw = $order->getRawOriginal('status'); $original = $raw instanceof OrderStatus ? $raw : OrderStatus::tryFrom((string) $raw);`.

2. **`BUG-008` — اختفاء زر فتح مساعد الذكاء الاصطناعي على دروس الفيديو المضمنة (`YouTube` / `embed`):**
   - **المكان:** [show.blade.php:101-128](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/resources/views/learn/show.blade.php#L101-L128) و[assistant-panel.blade.php:3](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/resources/views/components/learning/assistant-panel.blade.php#L3)
   - **السبب:** `<x-learning.assistant-opener />` (`data-ai-open`) موضوع داخل شرط `@if($descriptor !== null && (in_array($descriptor->type, ['video', 'audio'], true) || $descriptor->provider === 'vimeo'))` فقط، بينما دروس الفيديو المربوطة بـ YouTube يكون نوعها `'embed'` وتدخل في `@elseif($canMarkComplete)` بدون عرض `assistant-opener`؛ وبما أن `<aside data-ai-panel>` مخفي كـ drawer تحت `2xl` (`1536px`)، يتعذر على المتعلم فتح المساعد على الموبايل (`390px`) واللابتوب (`1440px`) رغم وجود `data-ai-panel` في الصفحة.
   - **الإصلاح المقترح:** عرض `<x-learning.assistant-opener />` عند `$aiAvailable` سواء كان داخل شريط الأدوات أو بجانب زر الإتمام في فرع `@elseif`.

3. **`GAP-P6-F3` — الاختبار المطلوب بنظام `after_submission` لا يمنع الدرس التالي:**
   - **المكان:** [EntitlementService.php:250-254](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Entitlements/EntitlementService.php#L250-L254)
   - **السبب:** فحص التسلسل يتحقق فقط من `$previous->itemable->progression === 'require_pass'` ويتجاهل `'after_submission'`.

4. **`GAP-P9-04` — تعطل حماية القاصرين في إشعارات الهاتف تحت الإعداد الافتراضي (`O-015`):**
   - **المكان:** [SendPhoneReminder.php:49](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Notifications/Actions/SendPhoneReminder.php#L49), [SetPhoneReminderOptIn.php:33,50-52](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Notifications/Actions/SetPhoneReminderOptIn.php#L33-L52), [ReviewerName.php:25-30](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Engagement/ReviewerName.php#L25-L30)
   - **السبب:** الاعتماد على `ReviewerName::isChild()` يرجع `false` افتراضيًا عند `config('ai.adult_age') === null` أو غياب تاريخ الميلاد؛ كما أن `SetPhoneReminderOptIn` لا يتحقق من فشل `AuditLogger::record() === null`، وهناك استيراد عابر للوحدات من `Engagement` داخل `Notifications`.

5. **أخطاء أخرى متبقية من التدقيق والأتمتة:**
   - **`BUG-P7-06-01` ([availability.blade.php:29,53](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/resources/views/filament/ai/availability.blade.php#L29-L53)):** استخدام `aria-pressed` بدلًا من `aria-checked` على `role="switch"` (خطأ إتاحة axe حرج؛ الاختبار `->skip()`).
   - **`BUG-009` ([AssessmentControlsBrowserTest.php:55](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/tests/Browser/AssessmentControlsBrowserTest.php#L55)):** فشل جميع حالات فتح نوافذ Filament (24/24) لـ `add-assessment`, `release-quiz`, `certificate-templates` بسبب عدم ظهور `[role="dialog"]` بعد النقر.
   - **`GAP-P6-F8` ([manage-quiz.blade.php](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/resources/views/filament/courses/manage-quiz.blade.php) و[completion.blade.php](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/resources/views/filament/courses/completion.blade.php)):** ما زالا يستخدمان أصناف `bg-white`, `border-gray-*`, `bg-gray-*`, `primary-600` بدل tokens التصميم.
   - **`GAP-P2-06` ([PublicCourseController.php:30-32](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Http/Controllers/PublicCourseController.php#L30-L32), [CourseSeo.php:66](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Catalog/CourseSeo.php#L66), [SitemapEntries.php:60-74](file:///c:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2/app/Domain/Seo/Queries/SitemapEntries.php#L60-L74)):** نسيان تمرير `$profiles` لـ `CourseSeo::for()` (فقدان روابط المدربين في JSON-LD)، وحقن `hreflang` قسريًا للغات غير المترجمة، وإغفال تصفية اللغات في خريطة التصنيفات/المدربين.
   - **`T-P7-01 / T-P7-02` تدقيق `research`:** نافذة commit للـ quota قبل فحص الاستحقاق أثناء البث (`AskLessonAssistant.php:163-169` مقابل `AssistantController.php:85-98`), واختلاف تصنيف `StyleTier` عن `AssistantEligibility` عند غياب `age_bands`, وعدم قفل `BudgetGate::assertOpen()`.
   - **`BUG-004` / `BUG-005` / `BUG-006`** المعلقة في `docs/reviews/BUGS_FOUND.md` (3 اختبارات `->todo()`).
   - **ملاحظات مفتوحة من تدقيقات P4/P5 السابقة:** `REVIEW_QUEUE_P4` #8 و#9 (`T-P4-10`) و`REVIEW_QUEUE_P5` #1 و#4 و#8 (`T-P5-01`/`02`).
   - **6 أخطاء PHPStan (`G2`)** و**3 إخفاقات قديمة** في `CurriculumPageTest.php:157,218` و`WorkspaceSurfacesTest.php:24`, بالإضافة لعدم تنسيق عدد من الملفات غير الممسوسة في `pint --test`.

---

## 4. مشاكل بيئة التشغيل المحلية (Host / Windows Environment)

1. **فشل `qpdf.exe` على Windows (`-1073741515` / `0xC0000135` — DLL مفقودة):**
   - المسار: `C:/Users/N/AppData/Local/YoungGo/qpdf/12.4.2/bin/qpdf.exe`
   - يتعطل كل استدعاء للتحقق من ملفات PDF (`PdfInspector`), مما سبب **23 إخفاقًا** (`PrivateFilesTest` 20، `PdfIngestionTest` 1، `ContentLibraryTest` 1، `CurriculumPageTest` 1) وتخطي اختبارات PDF الحية في `AssignmentSubmissionsTest` و`ManualPaymentProofsTest`.
   - **الحل المقترح محليًا:** إعادة تثبيت حزمة qpdf الكاملة على Windows متضمنة ملفات DLL الخاصة بها (مثل `libgcc_s_*.dll`, `libstdc++-6.dll`, `qpdf*.dll` في نفس مجلد `bin/`) أو تثبيت Visual C++ Redistributable، ثم التأكد من عمل `qpdf.exe --version` بدون أخطاء.
2. **فشل `app:doctor` في عنصر `Scheduler: FAIL`:**
   - يجب تشغيل جدول المهام (`php artisan schedule:work` أو cron بالخادم) قبل إعادة فحص `G11`.

---

## 5. قرارات المالك والاعتماد البصري المتبقية (Owner Decisions & G7)

- **قرارات مفتوحة `O-*`:**
  - `O-001` / `O-027`: تأكيد الدومين والهوية البصرية النهائية للواجهة العامة (`T-P2-03` و`T-P2-08` و`T-P9-03`).
  - `O-006` (`B-07`) و`O-023`: مفاتيح وبيانات بوابة Paymob والبيانات الضريبية/القانونية للبائع (`T-P8-02`, `T-P8-03`).
  - `O-013`: ملف السوق السعودي القانوني والضريبي (`T-P8-13`, `T-P12-01`).
  - `O-014` (`B-08`): اختيار مزود الذكاء الاصطناعي الحقيقي ومفاتيحه لتشغيل `ai:eval --provider=configured` (`T-P7-01..06`, `T-P7-07`).
  - `O-015` و`O-028`: اعتماد حدود العمر، سياسة القاصرين للذكاء الاصطناعي، وصياغة/مستلمي تنبيهات الحماية (`Safeguarding`).
  - `O-021`: بيانات مزود WhatsApp/SMS الحقيقي (`T-P9-04`).
  - `YG-GAP-PRV-004`: النص القانوني المعتمد لملفات الكوكيز والخصوصية واتفاقية المدربين.
- **الاعتماد البصري `G7` (`D-026`):** لا تزال مقارنة اللقطات الملتقطة (`tests/Browser/screenshots/T-P*/`) مع المراجع في `docs/design-system/reference-pack-2026-11-17/` بأحجام متكافئة وبإصلاح الوضع الداكن مطلوبة من المراجع البشري/المالك قبل إغلاق مهام الواجهات.
