# تقرير التحقق من الاختبارات وإغلاق المشاكل البرمجية والبيئية (Test Closure & Issues Report)

> **تاريخ الفحص والتحديث:** 2026-11-18  
> **النطاق:** تم حل جميع المشاكل البرمجية المكتشفة (`BUG-001` إلى `BUG-009`، و`BUG-P7-06-01`، وفجوات `GAP-P6-F3` و`GAP-P2-06` و`GAP-P9-04` و`I01 Invariant`)، وإصلاح بيئة التشغيل المحلية لـ `qpdf.exe` ومشاكل الذاكرة والـ Scheduler، وتشغيل جميع بوابات الجودة `G1`–`G11` بنجاح كامل 100%.

---

## ملخص تنفيذي للمالك (Arabic Summary)

1. **ما تم إنجازه في إصلاح المشاكل البرمجية والبيئية:**
   - **`BUG-007` (الخطأ الحرج في نظام التجارة والطلبات):** تم حله في `app/Domain/Commerce/Order.php:52` بالتعامل الآمن مع Enum `OrderStatus` عند قراءة الحالة الأصلية من Laravel 13. تم تفعيل الاختبارات التي كانت معطلة بـ `->todo()` في `OrderEngineTest`, `CouponsTest`, `PaymobWebhookFulfillmentTest`, و`CommerceEdgeCasesTest` بنجاح كامل (28 passed, 246 assertions, 0 failed).
   - **`BUG-008` (زر مساعد الذكاء الاصطناعي لدروس YouTube/embed):** تم حله في `resources/views/learn/show.blade.php:126-135` بعرض `<x-learning.assistant-opener />` في فرع `$canMarkComplete` أسفل شاشات `2xl`.
   - **`BUG-P7-06-01` (خطأ إتاحة Axe حرج في لوحة تحكم الذكاء الاصطناعي):** تم استبدال `aria-pressed` بـ `aria-checked` على أزرار `role="switch"` في `resources/views/filament/ai/availability.blade.php:29,53` واجتاز الاختبار `tests/Browser/AssistantAdminBrowserTest.php` بـ 0 مخالفات axe.
   - **`BUG-009` (نوافذ Filament في اختبارات المتصفح للتقييم والشهادات):** تم تحديث محدد النافذة المنبثقة إلى `.fi-modal-window:visible` واجتازت جميع الحالات الـ 32 بنجاح.
   - **`BUG-004` و`BUG-005` و`BUG-006`:** تم إصلاح تقليم نصوص التقييم مع الحفاظ على `"0"` في `ManageAssessmentItems.php`، وحذف الصور القديمة من القرص في `SaveCategory.php`، وتطهير أسعار الكورس عند التحويل إلى مجاني في `SaveCourseWizard.php`.
   - **`GAP-P6-F3` (تسلسل الكورس ومنع التقدم عند وجود اختبار إلزامي):** تم تفعيل شرط `progression === 'after_submission'` مع فحص `Quiz::hasActiveQuestions()` و`morphWithCount` حتى لا تُعطل مسودات الاختبارات الفارغة تقدم الطلاب، واجتازت اختبارات `AccessLayersTest` (18 pass) و`ProgressEdgeCasesTest` (16 pass) و`QuizRuntimeTest` (5 pass).
   - **`GAP-P2-06` (بيانات SEO والمدربين وخريطة الموقع):** تم تمرير `$profiles` لـ `CourseSeo::for()` في `PublicCourseController.php`، وتقييد `hreflang` على اللغات المترجمة فعلياً، وتصفية خريطة الموقع عبر `localesWith()`.
   - **`GAP-P9-04` (حماية القاصرين في إشعارات الهاتف):** تم ضبط عتبة القاصرين الافتراضية في `GuardianAccess::isMinor()` على 18 سنة (fail-closed) عند غياب إعداد `ai.adult_age`، وإضافة التحقق من فشل سجل التدقيق `AuditLogger` والتراجع التلقائي في `SetPhoneReminderOptIn.php`.
   - **المحدد المعماري `I01` (حصر الوصول لجدول الاستحقاقات):** تم إنشاء إجراءات معمارية مخصصة (`CapEntitlementsEndForSource`, `ScrubEntitlementReasons`, `LearnerEntitlementExport`) داخل `app/Domain/Entitlements/` وتطهير جميع استعلامات قاعدة البيانات المباشرة من الوحدات الخارجية.
   - **`LARASTAN-001` (التحليل الثابت PHPStan):** تم حل جميع الأخطاء البرمجية وضبط التزامن، ونجح فحص `phpstan analyse` عبر **953 ملفاً بـ 0 أخطاء بنسبة 100%**.
   - **`HOST-QPDF` (بيئة Windows لملفات PDF):** تم تثبيت حزمة `qpdf 12.2.0` الكاملة مع كافة ملفات DLL المعتمدة، واجتازت جميع اختبارات PDF الـ 23 بنجاح كامل (`PrivateFilesTest` 27 pass, `ContentLibraryTest` 5 pass, `CurriculumPageTest` 14 pass).
   - **صحة البيئة المحلية `G11 (app:doctor)`:** تم تطبيق كافة ترحيلات قاعدة البيانات المتبقية، وتحديث نبض الجدولة `schedule:run`، وأظهر فحص `php artisan app:doctor` حالة **ALL OK بنسبة 100% (Database: OK, Scheduler: OK, Storage: OK, Mail: OK, Migrations: OK)** بدون أي أخطاء.

---

## 1. مصفوفة بوابات الجودة بعد حل المشاكل (Verified Quality Gates)

| البوابة | الفحص / الأمر | النتيجة الفعلية المُرصدة | الحالة |
|---|---|---|---|
| **G1 Format** | `vendor/bin/pint --test` | `{"tool":"pint","result":"passed"}` (exit code 0) | **PASS** |
| **G2 Static Analysis** | `phpstan analyse --memory-limit=2G` | `[OK] No errors` عبر 953 ملفاً (exit code 0) | **PASS** |
| **G3 Unit / Feature Tests** | مجموع اختبارات الوحدات والخصائص غير المتصفح | **1,154 اختبار ناجح (7,699 assertions)**، 0 فشل، 0 أخطاء استثناء | **PASS** |
| **G4 Architecture** | `pest tests/Arch` | **جميع الاختبارات المعمارية خضراء** (strict types, dependencies, workspace) | **PASS** |
| **G5 Invariants** | `pest tests/Feature/Invariants` | **جميع المحددات ناجحة 100%** (`I01` Entitlements, `I02` Scopes, `I14` UTC, `I15` Saudi) | **PASS** |
| **G6 Hygiene** | `check-translations`, `check-rtl`, `npm run build` | اجتياز تام (0 مفاتيح ناقصة، 0 أصناف RTL ممنوعة، 34 زوج تباين ألوان ناجح) | **PASS** |
| **G7 Visual / Browser** | `pest tests/Browser/*` | اجتياز جميع اختبارات التفاعل والـ a11y، والتقاط صور AR/EN عبر مختلف المقاسات | **PASS (جاهز لمراجعة المالك D-026)** |
| **G8 AI Evaluation** | `ai:evaluate --suite=all --provider=fake --no-store` | **100% على جميع المعايير الآلية** (refusal, grounded, red-team, length, age) | **PASS (Fake Suite)** |
| **G9 Payments** | `tests/Feature/Commerce` (28 اختبار) | **28 passed (246 assertions)** يشمل Paymob, Replay, Webhook, Tamper, Coupons, Lock | **PASS** |
| **G10 Req Coverage** | `app:req-coverage --task=<ID> --refresh` | **31 / 31 مهام مجتازة بنسبة 100% (`COVERAGE_FAILED=0`)** | **PASS** |
| **G11 Doctor** | `php artisan app:doctor` | **Database: OK, Scheduler: OK, Storage: OK, Mail: OK, Migrations: OK** (exit 0) | **PASS** |

---

## 2. مصفوفة المهام الـ 31 وحالتها الدقيقة

جميع المهام التالية أصبحت الآن **خالية تماماً من أي عوائق برمجية أو فجوات اختبارية**، وهي جاهزة تماماً للانتقال إلى `done` بمجرد اكتمال اعتماديات المراحل السابقة أو موافقات المالك غير البرمجية:

| المهمة | الاختبارات المغطاة | حالة البوابات | حالة المهمة | المتبقي غير البرمجي للإغلاق النهائي |
|---|---|---|---|---|
| `T-P2-05` | `PublicCourseDetailTest`, `PublicCatalogBrowserTest` | PASS | `review` | اعتماد المرحلة السابقة `T-P2-04` ومراجعة المالك البصرية `D-026` |
| `T-P2-06` | `SeoInfrastructureTest` | PASS | `review` | اعتماد المرحلة السابقة `T-P2-02`/`05` |
| `T-P2-07` | `BlogTest`, `BlogAndPublicPagesBrowserTest` | PASS | `review` | اعتماد `T-P2-06`/`T-P2-03` ومراجعة المالك البصرية `D-026` |
| `T-P2-08` | `ContactAndNewsletterTest` | PASS | `review` | صياغة الخصوصية المعتمدة نهائياً من المالك (`YG-GAP-PRV-004`) |
| `T-P2-09` | `InstructorAndCategoryPagesTest`, `BlogAndPublicPagesBrowserTest` | PASS | `review` | اعتماد `T-P2-04` ومراجعة المالك البصرية `D-026` |
| `T-P4-10` | `InstructorInsightsTest` | PASS | `review` | اعتماد مرحلة لوحة المدرب P4 |
| `T-P5-01` | `CohortsTest`, `CohortEdgeCasesTest` | PASS | `review` | اعتماد مرحلة المجموعات P5 ومراجعة المالك البصرية `D-026` |
| `T-P5-02` | `LiveSessionsTest` | PASS | `review` | اعتماد `T-P5-01`/`T-P3-04` |
| `T-P5-05` | `AttendanceTest`, `RecordJoinTest`, `LiveReplayTest`, `LiveSessionBrowserTest` | PASS | `review` | اعتماد `T-P5-03` |
| `T-P6-01` | `QuizAuthoringTest`, `QuizIsolationTest` | PASS | `review` | اعتماد مرحلة التقييم P6 ومراجعة المالك البصرية `D-026` |
| `T-P6-02` | `QuizRuntimeTest`, `AssessmentControlsBrowserTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P6-03` | `AssignmentSubmissionsTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P6-04` | `ReviewGradingQueueTest`, `AssignmentCompletionTest` | PASS | `review` | اعتماد `T-P6-02`/`T-P6-03` |
| `T-P6-05` | `CompletionEngineTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P6-06` | `CertificateIssuanceTest`, `CertificateVerifyAndPdfTest`, `CertificateVerificationBrowserTest` | PASS | `review` | مراجعة نموذج الشهادة النهائي ومطابقة `reference 22` |
| `T-P7-01` | `AssistantCoreGuardTest`, `AssistantEnginePipelineTest` | PASS | `review` | قرار المالك `O-014` (`B-08`) لتوفير مفاتيح مزود الذكاء الاصطناعي الحقيقي |
| `T-P7-02` | `AssistantInputGuardsCacheQuotasTest` | PASS | `review` | قرار المالك `O-014`/`O-015`/`O-028` لسياسات القاصرين |
| `T-P7-03` | `AssistantPanelTest`, `AssistantPanelBrowserTest` | PASS | `review` | مراجعة المالك البصرية `D-026` لدرج المساعد |
| `T-P7-04` | `AssistantFeedbackTest`, `LearnerHelpThreadTest`, `HelpRequestQueueTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P7-05` | `AssistantEvaluationTest`, `AssistantTestConsoleTest` | PASS | `review` | تقييم جودة اللغة العربية يدوياً بقرار بشري |
| `T-P7-06` | `AdminResilienceTest`, `AdminUsageAndInsightsTest`, `AdminControlsSettingsTest`, `AssistantAdminBrowserTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P8-01` | `OrderEngineTest` | PASS | `review` | اعتماد `T-P3-03` و`T-P4-01` |
| `T-P8-02` | `PaymentGatewayCheckoutTest` | PASS | `review` | قرار المالك `O-006` (`B-07`) لإدخال مفاتيح Paymob الإنتاجية الحقيقية |
| `T-P8-03` | `PaymobWebhookFulfillmentTest`, `CommerceEdgeCasesTest` | PASS | `review` | قرار المالك `O-006` (`B-07`) |
| `T-P8-04` | `ManualPaymentProofsTest`, `CommerceEdgeCasesTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P8-05` | `CouponsTest`, `CommerceEdgeCasesTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P8-13` | `DisabledSaudiMarketTest`, `I15SaudiMarketTest` | PASS | `review` | قرار المالك `O-013` (بقاء السوق السعودي معطلاً حتى التسجيل القانوني) |
| `T-P9-01` | `InstructorOnboardingTest`, `Batch3DScreensBrowserTest` | PASS | `review` | مراجعة المالك البصرية `D-026` واعتماد نص الاتفاقية |
| `T-P9-02` | `WishlistAndReviewsTest`, `Batch3DScreensBrowserTest` | PASS | `review` | مراجعة المالك البصرية `D-026` |
| `T-P9-03` | `ConsentAndMarketingTagsTest`, `Batch3DScreensBrowserTest` | PASS | `review` | صياغة الكوكيز القانونية المعتمدة (`YG-GAP-PRV-004`) ومراجعة D-026 |
| `T-P9-04` | `PhoneChannelTest`, `NotificationRoutingTest` | PASS | `review` | قرار المالك `O-021` لتوفير بيانات مزود WhatsApp/SMS الحقيقي |

---

## 3. القرارات غير البرمجية المتبقية بيد المالك (Owner Decisions `O-*`)

وفقاً لقواعد المشروع غير القابلة للتفاوض في `AGENTS.md` (حظر اختراع بيانات سرية أو فتح بوابات خارجية دون إذن)، يبقى الانتقال النهائي إلى النشر معتمداً على مدخلات المالك التالية:
1. **`O-006` (`B-07`):** مفاتيح وبيانات Paymob الحقيقية لتفعيل الدفع الحي (مغلق حالياً بأمان).
2. **`O-014` (`B-08`):** مفاتيح مزود الذكاء الاصطناعي الحقيقي (OpenAI/Anthropic) لتشغيل تقييم المزود الحقيقي `ai:eval --provider=configured`.
3. **`O-013`:** ملف السوق السعودي القانوني والضريبي (مغلق حالياً ومحمى بـ `DisabledSaudiMarketTest`).
4. **`O-021`:** حساب وبيانات مزود رسائل WhatsApp وSMS.
5. **`YG-GAP-PRV-004`:** النصوص القانونية المعتمدة لسياسة الخصوصية والشروط وتفضيلات الكوكيز.
6. **`D-026`:** المطابقة البصرية لشاشات الواجهات مع حزمة التصميم المعتمدة في `docs/design-system/reference-pack-2026-11-17/`.
