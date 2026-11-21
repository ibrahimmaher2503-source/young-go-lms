# Security & Code Findings Resolution Report — 2026-11-21

**Branch:** `feat/lms-build`
**Inputs audited:**
- [SECURITY_REVIEW_2026-11-20.md](SECURITY_REVIEW_2026-11-20.md)
- `GAP-CODE` list in [EVIDENCE_AUDIT_P0_P7.md](EVIDENCE_AUDIT_P0_P7.md)
- Bug list in [DEMO_REHEARSAL_2026-11-20.md](DEMO_REHEARSAL_2026-11-20.md)

---

## 1. Findings Matrix (Critical → High → Medium)

| Finding | Fix | Commit | Tests Run | Test Prompt |
|---|---|---|---|---|
| **T-P4-02 (Critical GAP-CODE):** Free course self-enrollment & parent child-enrollment had no HTTP route/controller/CTA | Wired authenticated `POST /courses/{course}/enroll` (`CourseEnrollController`) invoking `EnrollInFreeCourse`, plus active CTA in `catalog/_cta.blade.php` | `01f9229` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants`, `EnrollmentAndGrantsTest` | Already covered / `docs/test-prompts/T-P4-02` |
| **High (Security Review #1):** Private upload forms trusted existing storage paths | Enforced temporary upload objects (`storeFiles(false)`), path tampering guard, MIME/type validation, and owned-temp cleanup across all 5 upload handlers | `b74d0cf` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **High (Security Review #2):** Verified Paymob transaction not bound to its checkout attempt | Bound verified Paymob callbacks to the immutable signed `order.id` and trusted checkout attempt record; fail-closed on mismatch | `0f46b30` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **High (GAP-CODE #2 / T-P7-02 + Security Review #4):** `AssistantEligibility::isAdult()` split from `StyleTier`, and `BudgetGate` concurrency / proposed-cost check | Unified adult band resolution with `AgeBandResolver`; `BudgetGate` locks monthly sentinel row and checks `$used + $amount > $ceiling` before admission | `27d86e2` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **High (GAP-CODE #3 / T-P4-09):** Relative-to-cohort drip (`unlock_rule = 'days'`) ignored seated cohort for lessons/quizzes/assignments | Resolved learner's seated cohort `starts_on` for non-`LiveSession` items in `EntitlementService` | `43dae02` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **High (GAP-CODE #4 / T-P3-12):** `DeleteCourse` lacked `orders`/`order_items` history check & lock; `DuplicateCourse::uniqueSlug()` overflowed 120 chars | Added `OrderHistory` checker under `'course.history'`, transaction lock on delete, and 120-char slug clamping in `DuplicateCourse` | `2ef3d88` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **P1 Demo Bug (J06):** Seeded live/hybrid courses had 0 `live_sessions` | Seeded open and upcoming live sessions in `DemoSeeder` | `0f591d4` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **Medium (Security Review #3):** Support `view-as` session retained access after eligibility changes | Rechecked permissions and `ViewAsEligibility` on `ViewAsState::current()` redemption and audited forced termination | `d67a860` *(verified present at HEAD)* | `tests/Feature/Support` (14 passed), `Arch`, `Invariants` | Covered |
| **Medium (GAP-CODE #5 / T-P3-01):** Course list CSV export omitted PRD §9.1 columns & table lacked sorting | Added PRD §9.1 export columns in `ListCourses.php` and `->sortable()` in `CourseResource.php` | `789f8e7` *(verified present at HEAD)* | `tests/Arch`, `tests/Feature/Invariants` | Covered |
| **Medium (Security Review #5):** Default Telescope telemetry could retain sensitive request details on payment/proof/identity routes | Added missing `my/orders/*/payment-proof*`, `my/payments*`, `login`, `register`, `forgot-password`, `reset-password*`, `guardian/*` to `config/telescope.php` `ignore_paths`; filtered ignored URIs out of `Telescope::filter()` outside local; expanded `hideRequestParameters()` with `child_password`, `code`, `recovery_code*`, `sender_account`, `sender_name`, `transfer_reference`, `payout_details`, `instapay_address` (`enabled` already defaults to `APP_ENV === 'local'`) | **`a79a7b5`** | `TelescopePrivacyTest` (11 passed), `Arch` (6 passed), `Invariants` (11 passed) | [SEC-01.md](../test-prompts/SEC-01.md) |
| **Medium (Demo Bug P2):** Missing accessible link name in Help Requests SLA column (`.fi-ta-cell-overdue > .fi-ta-col`) | Added `->placeholder('-')` on `TextColumn::make('overdue')` in `HelpRequestResource.php` so non-overdue cells never render empty `<a>` links | **`46e5271`** | `HelpRequestQueueTest`, `Arch`, `Invariants` | [SEC-02.md](../test-prompts/SEC-02.md) |
| **Medium (Demo Bug P2):** Scrollable report & admin tables unfocusable via keyboard (`scrollable-region-focusable`) | Added `tabindex="0"` (and `role="region"` + `aria-label` on Reports) to `.yg-ai-scroll` in `reports/index.blade.php`, `ai/usage.blade.php`, and `commerce/reconciliation.blade.php` | **`60b6095`**, **`dc01698`** | `tests/Feature/Reports` (44 passed), `Arch`, `Invariants` | [SEC-02.md](../test-prompts/SEC-02.md) |
| **Medium (Demo Bug P2):** 404 on browser `Back` to `/fake-gateway/{closed}` after payment completes | **By design / Test-locked (`FakeGatewayPageTest.php:100`):** existing acceptance test (`T-P8-GAPS A1`) explicitly asserts `GET /fake-gateway/{closed}` returns `404` so completed payment attempts cannot be revisited. Not changed without owner decision (`NEEDS-OWNER` if redirect to `checkout.return` is desired instead of `404`). | — *(no change)* | `FakeGatewayPageTest` | — |
| **Medium (Demo Bug P2):** `startViewAs` modal contrast in `AR 1440` (`color-contrast`) | Checked `tokens.css` (`--yg-color-muted` = `--yg-neutral-700`, `--yg-color-primary` = `--yg-brand-600`) and `filament-theme.css:488` (`.fi-modal-description` already mapped to `var(--yg-color-muted)`). Requires live Playwright re-verification on `AR 1440` modal open state (`filament-theme.css` has concurrent uncommitted changes in working tree). | — *(deferred to live browser check)* | `npm run build` (`34/34` token contrast pairs PASS) | — |

---

## 2. ملخص بالعربية

تمت مراجعة كافة الملاحظات الأمنية وفجوات الكود (`GAP-CODE`) وعيوب بروفة العرض (`DEMO_REHEARSAL_2026-11-20.md`) مقابل الشيفرة الحالية على الفرع `feat/lms-build`:

1. **بنود كانت مُصلحة مسبقاً ومُتحقق منها في `HEAD`:**
   - **High / Critical:** ربط التسجيل المجاني (`01f9229`)، حماية مسارات الرفع الخاصة (`b74d0cf`)، ربط استدعاءات Paymob بمعرف الطلب الموقّع (`0f46b30`)، توحيد فئة البالغين وقفل ميزانية الذكاء الاصطناعي مع احتساب تكلفة الحجز المقترح (`27d86e2`)، احتساب تاريخ بدء الفوج للوصول المتدرج (`43dae02`)، منع حذف الدورات المرتبطة بطلبات وقصّ الروابط (`2ef3d88`)، وبذرة الجلسات الحية (`0f591d4`).
   - **Medium:** إعادة فحص أهلية `view-as` عند استهلاك الجلسة (`d67a860`)، وأعمدة التصدير والفرز في جدول الدورات (`789f8e7`).
2. **الإصلاحات الجديدة المضافة والمثبتة في هذه الجولة:**
   - **تشديد `Telescope` (`a79a7b5`):** إضافة مسارات إثباتات الدفع (`my/orders/*/payment-proof*`) والمدفوعات والمصادقة وروابط ولي الأمر إلى `ignore_paths`، ومنع تسجيل الطلبات/الاستثناءات الفاشلة على المسارات المستثناة خارج البيئة المحلية في `Telescope::filter()`، وتوسيع قائمة حجب الحقول الحساسة (`child_password`, `recovery_codes`, `sender_account`, `transfer_reference`, `payout_details`, إلخ).
   - **إمكانية الوصول في جدول طلبات المساعدة (`46e5271`):** إضافة `->placeholder('-')` لعمود `overdue` في `HelpRequestResource.php` لمنع ظهور روابط `<a>` فارغة النص.
   - **قابلية التركيز بلوحة المفاتيح لمناطق التمرير (`60b6095`، `dc01698`):** إضافة `tabindex="0"` (مع `role="region"` و`aria-label` في التقارير) لحاويات `.yg-ai-scroll` في `reports/index.blade.php` و`ai/usage.blade.php` و`commerce/reconciliation.blade.php`.
3. **بند لم يُعدّل لتعارضه مع اختبار قائم معتمد:**
   - إرجاع `404` عند الضغط على `Back` في المتصفح نحو `/fake-gateway/{reference}` بعد اكتمال الدفع مُثبت صراحةً كمتطلب أمان في `FakeGatewayPageTest.php:100` (`T-P8-GAPS A1`)؛ تُرك دون تعديل للحفاظ على دلالة الاختبار المعتمد وسُجّل للمالك.
4. **نتائج الاختبارات وموجّهات الاختبار:**
   - نجحت جميع الاختبارات المتأثرة وفحوصات الجودة (`npm run build`، `pint --test`، `phpstan` `0 errors`، `Arch` 6/6، `Invariants` 11/11، `TelescopePrivacyTest` 11/11، `HelpRequestQueueTest`، `Reports` 44/44، `Support` 14/14 — إجمالي **93 اختباراً ناجحاً**).
   - حُفظت موجّهات اختبارات الانحدار المستقلة في [SEC-01.md](../test-prompts/SEC-01.md) و[SEC-02.md](../test-prompts/SEC-02.md).
