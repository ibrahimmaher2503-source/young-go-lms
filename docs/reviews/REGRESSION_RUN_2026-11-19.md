# تقرير تشغيل الانحدار الشامل والإصلاحات (REGRESSION_RUN_2026-11-19)

- **المستودع والفرع:** `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2` — الفرع `feat/lms-build`
- **تاريخ التشغيل:** 2026-11-19
- **البيئة:** PHP 8.4.21 · Laravel 13 · Pest 5 · Filament 5.11 · Livewire 4.4 · SQLite (`:memory:`) · Standalone QPDF 12.2/12.4

---

## 1. جدول نتائج مجموعات الاختبار (حسب المجلد / الحزمة)

| المجموعة / المجلد | إجمالي الاختبارات (Tests) | التحققات (Assertions) | ناجح (Passed) | فاشل (Failed) | متخطى / مؤجل (Skip / Todo) | الزمن (Time) |
|---|---:|---:|---:|---:|---:|---:|
| `tests/Arch` | 6 | 63 | 6 | 0 | 0 | 19.26s |
| `tests/Feature/Invariants` | 11 | 44 | 11 | 0 | 0 | 8.59s |
| `tests/Unit` | 29 | 139 | 29 | 0 | 0 | 9.42s |
| `tests/Feature/AI` | 154 | 1,356 | 154 | 0 | 0 | 101.67s |
| `tests/Feature/Assessment` | 46 | 304 | 46 | 0 | 0 | 30.05s |
| `tests/Feature/Assignments` | 6 | 128 | 6 | 0 | 0 | 8.31s |
| `tests/Feature/Audit` | 4 | 36 | 4 | 0 | 0 | 4.42s |
| `tests/Feature/Brand` | 10 | 15 | 10 | 0 | 0 | 21.20s |
| `tests/Feature/Catalog` | 119 | 546 | 119 | 0 | 0 | 74.04s |
| `tests/Feature/Cohorts` | 25 | 87 | 25 | 0 | 0 | 15.49s |
| `tests/Feature/Commerce` | 465 | 2,917 | 459 | 0 | 4 skipped, 2 todo | 274.66s |
| `tests/Feature/Content` | 19 | 177 | 19 | 0 | 0 | 12.51s |
| `tests/Feature/Curriculum` | 33 | 191 | 33 | 0 | 0 | 24.15s |
| `tests/Feature/Decisions` | 22 | 60 | 21 | 0 | 1 todo | 8.16s |
| `tests/Feature/Demo` | 15 | 156 | 15 | 0 | 0 | 53.25s |
| `tests/Feature/Engagement` | 20 | 155 | 20 | 0 | 0 | 15.22s |
| `tests/Feature/Entitlements` | 64 | 284 | 63 | 0 | 1 todo | 41.41s |
| `tests/Feature/Filament` | 149 | 1,259 | 149 | 0 | 0 | 118.33s |
| `tests/Feature/Identity` | 144 | 766 | 144 | 0 | 0 | 81.00s |
| `tests/Feature/InstructorFinance` | 116 | 694 | 113 | 0 | 3 todo | 77.90s |
| `tests/Feature/Instructors` | 17 | 231 | 17 | 0 | 0 | 12.07s |
| `tests/Feature/Learning` | 74 | 514 | 74 | 0 | 0 | 48.23s |
| `tests/Feature/Library` | 13 | 96 | 13 | 0 | 0 | 9.26s |
| `tests/Feature/Live` | 54 | 266 | 54 | 0 | 0 | 31.99s |
| `tests/Feature/Media` | 157 | 543 | 157 | 0 | 0 | 89.86s |
| `tests/Feature/Milestone` | 1 | 87 | 1 | 0 | 0 | 3.87s |
| `tests/Feature/Notifications` | 90 | 1,181 | 90 | 0 | 0 | 48.19s |
| `tests/Feature/Pages` | 173 | 1,081 | 173 | 0 | 0 | 103.06s |
| `tests/Feature/Privacy` | 39 | 426 | 39 | 0 | 0 | 25.56s |
| `tests/Feature/Reports` | 44 | 695 | 44 | 0 | 0 | 28.55s |
| `tests/Feature/Seo` | 2 | 42 | 2 | 0 | 0 | 3.96s |
| `tests/Feature/Settings` | 58 | 386 | 57 | 0 | 1 todo | 38.31s |
| `tests/Feature/Shared` | 5 | 7 | 5 | 0 | 0 | 1.91s |
| `tests/Feature/Subscriptions` | 52 | 326 | 49 | 0 | 3 todo | 33.12s |
| `tests/Feature/Support` | 14 | 73 | 14 | 0 | 0 | 9.48s |
| `tests/Feature/*.php` (ملفات الجذر) | 89 | 315 | 89 | 0 | 0 | 33.78s |
| **التشغيل الموحد الشامل (`composer qa` / `--exclude-group=browser`)** | **2,339** | **15,646** | **2,324** | **0** | **4 skipped, 11 todo** | **1525.13s** |

---

## 2. جدول الإصلاحات المنفذة (Fixes)

| المشكلة (Problem) | السبب الجذري (Root Cause) | الملف والسطر (File & Line) | الإصلاح (The Fix) | الالتزام (Commit) | المهمة المرتبطة (Task) |
|---|---|---|---|---|---|
| فشل الاختبار `Tests\Feature\Pages\G7FixesLearner360LabelsTest > it F7 notice kinds render localized labels and unknown kinds fall back to Notice` (`Failed asserting that 4 is identical to 2` عند السطر 117) | بعد ربط إشعارات منح الوصول (`AccessNotice`) داخل `GrantEntitlement` في الالتزام `20b9409` (`T-P9-05`)، أصبح منح استحقاقين في بيانات الاختبار ينشئ سجلين إضافيين في `notification_dispatches` بالاسم `AccessNotice`. ولأن الدالة `Learner360Record::noticeLabel()` تحوّل اسم الصنف عبر `Str::snake(Str::beforeLast($class, 'Notice'))` إلى المفتاح `access` وتبحث عنه في `support.notice_kinds.*`، فإن غياب المفتاح `access` (وكذلك `assignment` و`certificate_revoked` و`guardian_otp`) من ملفات الترجمة أدى إلى سقوط `AccessNotice` في التصنيف الاحتياطي العام `other` (`Notice` / `إشعار`) بدلاً من عرض نوع الإشعار المترجم الخاص به. | `lang/en/support.php:62-76`<br>`lang/ar/support.php:62-76`<br>(تُقرأ من `app/Domain/Support/Queries/Learner360Record.php:151-156`) | إضافة مفاتيح أنواع الإشعارات المترجمة الناقصة (`access`, `assignment`, `certificate_revoked`, `guardian_otp`) باللغتين العربية والإنجليزية داخل `support.notice_kinds` دون المساس بكود الاختبار. | `d4121ee` | `T-P9-10` / `T-P9-05` |

---

## 3. قائمة `NEEDS-OWNER` (نقاط متوقفة على قرار المالك)

1. **`T-DECISIONS-2026-11-18` (`tests/Feature/Decisions/DecisionsRegisterDocsTest.php:34-36`) — تعارض وثائقي حول `O-026` و`O-027`:**
   - سجل القرارات `docs/07_DECISION_REGISTER.md` يضع في القسم `C.2` علامة `DECIDED 2026-11-18 (PO)` أمام `O-026` و`O-027`، بينما يفترض اختبار `DecisionsRegisterDocsTest.php:35` عدم وجود `DECIDED 2026-11-18` في القسم `C`. لم نعدّل الاختبار أو الوثيقة لأن حسم الصياغة المعتمدة في سجل القرارات قرار مالك (`NEEDS-OWNER`).
2. **`T-P8-01` / `T-P12-01` (`tests/Feature/Commerce/PricingOrderEngineAcceptanceTest.php:178-200`) — آلية تفعيل السوق السعودي `Market::isActive()`:**
   - الاختباران المتخطيان يفترضان أن تغيير `config(['markets.SA.active' => true])` يفعّل السوق السعودي للوصول إلى فحص غياب سعر `SAR` أو اختلاف العملة، بينما `Market::isActive()` يقفل السوق السعودي برمجياً (`D-010` / `T-P12-01`) ويتطلب سجل اعتماد مالك صريح عبر `EnableGatedFlag`. البتّ في إبقاء القفل البرمجي الصارم مقابل الاعتماد على `config('markets.SA.active')` متروك للمالك في `T-P12-01`.
3. **المدخلات الخارجية المفتوحة في سجل القرارات (تحديث المالك 2026-11-19):**
   - `O-001` / `O-003` (النطاق الفعلي وقاعدة بيانات النظام القديم للقراءة فقط — تحجب `T-P10-01`).
   - `O-006` / `O-007` / `O-023` (بيانات اعتماد Paymob الحية والبيانات الضريبية والقانونية المصرية وإلزامية الإيصال الإلكتروني ETA — تحجب التفعيل الحي في `T-P8-MG`).
   - `O-013` (الكيان القانوني والضريبي السعودي — `T-P12-01`).
   - `O-014` / `O-015` / `O-028` (مفتاح OpenAI الحي ومنطقة البيانات والمراجعة القانونية لخصوصية القُصّر وصياغة بلاغات الحماية — تحجب `T-P7-07`).
   - `O-019` (الصياغة القانونية لجهة إصدار الشهادات).

---

## 4. حالة الخلل `BUG-007` مع الأدلة

- **الحالة:** **تم الإصلاح والتحقق بالكامل (`Fixed & Verified` — لم يعد حاجزاً).**
- **تفاصيل الإصلاح:**
  - كان الخلل في `app/Domain/Commerce/Order.php:52` ناتجاً عن تحويل قيمة `$order->getOriginal('status')` (وهي كائن من نوع `OrderStatus` Enum بعد تطبيق الـ Cast) مباشرةً إلى `string` في الحدث `Order::updating`.
  - تم إصلاحه في الالتزام **`4a99365`** (`feat(commerce): refunds, payment history, receipts and e-receipt adapter (T-P8-06)`) عبر التحقق الآمن من النوع:
    ```php
    $raw = $order->getOriginal('status');
    $original = $raw instanceof OrderStatus ? $raw : OrderStatus::tryFrom((string) $raw);
    ```
  - تم إلغاء علامة `todo()` عن كافة الاختبارات المرتبطة به في الالتزام **`a992726`**.
- **دليل التشغيل الفعلي في هذا الفحص (0 `todo`، 0 `fail`، 0 `skip`):**
  1. `Tests\Feature\Commerce\OrderEngineTest`: **4 ناجحة** (0 `todo`).
  2. `Tests\Feature\Commerce\CouponsTest`: **2 ناجحة** (0 `todo`).
  3. `Tests\Feature\Commerce\PaymobWebhookFulfillmentTest`: **4 ناجحة** (0 `todo`).
  4. `Tests\Feature\Commerce\ManualPaymentProofsTest`: **2 ناجحة** (0 `todo`، 0 `skip` بعد توفر `qpdf.exe`).
  5. `Tests\Feature\Commerce\CommerceEdgeCasesTest`: **12 ناجحة** (0 `todo`).
  - **الإجمالي للملفات الخمسة:** **24 اختباراً ناجحاً من أصل 24 (0 `todo`، 0 `failed`)**.
- **تحديث اللوحة:** تم تحديث ملاحظات المهام `T-P8-01` إلى `T-P8-05` في `agents/PROGRESS.md` والجدول المرجعي في `docs/reviews/BUGS_FOUND.md` لإزالة وصم `BUG-007` كحاجز مفتوح وتثبيت رقم الالتزام `4a99365` وعدد الاختبارات.

---

## 5. قائمة شاملة بجميع اختبارات `skip` و`todo` وأسبابها

### أ) الاختبارات المتخطاة (`->skip()` — إجمالي 4 في التشغيل الفعلي)

| # | الملف والسطر | اسم الاختبار | السبب المسجل | مرشح للتفعيل؟ |
|---:|---|---|---|---|
| 1 | `tests/Feature/Commerce/CouponAcceptanceTest.php:354` | `a true parallel redemption race is covered by the row-locked guarded update, not by this suite` | قاعدة بيانات الاختبار `SQLite :memory:` في اتصال واحد لا تدعم المعاملات المتوازية الحقيقية (يتم فحص التحديث المحمي تسلسلياً في الاختبار السابق له). | لا (يتطلب بيئة MySQL متعددة الاتصالات). |
| 2 | `tests/Feature/Commerce/ManualPaymentProofAcceptanceTest.php:258` | `a true parallel double approve is covered by the database lock, not by this suite` | `SQLite :memory:` لا تدعم سباق المعاملات المتوازية (تمت تغطية الاعتماد والرفض المزدوج تسلسلياً باختبارات التكرار Idempotency). | لا (يتطلب بيئة MySQL متعددة الاتصالات). |
| 3 | `tests/Feature/Commerce/PricingOrderEngineAcceptanceTest.php:188` | `refuses a Saudi order with price_unavailable when the market is enabled and no SAR price exists` | `Market::isActive()` يبقي السوق السعودي مغلقاً برمجياً حتى عند تفعيل `config('markets.SA.active')` لأن التفعيل مرهون بمهمة `T-P12-01`. | ينتظر قرار المالك / `T-P12-01` (`NEEDS-OWNER`). |
| 4 | `tests/Feature/Commerce/PricingOrderEngineAcceptanceTest.php:200` | `refuses a Saudi price row whose currency is not SAR` | نفس السبب أعلاه (`Market::isActive()` يقفل السوق السعودي قبل الوصول إلى فحص عملة السعر). | ينتظر قرار المالك / `T-P12-01` (`NEEDS-OWNER`). |

*(ملاحظة: توجد شروط تخطٍّ احترازية `skip(fn () => ! p804Qpdf())` و`markTestSkipped` عند غياب `qpdf` أو `imagewebp` في `ManualPaymentProofAcceptanceTest.php:595,640,656`، وبما أن `qpdf` و`GD WebP` يعملان على هذا الجهاز فقد **نفّذت هذه الاختبارات ونجحت بالكامل** ولم تُتخطَّ).*

### ب) الاختبارات المؤجلة (`->todo()` — إجمالي 11)

| # | الملف والسطر | اسم الاختبار | السبب المسجل | مرشح للتفعيل؟ |
|---:|---|---|---|---|
| 1 | `tests/Feature/Commerce/BundleAllocationTest.php:591` | `webhook-fulfils a paid bundle order and refuses plan-line flags (not covered by this test file)` | عنصر نائب في ملف اختبار الباقات (تغطية الويب هوك موجودة في ملفات اختبار الويب هوك والاشتراكات). | **نعم (مرشح للتفعيل)** — منطق `FulfillOrder` والويب هوك للباقات منفذ وجاهز. |
| 2 | `tests/Feature/Commerce/BundleAllocationTest.php:595` | `renders the bundle list and allocation modal for finance and admin in AR and EN (Filament, not covered by this test file)` | عنصر نائب لواجهة Filament لإدارة الباقات باللغتين. | **نعم (مرشح للتفعيل)** — مورد `BundleResource` منفذ في Filament. |
| 3 | `tests/Feature/Decisions/DecisionsRegisterDocsTest.php:36` | `leaves O-026 and O-027 unmarked as the prompt requires` | تعارض بين سجل القرارات (الذي يضع `DECIDED 2026-11-18 (PO)` في القسم C.2) وبين نص الاختبار. | ينتظر حسم المالك (`NEEDS-OWNER`). |
| 4 | `tests/Feature/Entitlements/EntitlementServiceTest.php:241` | `keeps the invariant scaffolds for commerce sources that arrive in P8` | سقالة تاريخية من المرحلة P4 قبل بناء مصادر التجارة في P8. | **نعم (مرشح للتفعيل)** — مصادر التجارة (`Purchase`, `Bundle`, `Subscription`, `ManualPayment`) اكتملت في P8. |
| 5 | `tests/Feature/InstructorFinance/CommissionLedgerTest.php:339` | `is not covered yet: changing learner progress or completion rows leaves every ledger amount unchanged` | اختبار إضافي لقاعدة `D-013` (عدم ارتباط عمولة المدرب بنسب المشاهدة أو الإكمال). | **نعم (مرشح للتفعيل)** — دفتر الأستاذ معزول تماماً عن جدول التقدم. |
| 6 | `tests/Feature/InstructorFinance/CommissionLedgerTest.php:518` | `is not covered yet: Filament rule, ledger and my-earnings pages with their actions and role access` | اختبار صفحات Filament لقواعد العمولة ودفتر الأستاذ وأرباح المدرب. | **نعم (مرشح للتفعيل)** — الموارد (`CommissionRuleResource`, `InstructorLedgerResource`, `MyEarningsResource`) منفذة. |
| 7 | `tests/Feature/InstructorFinance/CommissionLedgerTest.php:522` | `is not covered yet: the listener never fails the payment and ledger:backfill repairs a failed earning` | اختبار صمود المستمع `AccrueInstructorEarnings` والأمر `ledger:backfill`. | **نعم (مرشح للتفعيل)** — كلاهما منفذ في `App\Domain\InstructorFinance` ومغطى جزئياً في `CommissionLedgerAcceptanceTest`. |
| 8 | `tests/Feature/Settings/TPassMarkSafeguardingRecipientsTest.php:134` | `rolls the setting back when the audit cannot be recorded` | الصنف `AuditLogger` معرّف كـ `final`، ولا يوجد حالياً منفذ حقن خطأ (Fault Seam) لإجبار `AuditLogger::record` على الفشل داخل هذا الاختبار دون كسر قاعدة `final`. | لا (مقيد بقاعدة معمارية `final` في `tests/Arch/DomainTest.php` ما لم يُعتمد seam). |
| 9 | `tests/Feature/Subscriptions/SubscriptionPlansTest.php:186` | `is not covered yet: a plan built from a category contains only that category subscription and both courses` | اختبار بناء خطة اشتراك مرتبطة بتصنيف (`category_ids`). | **نعم (مرشح للتفعيل)** — ربط الخطط بالتصنيفات منفذ في `SavePlan`. |
| 10 | `tests/Feature/Subscriptions/SubscriptionPlansTest.php:247` | `is not covered yet: the notice goes to the payer in their locale, an opted-out payer gets none and a failing send leaves no row` | اختبار لغة إشعار تجديد الاشتراك وخيار إلغاء الاشتراك وعدم ترك سجل عند الفشل. | **نعم (مرشح للتفعيل)** — منطق `SendSubscriptionRenewalReminders` و`NotificationDispatcher` منفذ. |
| 11 | `tests/Feature/Subscriptions/SubscriptionPlansTest.php:568` | `is not covered yet: Filament plan and subscription pages, summary tiles and the cancel action` | اختبار صفحات Filament لخطط الاشتراك والاشتراكات وبطاقات الملخص. | **نعم (مرشح للتفعيل)** — `PlanResource` و`SubscriptionResource` منفذان. |

---

## 6. فجوات التغطية الملحوظة (Coverage Gaps — بدون كتابة ملفات اختبار جديدة)

1. **تحقق صريح من ترجمة أنواع الإشعارات الجديدة في `G7FixesLearner360LabelsTest.php:154`:**
   - مصفوفة التحقق في السطر 154 تفحص المفاتيح التسعة القديمة في `support.notice_kinds`؛ يُستحسن مستقبلاً تضمين المفاتيح الأربعة المضافة (`access`, `assignment`, `certificate_revoked`, `guardian_otp`) داخل تلك الحلقة أيضاً.
2. **قراءة الإعدادين `default_locale` و`default_market` وقت التشغيل (`T-P9-09`):**
   - يتم حفظ `default_locale` و`default_market` في `GeneralSettings`، لكن وسطاء تحديد اللغة والسوق (`ResolveLocale` / `MarketResolver`) يقرآن من إعدادات `config()` الثابتة بدلاً من `GeneralSettings` وقت التشغيل.
3. **إغلاق جلسات `View-As` المنتهية عبر المجدول (`T-P9-10`):**
   - جلسات العرض كمتعلم (`ViewAsSession`) تُغلق بشكل كسول (Lazily) عند الطلب التالي أو عند بدء جلسة جديدة، ولا يوجد أمر مجدول لتنظيف الجلسات المنتهية التي لم يتبعها طلب.
4. **الاختبارات الـ 8 المؤجلة (`->todo()`) المرشحة للتفعيل في الجدول أعلاه (`BundleAllocationTest`, `EntitlementServiceTest`, `CommissionLedgerTest`, `SubscriptionPlansTest`).**

---

## 7. نتائج فحوصات الجودة (Quality Checks)

| الفحص (Check) | الأمر (Command) | النتيجة (Result) | التفاصيل |
|---|---|---|---|
| **G1 Format (Pint)** | `vendor/bin/pint --test` | **PASS** (Exit 0) | `{"tool":"pint","result":"passed"}` — لا توجد أي مخالفات تنسيق. |
| **G2 Static Analysis (Larastan / PHPStan)** | `php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress` | **PASS** (Exit 0) | `[OK] No errors` عبر كامل ملفات المشروع. |
| **G6 Translations Parity** | `php artisan app:check-translations` | **PASS** (Exit 0) | `Translation key check passed.` — تطابق تام بين `lang/ar` و`lang/en`. |
| **G6 RTL Logical CSS** | `php artisan app:check-rtl` | **PASS** (Exit 0) | `RTL utility check passed.` — خلو القوالب من فئات الاتجاه الفيزيائية المحظورة. |
| **Brand Tokens & Contrast** | `php artisan app:check-brand-tokens` & `npm run build` | **PASS** (Exit 0) | `Brand token check passed.` + `AA contrast passed for 34 light/dark pairs.` + بناء Vite ناجح. |
| **Combined QA (`composer qa`)** | `COMPOSER_PROCESS_TIMEOUT=0 composer qa` | **PASS** (Exit 0) | نجح `pint` + `phpstan` + كامل اختبارات `pest --exclude-group=browser` (2,324 اختباراً ناجحاً، 0 فشل). |

---

## 8. الحالة النهائية (Final Status)

- **هل حزمة الاختبارات بالكامل خضراء (Green)؟**
  - **نعم — خضراء بالكامل (100% GREEN، 0 Failures).**
  - إجمالي تشغيل `composer qa` الموحد: **2,339 اختباراً — 2,324 ناجحاً، 0 فاشل، 4 متخطاة (`skip`)، 11 مؤجلة (`todo`)، مع 15,646 تحققاً (`assertions`)، وجميع فحوصات الجودة (`pint`, `phpstan`, `app:check-translations`, `app:check-rtl`, `app:check-brand-tokens`, `npm run build`) ناجحة برمز خروج `0`.**
