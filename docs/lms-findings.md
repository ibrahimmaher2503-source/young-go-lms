# تقرير التدقيق الاستكشافي للأداء وقابلية التوسع (Discovery-only Audit)
**مشروع:** Young Go LMS  
**تاريخ الفحص:** 17 نوفمبر 2026  
**طبيعة المهمة:** تدقيق استكشافي للأداء واكتشاف المشاكل وقابلية التوسع (بدون تعديل كود التطبيق أو الإعدادات).  
**بيئة التشغيل المحلية:** PHP 8.4.21, SQLite (database/database.sqlite), Node v24.15.0, Playwright Chromium, المنفذ 8080 (مع الإبقاء على منفذ ERP 8000 دون مساس).

---

## 1. ملخص تنفيذي ونطاق الفحص
تم إجراء فحص استكشافي شامل ومحايد لأداء مسارات النظام عبر كافة أدوار المستخدمين (طالب، مدرس، إدارة، زائر عام). تم توثيق قياسات التقرير السابق كمرجع أساسي، وتكرار القياسات المعيارية تحت نفس الظروف عبر 3 محاولات متتالية لكل مسار لاستخراج وسيط زمن الاستجابة (Median Response Latency) وإجمالي وقت استعلامات SQL وعددها والأنماط المكررة واستجابة المتصفح وموارد الذاكرة عبر Playwright / Chrome DevTools Protocol (CDP).

---

## 2. سجل المشاكل المكتشفة (Issue Register)

### [ISSUE-001] انفجار استعلامات N+1 وغياب التصفح المصفح (Pagination) في صفحات الطالب الرئيسية
- **المعرف الثابت:** `ISSUE-001`
- **الأولوية:** حرج (Critical)
- **الصفحات المتأثرة:**
  - `GET /my` (لوحة تحكم الطالب - Learner Dashboard)
  - `GET /my/courses` (قائمة دورات الطالب - Learner My Courses) *(تم تحديث المشكلة لضم هذا المسار لاشتراكه في نفس السبب الجذري)*
  - `GET /parent` (لوحة تحكم ولي الأمر - Parent Dashboard) *(تم تحديث المشكلة لضم هذا المسار لاشتراكه في استدعاء `LearnerCourseRows`، حيث نفذ 51 استعلاماً لدورة واحدة فقط لابن مسجل)*
- **الدور:** طالب (`student`)
- **حجم البيانات المختبرة:**
  - 1 دورة، 5 دورات، 50 دورة مسجل فيها الطالب.
- **القياسات والأدلة:**
  - **أرقام التقرير السابق المرجعية (بتاريخ 2026-11-17):**
    - 1 دورة: 37 استعلام SQL (وقت SQL: 13.05 ms)
    - 5 دورات: 129 استعلام SQL (وقت SQL: 52.19 ms)
    - 50 دورة: 1,205 استعلام SQL (وقت SQL: 511.71 ms)
    - معدل النمو: حوالي 23.9 استعلام إضافي لكل دورة جديدة.
  - **إعادة القياس المعيارية (3 محاولات متتالية - وسيط الاستجابة - 17 نوفمبر 2026):**
    - **مسار `/my`:**
      - بيانات قليلة (5 دورات): 134 استعلامًا، وسيط وقت SQL: 53.4 ms، وسيط استجابة السيرفر: 179.34 ms
      - بيانات كبيرة (50 دورة): 1,206 استعلامات (+1072 استعلام)، وسيط وقت SQL: 508.2 ms، وسيط استجابة السيرفر: 2,042.59 ms
      - المعروض فعلياً: 3 دورات فقط قيد التقدم وبطاقة متابعة الدرس الأخير (مع ذلك استعلم عن الـ 50 دورة كاملة).
    - **مسار `/my/courses`:**
      - بيانات قليلة (5 دورات): 114 استعلامًا، وسيط وقت SQL: 44.8 ms، وسيط استجابة السيرفر: 161.52 ms
      - بيانات كبيرة (50 دورة): 1,104 استعلامات (+990 استعلام)، وسيط وقت SQL: 462.1 ms، وسيط استجابة السيرفر: 2,111.41 ms
      - المعروض فعلياً: 50 دورة كاملة دون تقسيم صفحات (غياب Pagination).
- **خطوات إعادة المشكلة:**
  1. تسجيل الدخول بحساب طالب تجريبي (`student@younggo.test`).
  2. تسجيل الطالب في 50 دورة تحتوي على أقسام ودروس منشورة.
  3. فتح الرابط `http://127.0.0.1:8080/my` أو `http://127.0.0.1:8080/my/courses`.
  4. مراقبة سجل استعلامات SQL المنفذة وزمن تحميل الصفحة.
- **المتوقع والفعلي:**
  - **المتوقع:** جلب بيانات الدورات مجمعة باستخدام Eager Loading / `whereIn`، وتطبيق Pagination بحد أقصى (مثلاً 12 دورة في الصفحة)، بحيث يظل عدد الاستعلامات ثابتًا (O(1)) ولا يتجاوز زمن الاستجابة 100 ms.
  - **الفعلي:** تنفيذ استعلامات منفصلة لحساب تقدم كل دورة وجلب أقسامها وعناصرها لكل كورس على حدة داخل حلقة تكرارية، مما رفع الاستعلامات إلى 1,206 استعلامات وزمن الاستجابة إلى أكثر من ثانيتين (2,042 ms - 2,111 ms).
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Progress/Queries/LearnerCourseRows.php`: الأسطر 22–38 (دالة `for` تمر بحلقة `foreach ($enrolled as $course)` وتستدعي `$this->continue->progress()` و `$this->continue->for()`).
  - `app/Domain/Progress/Queries/ContinueLearning.php`: السطر 27 والسطر 43 (إعادة تحميل `CourseSection` و `CurriculumItem` لكل دورة على حدة).
  - `app/Domain/Progress/Actions/RecalculateCourseProgress.php`: الأسطر 35–42 (إعادة احتساب النسبة وتحديث قاعدة البيانات داخل قراءة الـ Dashboard).
  - `app/Http/Controllers/LearnerHomeController.php`: الأسطر 49–62 (غياب الـ Pagination وجلب كل الكورسات في الذاكرة عبر `$all = $this->rows($this->learner())` ثم التصفية عبر Collection).
- **مسار السجلات والأدلة:**
  - `scripts/benchmark_results.json`
  - `scripts/profile_sql.php`

---

### [ISSUE-002] استعلامات متكررة وغياب التخزين المؤقت على مستوى دورة حياة الطلب (Request Memoization) في مشغّل الدروس
- **المعرف الثابت:** `ISSUE-002`
- **الأولوية:** متوسطة (Medium)
- **الصفحة المتأثرة:** `GET /learn/{course}/{item}`
- **الدور:** طالب (`student`)
- **حجم البيانات المختبرة:** دورة تحتوي على قسم ودرس واحد منشور مع وسائط ومصادر.
- **القياسات والأدلة:**
  - **أرقام التقرير السابق المرجعية (بتاريخ 2026-11-17):**
    - إجمالي استعلامات الصفحة: 24 استعلامًا (وقت SQL: 8.42 ms، وقت المتحكم والعرض: 32.15 ms).
    - 9 استعلامات مكررة مطابقة في الطلب الواحد:
      - استعلام جدول `course_sections`: تكرر 3 مرات بنفس الشروط (`where course_id = ? and archived_at is null and published = ?`).
      - استعلام جدول `curriculum_items`: تكرر مرتين بنفس الشروط.
      - استعلام جدول `lessons`: تكرر مرتين.
      - استعلام جدول `media_assets`: تكرر مرتين.
  - **إعادة القياس عبر المتصفح (Playwright / CDP - 17 نوفمبر 2026):**
    - زمن تحميل الصفحة الكلي (NetworkIdle): 1,222 ms
    - استهلاك الذاكرة (DevTools JS Heap): 3.30 MB
    - عدد طلبات الشبكة: 4 طلبات ثابتة (المستند + CSS + JS + الخط العربي)، دون وجود طلبات API إضافية أو مكررة في الواجهة.
- **خطوات إعادة المشكلة:**
  1. تسجيل الدخول بحساب الطالب التجريبي.
  2. الانتقال المباشر إلى الدرس `/learn/1/1`.
  3. فحص سجل الاستعلامات المجمعة أثناء معالجة الطلب.
- **المتوقع والفعلي:**
  - **المتوقع:** تنفيذ استعلام هيكل المنهج والأقسام مرة واحدة وتخزينه مؤقتًا في الذاكرة طوال الطلب (Request-Scoped Memoization / Identity Map)، وتحميل عناصر الدرس عبر Eager Loading.
  - **الفعلي:** استدعاء فحص الصلاحية `EntitlementService::canAccessItem` ثم شجرة المنهج `CurriculumTree::for` ثم تقدم التعلم `ContinueLearning::progress`، وكل إجراء ينفذ استعلاماته المستقلة دون مشاركة البيانات المسترجعة مسبقاً.
- **الملفات والأسطر المسؤولة:**
  - `app/Http/Controllers/LearnController.php`: الأسطر 56–66 (استدعاءات منفصلة غير مدمجة).
  - `app/Domain/Curriculum/Queries/CurriculumTree.php`: الأسطر 20–35 (تكرار الاستعلام عن الأقسام والعناصر).
  - `app/Domain/Entitlements/EntitlementService.php`: الأسطر 40–55 (إعادة الاستعلام عن تبعية العنصر والقسم للتحقق من الوصول).
- **مسار السجلات والأدلة:**
  - `test-results/student-progress-.../trace.zip`
  - `scripts/profile_network.mjs`

---

### [ISSUE-003] مشكلة N+1 في فحص صلاحيات الأسطر (Row Actions Authorization) في قائمة دورات المدرس
- **المعرف الثابت:** `ISSUE-003`
- **الأولوية:** عالية (High)
- **الصفحة المتأثرة:** `GET /instructor/courses` (Filament Table Workspace)
- **الدور:** مدرس أكاديمي / مستقل (`instructor_academy`, `instructor_independent`)
- **حجم البيانات المختبرة:**
  - بيانات قليلة: 5 دورات مرتبطة بالمدرس
  - بيانات كبيرة: 50 دورة مرتبطة بالمدرس
- **القياسات والأدلة:**
  - عند 5 دورات: 80 استعلام SQL، زمن وسيط: 416.67 ms (المعروض: 5 دورات).
  - عند 50 دورة: 150 استعلام SQL (+70 استعلام إضافي)، زمن وسيط: 637.60 ms.
  - المعروض فعلياً بعد التصفح: 10 دورات في الصفحة (Default Filament Pagination).
  - الاستعلامات المكررة في الطلب الواحد عند 50 دورة:
    - فحص الصلاحية `CourseDirectory::for($user)->whereKey($course->getKey())->exists()`: تكرر **90 مرة** في نفس الطلب!
    - فحص صلاحية التعديل للمدرس `$course->instructors()->whereKey($user->getKey())->wherePivot('scope', 'manage')->exists()`: تكرر **50 مرة**!
- **خطوات إعادة المشكلة:**
  1. تسجيل الدخول بحساب مدرس (`instructor@younggo.test`).
  2. إنشاء 50 دورة وربطها بالمدرس كمشرف/مدير.
  3. فتح الرابط `/instructor/courses`.
- **المتوقع والفعلي:**
  - **المتوقع:** بقاء عدد الاستعلامات ثابتًا عند عرض 10 أسطر (حوالي 10-15 استعلامًا)، مع تفويض الصلاحيات دفعة واحدة (Batch Authorization) أو الاستفادة من بيانات العلاقة المحملة مسبقًا في الـ Collection.
  - **الفعلي:** تكرار فحص `can('view', $record)` و `can('update', $record)` لكل زر إجراء (View, Edit, Learners, Curriculum, etc.) ولكل سطر معروض، مما يولد 150 استعلامًا في الصفحة الواحدة.
- **الملفات والأسطر المسؤولة:**
  - `app/Policies/CoursePolicy.php`: الأسطر 25–37 (دالتا `view` و `update` تطلقان استعلامات `exists()` فرعية متكررة دون كاش محلي).
  - `app/Filament/Resources/Courses/CourseResource.php`: تعريف أزرار الإجراءات داخل الجدول التي تفحص البوليسي بشكل منفصل لكل صف.
- **مسار السجلات والأدلة:**
  - `scripts/benchmark_results.json`

---

### [ISSUE-004] استعلامات فرعية مترابطة (Correlated Subqueries) في قائمة طلاب الدورة [تحليل تجريبي للتوسع]
- **المعرف الثابت:** `ISSUE-004`
- **الأولوية:** متوسطة (Medium) - «مشتبه بها كعائق توسع في قواعد البيانات الإنتاجية»
- **الصفحة المتأثرة:** `GET /instructor/courses/{id}/learners` (أو `/admin/courses/{id}/learners`)
- **الدور:** مدرس / إدارة (`instructor_academy`, `admin`)
- **حجم البيانات المختبرة:** 5 طلاب، 50 طالباً، 200 طالب مسجلين في الدورة.
- **القياسات والأدلة المعيارية (عبر Telescope وقياسات الأداء المباشرة - 17 نوفمبر 2026):**
  - عند 5 طلاب: 9 استعلامات SQL، وسيط وقت الاستعلام الفرعي المترابط: 0.59 ms، إجمالي وقت SQL: 4.86 ms، المعروض: 5 طلاب.
  - عند 50 طالباً: 7 استعلامات SQL، وسيط وقت الاستعلام الفرعي المترابط: 0.75 ms، إجمالي وقت SQL: 4.21 ms، المعروض فعلياً: 15 طالباً في الصفحة (`paginate(15)`).
  - عند 200 طالب: 7 استعلامات SQL، وسيط وقت الاستعلام الفرعي المترابط: 0.71 ms، إجمالي وقت SQL: 2.85 ms، المعروض فعلياً: 15 طالباً في الصفحة (`paginate(15)`).
  - رابط طلب Telescope الموثق: [Telescope Entry](http://127.0.0.1:8080/telescope/requests/a34000a4-653a-4907-a641-9900c28c67df) (UUID: `a34000a4-653a-4907-a641-9900c28c67df`).
- **التحليل الفني للنتيجة:**
  - **السلوك الفعلي محلياً:** عدد الاستعلامات ظل ثابتاً (O(1))، وزمن تنفيذ الاستعلام الفرعي ظل مستقراً جداً (~0.59 ms إلى 0.75 ms) حتى مع 200 طالب، والسبب هو أن تقسيم الصفحات `paginate(15)` يقيد عدد صفوف الاستعلام الخارجي بـ 15 صفاً فقط (`LIMIT 15 OFFSET 0`)، وبالتالي لا يُنفذ الاستعلام الفرعي المترابط سوى 15 مرة فقط بغض النظر عن إجمالي عدد الطلاب المسجلين.
  - **الخطر المتبقي في بيئات الإنتاج (Architectural Bottleneck):**
    الاستعلام الفرعي داخل `addSelect`:
    ```sql
    select "users".*,
      (case when sum(case when status = 'active' and starts_at <= ? and (ends_at is null or ends_at > ?) then 1 else 0 end) > 0 then 'active' else 'ended' end) as access_status,
      (min(starts_at)) as enrolled_at
    from "users"
    where "users"."id" in (select "learner_id" from "entitlements" where "course_id" = ?)
    ```
    يقوم في كل صف من الـ 15 بفحص جدول `entitlements`. فإذا وصل حجم جدول الصلاحيات إلى مئات الآلاف وغابت الفهارس المركبة المناسبة (`course_id, learner_id, status, starts_at, ends_at`)، فإن كل استدعاء للاستعلام الفرعي المترابط سيجري فحصاً كاملاً (Index/Table Scan)، مما يرفع زمن الاستجابة تدريجياً مع تضخم حجم جدول `entitlements` الكلي.
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Entitlements/CourseLearners.php`: الأسطر 18–29.
  - `app/Filament/Resources/Courses/Pages/CourseLearnersPage.php`: السطر 55 (`CourseLearners::query($course)->orderBy('users.name')->paginate(15)`).
- **مسار السجلات والأدلة:**
  - `scripts/benchmark_issue_004.php`
  - `scripts/issue_004_results.json`

---

### [ISSUE-005] استعلامات إحصاء عديدة وغير مجمعة في هيدر صفحات الإدارة والمدرسين
- **المعرف الثابت:** `ISSUE-005`
- **الأولوية:** منخفضة (Low - Optimization)
- **الصفحات المتأثرة:**
  - `GET /admin/courses`
  - `GET /instructor/courses`
- **الدور:** إدارة ومدرسين (`admin`, `instructor`)
- **القياس والدليل:**
  - في صفحة `/admin/courses` يتم تنفيذ 10 استعلامات، من بينها 5 استعلامات `SELECT COUNT(*)` مستقلة لحساب بطاقات الهيدر الملخصة (Active, Paid, Free, Upcoming, Suspended).
  - الاستعلامات تتكرر بنفس النمط:
    - 3 استعلامات لـ `where "status" = ?`
    - استعلامان لـ `where "access_mode" = ?`
- **المتوقع والفعلي:**
  - **المتوقع:** دمج هذه الإحصاءات في استعلام تجميعي واحد باستخدام التجميع الشرطي (`SELECT COUNT(CASE WHEN ... THEN 1 END), ... FROM courses`).
  - **الفعلي:** تنفيذ 5 استعلامات منفصلة في كل زيارة للصفحة عبر دالة `CourseDirectory::counts()`.
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Catalog/Queries/CourseDirectory.php`: الأسطر 35–46 (`public static function counts`).
  - `app/Filament/Resources/Courses/Pages/ListCourses.php`: الأسطر 27–38 (`getWorkspaceSummary`).
- **مسار السجلات والأدلة:**
  - `scripts/benchmark_results.json`

---

### [ISSUE-077] انهمار استعلامات الصلاحيات المتكررة وتضاعف N+1 في طوابير مراجعة الواجبات والمقالات
- **المعرف الثابت:** `ISSUE-077`
- **الأولوية:** عالية (High)
- **الصفحات المتأثرة:**
  - `GET /instructor/submission-review` (طابور مراجعة تسليمات الواجبات للمدرس)
  - `GET /instructor/essay-review` (طابور مراجعة المقالات والأسئلة المقالية)
  - `GET /admin/submission-review` (طابور مراجعة الواجبات للإدارة)
  - `GET /admin/essay-review` (طابور مراجعة المقالات للإدارة)
- **الدور:** مدرس / إدارة (`instructor_academy`, `admin`)
- **حجم البيانات المختبرة:**
  - 5 دورات مرتبطة بالمدرس مقابل 50 دورة مرتبطة بالمدرس.
- **القياسات والأدلة المعيارية عبر Laravel Telescope (17 نوفمبر 2026):**
  - **عند 5 دورات:** 63 استعلام SQL، إجمالي وقت SQL: 38.71 ms، وسيط زمن الاستجابة: 906.43 ms
    - رابط طلب Telescope الموثق: [Telescope Entry - 5 Courses](http://127.0.0.1:8080/telescope/requests/a34000dc-74d8-4d4f-b77d-a6b743d89b13)
  - **عند 50 دورة:** **513 استعلام SQL** (+450 استعلاماً إضافياً! بمعدل 10 استعلامات لكل دورة جديدة)، إجمالي وقت SQL: 220.80 ms، وسيط زمن الاستجابة: **2,724.97 ms** (ارتفاع بنسبة +200%).
    - رابط طلب Telescope الموثق: [Telescope Entry - 50 Courses](http://127.0.0.1:8080/telescope/requests/a34000ed-9220-473e-b69e-422a527b23d1)
    - أبطأ استعلام مسجل:
      ```sql
      select exists(
        select * from "users"
        inner join "course_instructors" on "users"."id" = "course_instructors"."instructor_id"
        where "course_instructors"."course_id" = 187
          and "users"."id" = 2
          and "course_instructors"."scope" = 'manage'
      ) as "exists" (32.42 ms)
      ```
- **خطوات إعادة المشكلة:**
  1. تسجيل الدخول بحساب مدرس (`instructor@younggo.test`).
  2. إنشاء 50 دورة وربط المدرس بها بصلاحية `manage`.
  3. فتح الرابط `http://127.0.0.1:8080/instructor/submission-review`.
  4. فحص سجل استعلامات Telescope لتبين عدد الاستعلامات وأزمنتها.
- **المتوقع والفعلي:**
  - **المتوقع:** جلب معرفات الدورات المصرح للمدرس بمراجعتها (`courseIds`) مرة واحدة وتخزينها في الذاكرة (Memoization) طوال دورة حياة الطلب، أو استخراجها باستعلام تجميعي مفهرس (`CourseInstructor::where('instructor_id', $user->id)->pluck('course_id')`)، بحيث لا يتجاوز عدد الاستعلامات 10-15 استعلاماً.
  - **الفعلي:** دالة `ReviewAccess::courseIds($user)` تجلب كافة الدورات من قاعدة البيانات `Course::all()` ثم تمر بحلقة تكرارية تفحص الصلاحية لكل كورس عبر البوليسي `Gate::allows('update', $course)`. وكل فحص بوليسي ينفذ استعلامين (`CourseDirectory::for` ثم `course_instructors`). بالإضافة إلى ذلك، يتم استدعاء `ReviewAccess::courseIds()` خمس مرات في نفس دورة حياة الطلب (داخل الـ Tabs، و الـ Query Scopes، وإحصاءات الجدول)، مما يضرب عدد الاستعلامات في 5، وينتج 10 استعلامات كاملة لكل كورس (50 كورس = 500 استعلام صلاحية!).
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Assignments/ReviewAccess.php`: الأسطر 38–41 (دالة `courseIds` بدون كاش أو memoization).
  - `app/Filament/Resources/SubmissionReview/SubmissionReviewResource.php`: الأسطر 74–98.
  - `app/Filament/Resources/EssayReview/EssayReviewResource.php`: الأسطر 68–85.
  - `app/Policies/CoursePolicy.php`: الأسطر 25–37.
- **مسار السجلات والأدلة:**
  - `scripts/benchmark_submission_review_http.php`

---

### [ISSUE-078] استنزاف عمليات الكتابة والقفل التزامني في كل نبضة تقدم للدرس (Heartbeat Write Churn)
- **المعرف الثابت:** `ISSUE-078`
- **الأولوية:** عالية (High - Concurrency Bottleneck)
- **الصفحة / المسار المتأثر:** `POST /learn/{course}/{item}/progress` (نبضة حفظ تقدم المشغل - Heartbeat)
- **الدور:** طالب (`student`)
- **حجم البيانات المختبرة:** دورة تحتوي على قسم واحد ودرس واحد (بيانات قياسية).
- **القياسات والأدلة المعيارية عبر Laravel Telescope (17 نوفمبر 2026):**
  - استجابة السيرفر: 200 OK في 479 ms (وسيط زمن الاستجابة).
  - عدد استعلامات SQL: **25 استعلاماً في كل نبضة فردية**.
  - عمليات الكتابة (Writes): **عمليتا كتابة متزامنتان في كل نبضة**:
    - `insert into "lesson_progress" ("completed", "last_position_seconds", "learner_id", "lesson_id", "started_at", "total_time_seconds", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?, ?, ?)`
    - `update "course_progress" set "completed_lessons" = ?, "percent" = ?, "updated_at" = ? where "id" = ?`
  - إجمالي وقت استعلامات SQL: 12.87 ms.
  - رابط طلب Telescope الموثق: [Telescope Entry - Progress Heartbeat](http://127.0.0.1:8080/telescope/requests/a33ffe23-07c2-4f63-a2da-a92b3671cb88) (UUID: `a33ffe23-07c2-4f63-a2da-a92b3671cb88`).
- **خطوات إعادة المشكلة:**
  1. تشغيل مشغل الدروس لطالب مسجل في دورة.
  2. إرسال طلب نبضة الحفظ الدوري (الذي يرسله المشغل تلقائياً كل 10 إلى 15 ثانية أثناء المشاهدة).
  3. مراقبة سجل Telescope للأوامر المنفذة أثناء معالجة الطلب.
- **المتوقع والفعلي:**
  - **المتوقع:** تحديث سريع وخفيف لموضع المشاهدة (`last_position_seconds` و `total_time_seconds`) في جدول تقدم الدرس دون إعادة حساب النسبة المئوية لكامل الدورة بشكل تزامني في كل نبضة، وترك إعادة احتساب نسبة الدورة عند إكمال الدرس فعلياً أو عبر وظيفة خلفية غير متزامنة (Queue/Job).
  - **الفعلي:** يستدعي `RecordProgress::heartbeat` الإجراء `RecalculateCourseProgress` فوراً وبشكل تزامني في كل نبضة، مما يستعلم عن كافة أقسام الدورة وجميع عناصر المنهج وسجلات التقدم لكامل الدورة ويجري عملية `UPDATE` لجدول `course_progress`. إذا كان هناك مئات الطلاب يشاهدون دروساً متزامنة ويرسلون نبضات كل 10 ثوانٍ، فسيحدث تنافس شديد على أقفال الصفوف (Row Locks Contention) وتعطل السيرفر.
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Progress/Actions/RecordProgress.php`: الأسطر 34–45 (`$this->recalculate->forLearner($course, $learner)`).
  - `app/Domain/Progress/Actions/RecalculateCourseProgress.php`: الأسطر 20–55.
  - `app/Http/Controllers/ProgressController.php`: السطر 31 (`$action->heartbeat($learner, $course, $item, ...)`).
- **مسار السجلات والأدلة:**
  - `scripts/profile_interactions.mjs`
  - `scripts/inspect_progress_telescope.php`

---

### [ISSUE-079] عمليات كتابة ذات تأثير جانبي (Side-Effect Writes) داخل طلبات القراءة GET
- **المعرف الثابت:** `ISSUE-079`
- **الأولوية:** متوسطة (Medium)
- **الصفحات / المسارات المتأثرة:**
  - `GET /my` (لوحة الطالب)
  - `GET /learn/{course}/{item}/quiz/{attempt}` (عرض محاولة الاختبار للدارس)
  - `GET /parent` (لوحة تحكم ولي الأمر)
- **الدور:** طالب (`student`)، ولي أمر (`parent`)
- **القياسات والأدلة المعيارية عبر Laravel Telescope:**
  1. **في مسار `GET /my`:**
     - عند زيارة طالب للوحة التحكم دون وجود جهاز موثق مسبقاً في الجلسة، يُنفذ السيرفر عمليتي `INSERT` تزامنيتين:
       - `insert into "user_devices" ("device_hash", "device_name", "first_seen_at", "ip_address", "is_trusted", "last_active_at", "user_agent", "user_id", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)` (استغرقت 11.2 ms)
       - `insert into "user_sessions" ("device_id", "ip_address", "last_activity_at", "session_id", "user_agent", "user_id", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?, ?, ?)` (استغرقت 0.23 ms)
     - رابط طلب Telescope الموثق: [Telescope Entry - Dashboard Read](http://127.0.0.1:8080/telescope/requests/a33ffe20-a147-4556-8246-24f8dfae411f) (UUID: `a33ffe20-a147-4556-8246-24f8dfae411f`).
     - **السبب والتقييم:** طبقة الحماية `EnsureActiveDevice` تتحقق من بصمة الجهاز عند كل طلب وتسجل الجهاز الجديد في قاعدة البيانات فورياً. السلوك مقصود لحماية الحسابات وفق PRD §5.2، ولكنه يكسر مبدأ حيادية القراءة (Idempotent GET Requests) ويفرض أعباء كتابة وتنافس أقفال عند ذروة الدخول أو تصفح الطلاب.
  2. **في مسار `GET /learn/{course}/{item}/quiz/{attempt}`:**
     - في `QuizAttemptController::show` (الأسطر 60–62):
       ```php
       if ($attempt->isOpen() && $attempt->deadline_at !== null && now()->gt($attempt->deadline_at->copy()->addSeconds((int) config('assessment.grace_seconds')))) {
           $attempt = $this->submit->__invoke($learner, $attempt, null, true);
       }
       ```
     - إذا فتح الطالب رابط محاولة انتهت مهلتها الزمنية عبر طلب `GET` عادي، يقوم المتحكم باستدعاء إجراء `SubmitQuizAttempt` بالكامل، والذي ينفذ عمليات تصحيح تلقائي وكتابة درجات وتحديث لجدول المحاولات وتحديث تقدم الدورة أثناء طلب قراءة.
  3. **في مسار `GET /parent` (لوحة تحكم ولي الأمر):**
     - عند فتح لوحة تحكم ولي الأمر بالرابط `GET /parent`، نفذ الطلب 51 استعلام SQL و **3 عمليات كتابة INSERT متزامنة** داخل جدول `course_progress`!
     - رابط طلب Telescope الموثق: [Telescope Entry - Parent Dashboard](http://127.0.0.1:8080/telescope/requests/a3401406-3495-4da1-8efa-948e8228ee25) (UUID: `a3401406-3495-4da1-8efa-948e8228ee25`).
     - استعلامات الكتابة المنفذة:
       `insert into "course_progress" ("completed_at", "course_id", "enrolled_at", "last_item_id", "learner_id", "percentage", "updated_at", "created_at") values (?, ?, ?, ?, ?, ?, ?, ?)`
     - **السبب والتقييم:** `ParentDashboardController:40` يستدعي `LearnerCourseRows::for($selected)` الذي يستدعي `RecalculateCourseProgress` فيقوم بإنشاء سجل تقدم جديد وكتابته في قاعدة البيانات أثناء مجرد استعراض ولي الأمر لبيانات ابنه!
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Identity/SessionLimiter.php`: الأسطر 25–48.
  - `app/Http/Controllers/QuizAttemptController.php`: الأسطر 60–63.
  - `app/Http/Controllers/ParentDashboardController.php`: السطر 40.
  - `app/Domain/Progress/Queries/LearnerCourseRows.php`: السطر 22.
- **مسار السجلات والأدلة:**
  - `scripts/telescope_tool.php`
  - `scripts/test_parent_dashboard.php`

---

### [ISSUE-080] انهيار صفحة تفاصيل الدورة العامة GET /courses/{slug} بخطأ 500 لعدم وجود جدول course_reviews
- **المعرف الثابت:** `ISSUE-080`
- **الأولوية:** حرج / حاجز تشغيلي (Critical Blocker)
- **الصفحة المتأثرة:** `GET /courses/{slug}` (صفحة تفاصيل الدورة للعامة والطلاب)
- **الدور:** زائر عام / طالب (`guest`, `student`)
- **حجم البيانات المختبرة:** دورة منشورة واحدة (`demo-course`)
- **القياسات والأدلة المعيارية عبر Laravel Telescope:**
  - حالة الاستجابة: **500 Server Error**
  - زمن الاستجابة: **13,569 ms** (توقف الطلب لأكثر من 13.5 ثانية قبل رد السيرفر بالخطأ!)
  - استعلامات SQL المنفذة قبل الانهيار: 17 استعلاماً (وقت SQL: 7.7 ms).
  - الخطأ المسجل في Telescope Log Watcher و `storage/logs/laravel.log`:
    `Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: course_reviews (Connection: sqlite, SQL: select count(*) as aggregate from "course_reviews" where "course_id" = 1 and "status" = published)`
  - معرف الطلب في Telescope (UUID): `a3401454-cbba-4125-ad79-cb963d84f9af`
  - رابط الطلب المباشر في Telescope: [Telescope Entry - Public Course 500](http://127.0.0.1:8080/telescope/requests/a3401454-cbba-4125-ad79-cb963d84f9af)
  - سجل خطأ التطبيق في Telescope Log Watcher: [Telescope Entry - QueryException Log](http://127.0.0.1:8080/telescope/logs)
- **خطوات إعادة المشكلة:**
  1. تشغيل السيرفر المحلي `php artisan serve --host=127.0.0.1 --port=8080`.
  2. زيارة أي دورة عامة عبر المتصفح أو عبر HTTP: `http://127.0.0.1:8080/courses/demo-course`.
  3. ينهار السيرفر فوراً بصفحة استثناء Ignition (500 Error) وتأخر استجابة يتجاوز 13 ثانية.
- **المتوقع والفعلي:**
  - **المتوقع:** فتح صفحة تفاصيل الدورة، وعرض البيانات الوصفية وهيكل المنهج والمدرب ومراجعات الطلاب إن وُجدت، وفي حال غياب تقييمات الطلاب يتم إرجاع ملخص صفري فارغ أو إخفاء وسم التقييمات بأمان (Graceful Fallback دون انهيار).
  - **الفعلي:** انهيار الصفحة بالكامل وموت الطلب بخطأ `QueryException: no such table: course_reviews` لأن كود الـ SEO يستدعي `ReviewSummary` والذي يستعلم عن جدول `course_reviews` وهو جدول غير منشأ في الـ Migrations أو قاعدة البيانات على الإطلاق!
- **الملفات والأسطر المسؤولة:**
  - `app/Domain/Engagement/ReviewSummary.php`: السطر 21 (`CourseReview::query()->where('course_id', $course->id)->where('status', 'published')`).
  - `app/Domain/Course/Seo/CourseSeo.php`: السطر 28 (`ReviewSummary::for($course)`).
  - `app/Http/Controllers/PublicCourseController.php`: السطر 30 (`CourseSeo::for($course)`).
- **مسار السجلات والأدلة:**
  - `storage/logs/laravel.log` (السجل بتاريخ 2026-11-18 00:36:26)
  - `scripts/inspect_all_watchers.php` (سجل خطأ التطبيق في Telescope LogWatcher)

---

## 3. جدول القياسات المقارنة الشامل عبر المسارات (Scale Benchmark Table)

تم إجراء القياسات على مسارات مختارة تمثل أدوار النظام المختلفة تحت بيئة متطابقة، باختبار حجمين من البيانات:
- **حجم قليل (Small):** 5 كورسات، 5 طلاب، 10 سجلات تدقيق.
- **حجم كبير (Large):** 50 كورساً، 50 طالباً، 100 سجل تدقيق.
- تم حساب **وسيط زمن استجابة السيرفر** من 3 محاولات متتالية، وقياس استعلامات SQL وأزمنتها والبيانات المعروضة فعلياً بعد التصفح:

| المسار / الصفحة | الدور | البيانات المعروضة فعلياً (بعد الـ Pagination) | إجمالي السجلات في قاعدة البيانات | استعلامات SQL (قليل → كبير) | إجمالي وقت SQL (قليل → كبير) | وسيط استجابة السيرفر (3 محاولات) | المشكلة المؤكدة / التحليل | الملف والسطر المسؤول |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **لوحة الطالب**<br>`/my` | طالب | 3 كورسات جارية + بطاقة متابعة | 5 → 50 كورس | 134 → **1,206** | 53.4 ms → **508.2 ms** | 179.3 ms → **2,042.6 ms** | **N+1 حرج**: إعادة حساب التقدم واستعلام الأقسام لكل كورس من الـ 50 | `LearnerCourseRows.php:22-38`<br>`ContinueLearning.php:27` |
| **قائمة كورسات الطالب**<br>`/my/courses` | طالب | **50 كورس كاملة** (غياب الـ Pagination) | 5 → 50 كورس | 114 → **1,104** | 44.8 ms → **462.1 ms** | 161.5 ms → **2,111.4 ms** | **N+1 وغياب Pagination**: جلب جميع الكورسات وحساب تقدمها في الذاكرة | `LearnerHomeController.php:49-62`<br>`LearnerCourseRows.php:22` |
| **كتالوج الكورسات العام**<br>`/courses` | زائر / عام | 5 → 12 كورس (مصفحة: 12 بالصفحة) | 5 → 50 كورس | 8 → 8 (ثابت) | 2.6 ms → 7.1 ms | 40.8 ms → 40.8 ms | **مستقر**: تصفح سليم مع Eager Loading للمدربين والفئات | `PublicCatalogController.php:44`<br>`CatalogQuery.php:27` |
| **بحث الكتالوج**<br>`/courses?q=دورة` | زائر / عام | 5 → 12 كورس (مصفحة: 12 بالصفحة) | 5 → 50 كورس | 8 → 8 (ثابت) | 2.4 ms → 8.4 ms | 21.0 ms → 43.1 ms | **مستقر**: استعلام نصي طبيعي مع فلاتر مجمعة وتصفح | `CatalogQuery.php:28-32` |
| **قائمة كورسات المدرس**<br>`/instructor/courses` | مدرس | 5 → 10 كورسات (مصفحة) | 5 → 50 كورس | 80 → **150** | 38.9 ms → 159.1 ms | 416.7 ms → **637.6 ms** | **N+1 في البوليسي**: تكرار فحص الصلاحيات 90x و 50x لكل صف وإجراء | `CoursePolicy.php:25-37`<br>`CourseDirectory.php:35` |
| **قائمة طلاب الدورة**<br>`/instructor/courses/1/learners` | مدرس | 5 → 15 طالباً (مصفحة: 15 بالصفحة) | 5 → 50 طالب | 7 → 7 (ثابت) | 2.1 ms → 4.6 ms | 91.6 ms → 90.3 ms | **مشتبه بها**: استعلامات فرعية مترابطة لحساب الحالة وتاريخ التسجيل | `CourseLearners.php:18-29` |
| **إدارة الكورسات**<br>`/admin/courses` | إدارة | 5 → 10 كورسات (مصفحة) | 5 → 50 كورس | 10 → 10 (ثابت) | 2.8 ms → 6.1 ms | 157.9 ms → 197.4 ms | استعلامات إحصاء هيدر غير مجمعة (5 استعلامات count منفصلة) | `CourseDirectory.php:35-46` |
| **سجل التدقيق والعمليات**<br>`/admin/audit/audit-logs` | إدارة | 10 → 10 سجلات (مصفحة) | 10 → 100 سجل | 4 → 3 (ثابت) | 0.9 ms → 2.0 ms | 94.8 ms → 103.9 ms | **مستقر**: تصفح سليم مع Eager Loading للفاعل (`with('causer')`) | `AuditLogResource.php:68-71` |
| **مراجعة تسليمات الواجبات**<br>`/instructor/submission-review` | مدرس | 10 تسليمات مصفحة (أو فارغة) | 5 → 50 كورس للمدرس | 63 → **513** | 38.7 ms → **220.8 ms** | 906.4 ms → **2,725.0 ms** | **N+1 وتكرار البوليسي**: استدعاء `ReviewAccess::courseIds` لـ 5 مرات وفحص كل كورس | `ReviewAccess.php:38-41`<br>`SubmissionReviewResource.php:74` |
| **مراجعة المقالات**<br>`/instructor/essay-review` | مدرس | 10 مقالات مصفحة | 1 كورس | 22 استعلاماً | 11.4 ms | 344.2 ms | تكرار فحص الصلاحيات على مستوى الدورات المرتبطة بالمدرس | `EssayReviewResource.php:68-85` |
| **مكتبة وسائط المدرس**<br>`/instructor/library` | مدرس | 12 عنصراً مصفحة | 10 عناصر | 4 استعلامات | 3.5 ms | 238.3 ms | **مستقر**: استعلامات تصفح سريعة ومحدودة | `LibraryResource.php` |
| **نبضة تقدم الدرس (Heartbeat)**<br>`POST /learn/1/1/progress` | طالب | استجابة JSON | 1 درس جاري | **25 استعلاماً (منها عمليتا كتابة)** | 12.9 ms | 479.0 ms | **استنزاف كتابة وقفل**: إعادة احتساب نسبة الدورة بالكامل تزامناً مع كل نبضة | `RecordProgress.php:34`<br>`RecalculateCourseProgress.php` |
| **إكمال الدرس**<br>`POST /learn/1/1/complete` | طالب | استجابة JSON | 1 درس مكتمل | **26 استعلاماً (منها عمليتا كتابة)** | 13.5 ms | 128.0 ms | تحديث سجل التقدم وإعادة احتساب نسبة الدورة وتحديث الإنجاز | `RecordProgress.php:48`<br>`RecalculateCourseProgress.php` |
| **صفحة بدء الاختبار**<br>`GET /learn/1/100` | طالب | نظرة عامة وزر البدء | 1 اختبار منشور | 35 استعلاماً | 18.9 ms | 833.0 ms | استعلامات هيكل المنهج والتقدم وفحص الصلاحيات و`QuizOverview` | `LearnController.php:108`<br>`QuizOverview.php` |
| **بدء محاولة الاختبار**<br>`POST /learn/1/100/quiz/start` | طالب | إعادة توجيه 302 للمحاولة | 1 محاولة جديدة | 21 استعلاماً (منها عمليتا كتابة) | 14.5 ms | 2,266.0 ms | إنشاء المحاولة `quiz_attempts` وتسجيل التدقيق وتحديد المهلة | `StartQuizAttempt.php:35-50` |
| **نموذج الاختبار والمؤقت**<br>`GET /learn/1/100/quiz/1` | طالب | الأسئلة والمؤقت | محاولة جارية (1 سؤال) | 14 استعلاماً | 35.7 ms | 514.0 ms | استعلام المحاولة والأسئلة والخيارات والوقت المتبقي | `QuizAttemptController.php:54-72` |
| **الحفظ التلقائي للإجابات**<br>`POST /learn/1/100/quiz/1/save` | طالب | استجابة JSON | حفظ إجابة سؤال واحد | 18 استعلاماً (منها عملية كتابة) | 13.3 ms | 523.0 ms | حفظ الإجابة في `quiz_answers` والتحقق من صلاحية المحاولة | `SaveQuizAnswers.php:25-45` |
| **تسليم الاختبار وتصحيحه**<br>`POST /learn/1/100/quiz/1/submit` | طالب | إعادة توجيه 302 للنتيجة | تسليم محاولة | **35 استعلاماً (منها 5 عمليات كتابة)** | 34.4 ms | 1,119.0 ms | **تصحيح تزامني شامل**: تقييم الإجابات، قفل المحاولة، كتابة التدقيق، وإعادة احتساب نسبة تقدم الدورة | `SubmitQuizAttempt.php:30-65`<br>`RecalculateCourseProgress.php` |
| **عرض نتيجة ودرجة الاختبار**<br>`GET /learn/1/100/quiz/1` | طالب | بطاقة النتيجة والدرجة | محاولة مكتملة | 11 استعلاماً | 6.5 ms | 365.0 ms | قراءة المحاولة المصححة والدرجة والخيارات الصحيحة (`QuizAttemptView`) | `QuizAttemptController.php:68-71` |
| **لوحة تحكم ولي الأمر**<br>`/parent` | ولي أمر | صفحة تفاصيل وإحصاءات الأبناء | 1 ابن، 1 دورة | **51 استعلاماً (منها 3 كتابات)** | 57.6 ms | 692.0 ms | **N+1 وكتابة على GET**: تكرار `LearnerCourseRows` وحساب التقدم وكتابة في DB | `ParentDashboardController.php:40`<br>`LearnerCourseRows.php:22` |
| **تفاصيل الدورة العامة**<br>`/courses/demo-course` | زائر / طالب | انهيار الصفحة بخطأ **500 Server Error** | 1 دورة منشورة | 17 استعلاماً | 7.7 ms | **13,569.0 ms** | **حاجز تشغيلي (ISSUE-080)**: استعلام جدول غير موجود `course_reviews` | `ReviewSummary.php:21`<br>`PublicCourseController.php:30` |
| **بدء إنشاء دورة للمدرس**<br>`/instructor/courses/create` | مدرس | منع الوصول **403 Forbidden** | 1 مدرس أكاديمي | 3 استعلامات | 0.9 ms | 505.0 ms | **تأكيد عملي لـ ISSUE-018**: سياسة الصلاحيات تمنع المدرس الأكاديمي وتشترط الأدمن | `CoursePolicy.php:20-22` |
| **مدير منهج الدورة للمدرس**<br>`/instructor/courses/1/curriculum` | مدرس | محرر الأقسام وعناصر المنهج | 1 دورة (قسمان و3 عناصر) | **57 استعلاماً** | 27.0 ms | 934.0 ms | استعلامات متكررة وفحوصات صلاحيات للأقسام وعناصر المنهج عند تحميل المحرر | `ManageCurriculum.php`<br>`CurriculumTree.php` |

---

## 4. قياسات أداء المتصفح والشبكة (Playwright & Chrome DevTools Protocol)

تم قياس استجابة المتصفح عبر Playwright Chromium في بيئة تشغيل فعلية:

| الصفحة | طلبات الشبكة الكلية | طلبات API / XHR | الطلبات المكررة | أبطأ طلب شبكة | زمن تحميل الصفحة (NetworkIdle) | استهلاك ذاكرة JS (Heap) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **الكتالوج العام** (`/courses`) | 4 طلبات | 0 | لا يوجد | الخط العربي (NotoSansArabic) | 1,130 ms | 0.97 MB |
| **بحث الكتالوج** (`/courses?q=رسم`) | 4 طلبات | 0 | لا يوجد | الخط العربي (NotoSansArabic) | 719 ms | 1.37 MB |
| **لوحة الطالب** (`/my`) | 4 طلبات | 0 | لا يوجد | ملف التنسيق (app CSS) | 820 ms | 2.50 MB |
| **قائمة دورات الطالب** (`/my/courses`) | 4 طلبات | 0 | لا يوجد | الخط العربي (NotoSansArabic) | 768 ms | 2.89 MB |
| **مشغل الدروس** (`/learn/1/1`) | 4 طلبات | 0 | لا يوجد | الخط العربي (NotoSansArabic) | 1,222 ms | 3.30 MB |

*ملاحظة حول أداء الواجهة الأمامية:* تم استخدام Blade Views مع تجميع الأصول عبر Vite (ملف CSS واحد وملف JS رئيسي واحد وخط محلي). لا توجد طلبات شبكة خارجية أو تسريبات لطلبات XHR في الواجهة الأمامية. عنق الزجاجة الرئيسي يتركز في معالجة السيرفر واستعلامات قاعدة البيانات لقوائم الطالب والمدرس.

---

## 5. حالة أدوات القياس وتغطية مجموعات Laravel Telescope بالكامل

### 5.1 ملخص حالة وبيئة عمل Laravel Telescope
- **حالة Laravel Telescope:** **تم التثبيت والتفعيل بنجاح (Installed & Active)**.
- **الإصدار المثبت:** Laravel Telescope `v5.29.0` (Development Dependency).
- **البيئة المحلية:** `APP_ENV=local` (الفلتر يسمح بكافة السجلات والطلبات دون حجب)، `CACHE_STORE=file`، `QUEUE_CONNECTION=sync`، `MAIL_MAILER=log`، `DB_CONNECTION=sqlite`.
- **رابط لوحة المراقبة المحلية المباشرة:** [http://127.0.0.1:8080/telescope](http://127.0.0.1:8080/telescope)

---

### 5.2 جدول تغطية مجموعات وتبويبات Telescope (Watcher Coverage Matrix)
تم تقييم وفحص كافة مجموعات المراقبة الـ 18 المتاحة في حزمة Telescope وفق حالة نشاطها والأدلة المستخرجة منها:

| اسم التبويب / الـ Watcher | الحالة في التدقيق | عدد السجلات المرصودة | التحليل والسبب الفني |
| :--- | :--- | :--- | :--- |
| **Requests** | **اتقاس** (Measured) | 100+ طلب | سجل كافة طلبات الطالب والمدرس والإدارة ومطابقتها بالمسار والزمن والـ UUID. |
| **Queries** | **اتقاس** (Measured) | 2,408+ استعلام | قيد كافة استعلامات SQL وأزمنتها والـ Bindings وحدد الاستعلامات البطيئة (عتبة `slow=100ms`). |
| **Gates** | **اتقاس** (Measured) | **6,872 فحص صلاحية** | رصد تكراراً هائلاً لفحوص الصلاحيات غير المخزنة مؤقتاً (خاصة `panel.admin.access` بـ 2,067 مرة و `panel.instructor.access` بـ 1,235 مرة). |
| **Views** | **اتقاس** (Measured) | **3,938 تصيير** | وثق تصيير قوالب Blade المتداخلة ومكونات Filament ومكتبة UI، إضافة إلى 637 تصييراً لمكونات صفحة الخطأ Ignition عند انهيار الدورة العامة. |
| **Models** | **اتقاس** (Measured) | 447 أحداث نماذج | رصد جلب وتحديث كائنات Eloquent (`User`, `Role`, `Course`, `CurriculumItem`, `QuizAttempt`). |
| **Cache** | **اتقاس** (Measured) | 196 عملية كاش | رصد عمليات الـ Cache (117 hits, 69 misses) واقتصرت على أذونات Spatie وإشعارات Filament. |
| **Events** | **اتقاس** (Measured) | 60 حدثاً | رصد أحداث النظام (`ServingFilament` و `FeatureRetrieved` و `CourseCompletionInputChanged`). |
| **Commands** | **اتقاس** (Measured) | 67 أمراً | وثق أوامر Artisan المنفذة محلياً (seed, migrate, cache). |
| **Redis** | **غير متاح** (Unavailable) | 0 | الـ Watcher مفعل، ولكن خادم Redis غير مثبت أو مستخدم محلياً (`CACHE_STORE=file`). |
| **Jobs** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، لكن معالجة النظام تعتمد التزامن الفوري `QUEUE_CONNECTION=sync` دون طوابير. |
| **Batches** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، ولا توجد وظائف مجمعة (Job Batches) منفذة في المسارات المقاسة. |
| **Schedule** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، ومجدول المهام لم ينفذ دورياً أثناء جلسة الفحص المباشرة. |
| **HTTP Client** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، ولم تنفذ التطبيقات استدعاءات خارجية (External HTTP) في البيئة المعزولة. |
| **Mail** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، ورسائل البريد موجهة محلياً للوج (`MAIL_MAILER=log`) لحماية بيانات الاختبار. |
| **Notifications**| **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، والإشعارات داخلية مخزنة في قاعدة البيانات دون إطلاق بث خارجي. |
| **Exceptions** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، تم تسجيل الخطأ الاستثنائي عبر LogWatcher وعبر تصيير Ignition Views. |
| **Logs** | **اتقاس** (Measured) | **1 سجل خطأ** | الـ Watcher مفعل بمستوى تصفية `'level' => 'error'`. رصد وسجل الخطأ الاستثنائي الفادح `QueryException` الخاص بجدول `course_reviews` المفقود. |
| **Dumps** | **نشط ولم يظهر نشاط** | 0 | الـ Watcher مفعل، ولا توجد استدعاءات `dump()` غير محذوفة في مسارات الإنتاج. |

---

### 5.3 استغلال مجموعات Telescope والتحليل الاستكشافي المعمق
1. **استكشاف GateWatcher (انفجار فحوص الصلاحيات):**
   - تم تسجيل **6,872 فحص صلاحية Gate** خلال جلسة الفحص!
   - الفحوص الأكثر تكراراً:
     - `panel.admin.access (denied)`: **2,067 مرة**! (يتم فحصها عشرات المرات في كل طلب للوحة المدرس للتحقق من أزرار أو عناصر مشتركة).
     - `panel.instructor.access (allowed)`: **1,235 مرة**.
     - `course.view (allowed)`: **1,173 مرة**.
     - `course.update (allowed)`: **896 مرة**.
     - `update (allowed)`: **884 مرة**.
     - `assessment.manage (allowed)`: 154 مرة.
     - `viewAny (allowed)`: 110 مرة.
   - هذا يثبت رقمياً السبب الجذري للمشكلتين `ISSUE-003` و `ISSUE-077`، حيث لا يتم حفظ نتيجة فحص الصلاحية في الذاكرة (Memoization)، مما يطلق استعلامات SQL متطابقة آلاف المرات.
2. **استكشاف ViewWatcher (تكلفة تصيير القوالب المتداخلة ومكونات صفحة الخطأ):**
   - تم تسجيل **3,938 عملية تصيير لملفات Blade**:
     - مكونات عناصر الشريط الجانبي `filament-panels::components.sidebar.item`: 288 مرة.
     - أيقونات Filament و UI (`components.ui.icon` و `filament::components.icon`): 390 مرة.
     - أزرار وروابط الواجهة (`components.ui.button` و `components.ui.link`): 285 مرة.
     - **مكونات صفحة الخطأ Ignition (637 تصييراً)**: عند انهيار صفحة الدورة العامة (`GET /courses/demo-course`) بخطأ 500، استهلك تصيير صفحة الاستثناء 637 قالباً فرعياً (`syntax-highlight`: 182، `file-with-line`: 159، `formatted-source`: 156، `vendor-frame`: 140).
3. **استكشاف CacheWatcher (غياب التخزين المؤقت لبيانات التطبيق):**
   - من بين 196 عملية كاش، كانت كافة المفاتيح إما خاصة بأذونات Spatie (`spatie.permission.cache`) أو إشعارات Filament المؤقتة.
   - **نسبة التغطية بالـ Cache لبيانات التطبيق والمنهج والتقدم هي 0%**؛ فكل استعلام عن أقسام الدورة ونسبة التقدم وعدادات الهيدر ينفذ مباشرة من قاعدة البيانات.
4. **استكشاف استهلاك المعالجة التزامنية (Sync Overhead):**
   - غياب أي وظيفة في `Jobs` أو `Batches` يوضح أن كافة العمليات الثقيلة (حساب التقدم، كتابة التدقيق، وتحديث الإنجاز) تنفذ **تزامناً (Synchronously)** داخل خيط معالجة طلب الـ HTTP، مما يفسر ارتفاع زمن استجابة السيرفر وتجاوزه لعدة ثوانٍ في بعض العمليات.
5. **استكشاف LogWatcher وتوثيق الأخطاء الاستثنائية الفادحة:**
   - سجل الـ LogWatcher خطأ استثنائياً فادحاً بمستوى Error:
     `[error] Application exception` مع السياق: `{"exception_type":"Illuminate\\Database\\QueryException","source":"Connection.php","line":864}`
     نتيجة محاولة الاستعلام عن جدول غير موجود `course_reviews` في قاعدة البيانات.

---

### 5.4 سجل الطلبات المقاسة مع روابط Telescope المباشرة والـ UUID

| رقم الرحلة | العملية والمسار | الدور | الحالة والمدة | استعلامات SQL | كتابات (Writes) | رابط الدخول المباشر في Telescope |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **J1** | `POST /login` (تسجيل دخول الطالب) | طالب | 204 No Content (660 ms) | 2 استعلام | 1 كتابة | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a340077f-17ce-4ce2-9dfd-ab82ee2f91b6) |
| **J1** | `GET /my` (لوحة الطالب) | طالب | 200 OK (258 ms) | **42 استعلاماً** | **2 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a33ffe20-a147-4556-8246-24f8dfae411f) |
| **J1** | `GET /my/courses` (دورات الطالب) | طالب | 200 OK (484 ms) | **37 استعلاماً** | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a340077a-5a37-4906-bbdf-caf4885f49d6) |
| **J2** | `GET /learn/1/1` (مشغل الدروس) | طالب | 200 OK (1,091 ms) | **36 استعلاماً** | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a340077c-19e0-4740-8cb6-73fd55354258) |
| **J2** | `POST /learn/1/1/progress` (نبضة الحفظ) | طالب | 200 OK (479 ms) | **25 استعلاماً** | **2 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a33ffe23-07c2-4f63-a2da-a92b3671cb88) |
| **J2** | `POST /learn/1/1/complete` (إكمال درس) | طالب | 200 OK (128 ms) | **26 استعلاماً** | **2 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a33ffe23-594f-45d4-9b3d-f51b7c80e280) |
| **J3** | `GET /learn/1/100` (نظرة عامة على الاختبار) | طالب | 200 OK (833 ms) | **35 استعلاماً** | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34007cd-506e-472b-b8a2-3ce5c4aed472) |
| **J3** | `POST /learn/1/100/quiz/start` (بدء المحاولة) | طالب | 302 Redirect (2,266 ms) | **21 استعلاماً** | **2 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34007d2-4d11-4f9d-a1e1-227b234168aa) |
| **J3** | `GET /learn/1/100/quiz/1` (عرض نموذج المحاولة) | طالب | 200 OK (514 ms) | 14 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34007d3-5734-4418-bb47-ad43fb63e55f) |
| **J3** | `POST /learn/1/100/quiz/1/save` (حفظ تلقائي) | طالب | 200 OK (523 ms) | 18 استعلاماً | 1 كتابة | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34007d7-9e69-4737-84d0-2c3ab45e2046) |
| **J3** | `POST /learn/1/100/quiz/1/submit` (تسليم الاختبار) | طالب | 302 Redirect (1,119 ms) | **35 استعلاماً** | **5 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34008b5-09a6-4caa-9804-50d09d2c0bc7) |
| **J3** | `GET /learn/1/100/quiz/1` (عرض النتيجة والدرجة) | طالب | 200 OK (365 ms) | 11 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34008b5-cf3d-4c78-a69f-05ba106ceef8) |
| **J4** | `GET /instructor/courses` (جدول دورات المدرس) | مدرس | 200 OK (271 ms) | 27 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000a2-abd7-405d-bb05-037068b1be04) |
| **J4** | `GET /instructor/courses/1/learners` (طلاب الدورة) | مدرس | 200 OK (174 ms) | 10 استعلامات | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000a4-653a-4907-a641-9900c28c67df) |
| **J5** | `GET /instructor/submission-review` (5 دورات) | مدرس | 200 OK (906 ms) | 63 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000dc-74d8-4d4f-b77d-a6b743d89b13) |
| **J5** | `GET /instructor/submission-review` (50 دورة) | مدرس | 200 OK (**2,725 ms**) | **513 استعلاماً** | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000ed-9220-473e-b69e-422a527b23d1) |
| **J5** | `GET /instructor/essay-review` (مراجعة المقالات) | مدرس | 200 OK (293 ms) | 22 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000a3-aa53-44a4-9ae7-1ca8404c8f64) |
| **J5** | `GET /instructor/library` (مكتبة الوسائط) | مدرس | 200 OK (197 ms) | 4 استعلامات | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34000a4-0e5c-48ac-b558-48ad4ad2f8c1) |
| **J6** | `GET /admin/courses` (تحدي 2FA للإدارة) | إدارة | 200 OK (65 ms) | استعلامان | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34002f4-945f-43e8-b456-12bd25d96a94) |
| **J6** | `GET /admin/audit/audit-logs` (سجلات التدقيق) | إدارة | 200 OK (69 ms) | استعلامان | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34002f5-08ba-4f10-b275-4ed06c466178) |
| **J7** | `GET /parent` (لوحة تحكم ولي الأمر) | ولي أمر | 200 OK (692 ms) | **51 استعلاماً** | **3 كتابة** | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a3401406-3495-4da1-8efa-948e8228ee25) |
| **J8** | `GET /courses/demo-course` (تفاصيل الدورة العامة) | زائر / طالب | **500 Server Error** (**13,569 ms**) | 17 استعلاماً | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a3401454-cbba-4125-ad79-cb963d84f9af) |
| **J9** | `GET /instructor/courses/create` (إنشاء دورة للمدرس) | مدرس | **403 Forbidden** (505 ms) | 3 استعلامات | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34015ce-98b6-4a1c-a923-3997089296f4) |
| **J10** | `GET /instructor/courses/1/curriculum` (مدير المنهج للمدرس) | مدرس | 200 OK (934 ms) | **57 استعلاماً** | 0 | [طلب Telescope](http://127.0.0.1:8080/telescope/requests/a34015fa-7d31-4637-8f35-04f33b599c71) |

> [!NOTE]
> **ملاحظة منهجية حول قياسات البيئة المحلية وأزمنة Telescope:**
> القياسات الواردة أُجريت على بيئة تطوير محلية (PHP Built-in Server + SQLite) مع تفعيل مراقبة Laravel Telescope بالكامل. يضيف Telescope عبئاً حسابياً طفيفاً (Overhead) لتسجيل الأحداث والاستعلامات في قاعدة البيانات المحلية. كما أن سرعة محرك SQLite في الذاكرة/الملف المحلي تجعل أزمنة تنفيذ SQL سريعة جداً (بالمللي ثانية)، بينما يتركز معظم وقت الاستجابة في معالجة إطار العمل (Laravel lifecycle, Livewire, Blade rendering). لا يجوز تعميم الأزمنة المطلقة للبيئة المحلية على بيئة الإنتاج الموزعة ذات قواعد البيانات المنفصلة (PostgreSQL/MySQL) وأحمال الشبكة المتعددة.

---

## 6. توصيات التحسين المقترحة (للمرحلة القادمة)

1. **معالجة N+1 في لوحة ودورات الطالب (`/my` و `/my/courses`):**
   - إضافة تقسيم صفحات (Pagination) لصفحة `/my/courses` (مثلاً 12 دورة بالصفحة).
   - جلب تقدم الدورات دفعة واحدة عبر استعلام `CourseProgress::whereIn('course_id', $ids)->where('learner_id', $learnerId)`.
   - إيقاف إعادة حساب التقدم (`RecalculateCourseProgress`) عند مجرد قراءة صفحة العرض، وحصره فقط عند وقوع حدث إكمال درس أو اختبار.
2. **معالجة تكرار فحص الصلاحيات في جدول دورات المدرس (`/instructor/courses`):**
   - تطبيق Memoization لنتائج فحص الصلاحية على مستوى كائن الدورة والمستخدم أثناء دورة حياة الطلب في `CoursePolicy`.
   - استخدام Eager Loading لعلاقة المدرسين مع الدورات `with('instructors')` لتفادي استعلامات `exists()` المتكررة لكل سطر وإجراء.
3. **تجميع استعلامات العدادات والإحصاءات في الهيدر:**
   - تحويل الاستعلامات الخمسة المنفصلة في `CourseDirectory::counts` إلى استعلام واحد يجمع الحالات عبر `selectRaw("COUNT(case when ...) as active, ...")`.
4. **معالجة انفجار استعلامات طوابير المراجعة (`/instructor/submission-review` و `/instructor/essay-review`):**
   - تخزين نتائج `ReviewAccess::courseIds($user)` في خاصية محلية أو كاش محلي للطلب (Request Memoization) بدلاً من استدعائها 5 مرات.
   - استبدال حلقة `Course::all()` + `Gate::allows('update', $course)` باستعلام مباشر مفهرس عبر العلاقة: `CourseInstructor::where('instructor_id', $user->id)->pluck('course_id')`.
5. **فصل إعادة احتساب تقدم الدورة عن نبضات المشغل (Heartbeat Decoupling):**
   - قصر معالجة `POST /learn/{course}/{item}/progress` على تحديث موضعي لجدول `lesson_progress`.
   - نقل `RecalculateCourseProgress` ليعمل في وظيفة خلفية (Queue Job) أو عند حدث إكمال الدرس الصريح فقط (`complete`) لتفادي قفل قاعدة البيانات عند بث نبضات متزامنة من مئات الطلاب.
6. **إلغاء عمليات الكتابة التزامنية في طلبات القراءة (Eliminating GET Side-Effect Writes):**
   - تحويل تسجيل الأجهزة الجديدة والجلسات في `EnsureActiveDevice` إلى عملية غير متزامنة أو تنفيذها فقط بعد التحقق من اختلاف البصمة الفعلي.
   - منع المتحكمات (`QuizAttemptController`) من تنفيذ تسليمات واحتساب درجات كاملة في مسارات `GET`، وتوجيه الطالب لزر أو طلب `POST` مخصص عند انتهاء المهلة.

---

## 7. Review-queue continuation ? revision-bound evidence (2026-11-17)

This section qualifies earlier claims where evidence conflicts. Discovery and documentation only: this session changed no application code, test source, task status, settings, existing database, reference image or existing screenshot. It ran existing tests and an evidence-only diagnostic against SQLite `:memory:`. No task is approved by this report.

The performance report above appeared during this session. Its original bytes are preserved in [findings-before-continuation.md](../agents/logs/review-continuation-2026-11-17/findings-before-continuation.md), SHA-256 `5f79a055076c6b301850ed73c5a0c4b33359cbf46c69da037b700c8f151b3c1a`. ISSUE-001?005 retain their identities. Repeated findings below refer to existing IDs rather than opening duplicates.

### Reviewed revisions and concurrent work

| Label | Revision / boundary | What the evidence establishes |
|---|---|---|
| R0 | `aa903cd3aad82043f17b81b3141368a40f4532fb` plus the dirty tree described in the original reports | Historical P0?P6 static reviews, failed reproducers and gate logs. Concurrent P7 work means these logs are not clean-commit certification. |
| R1 | `9ddc45dd5662bd8e0f2218197d02eebc2875d16c` commit tree | The P7 report deliberately cites this immutable version, excluding later working changes. |
| R2 | `17f8f10921bfae081f0acd90bc511c4acaf99f5a` plus working files at 20:50:55 UTC | Current starting [snapshot](../agents/logs/review-continuation-2026-11-17/snapshot.json), [status](../agents/logs/review-continuation-2026-11-17/status.txt), [application/test diff](../agents/logs/review-continuation-2026-11-17/diff.patch), [853-file SHA-256 manifest](../agents/logs/review-continuation-2026-11-17/source-manifest.json). Manifest digest `d804fe6b6a0828fd8f34287ed1560ab6f585fb8f81f606c243a3183cb85cf2bc`. |
| R2b | Same HEAD, 20:57:09 UTC | [Second snapshot](../agents/logs/review-continuation-2026-11-17/snapshot-final.json), [manifest](../agents/logs/review-continuation-2026-11-17/source-manifest-final.json), digest `09df6df5336960ecb823b08f6783da4b40ce00f0b2fe92a8fc846bc342d61bd7`. Help routing/notices/controller, panel registration, config, routes and assistant UI were changing concurrently. This is a bounded observation, not a stable whole-tree release. |

Initial commands were `git status --short`, `git log -5 --oneline`, followed by the current diff. The five commits were `17f8f10` (P7-02/03), `9ddc45d` (P7-01), `aa903cd` (P6-06), `8266c5c` (P6-05), `b3b1be9` (P6-04). The index had no staged changes.

Already-present uncommitted fixes: course slug bounding; published-media/preview validation; 2FA audit transactions; session reactivation limit; heartbeat tolerance; quiz ID locking/scoped reads; stable notification IDs; updated family/2FA browser assertions and focused regressions. These are previous/concurrent implementation work, not fixes made by this review.

Already-present untracked work: edge-case tests for curriculum/media/entitlements/progress/cohorts/live/library/notifications, quiz isolation tests, profiling scripts, UI evidence harnesses and screenshot matrices. P7-04 help models, Actions, migration, controllers, Filament resource, views and route/scheduler wiring were being added. The board still lists P7-04 `todo`; code presence is not verified completion. P7-01/02/03 are committed and `review`. P1-07 is currently `blocked`, despite historical prose calling it review. Status authority remains [PROGRESS.md](../agents/PROGRESS.md); this session did not edit it.

Source order used: latest owner instruction, Decision Register, PRD including ?29, AI/architecture specifications, engineering/design/UX conventions, task requirements, review protocol/gates. Task acceptance and `ai:` lines were read before implementation assessment. Conflicts are documented here because the owner explicitly prohibited task/status-document changes.

### Historical log reconciliation and fresh verification

Historical evidence directory: [review-queue-2026-11-17](../agents/logs/review-queue-2026-11-17/). Fresh evidence directory, abbreviated **C** below: [review-continuation-2026-11-17](../agents/logs/review-continuation-2026-11-17/).

| Check | Actual output read / revision boundary | Conclusion |
|---|---|---|
| Historical G10 | Original `g10-results.csv` has **43 rows, 32 exit-0 and 11 exit-1**. Separate `g10-T-P7-01.log` lists A1?A4 and ED-009 missing. | The summary's ?43 / 31 PASS / 12 FAIL? conflates batches. Including P7-01 gives **44 checked, 32 PASS, 12 FAIL**. |
| Historical broad Pest | `pest.log`: 128 MiB fatal memory exhaustion, premature PHP exit. `pest-1g.log`: **1 failed, 6 TODOs, 826 passed, 4277 assertions, 447.63 s**. Failure: CourseLifecycleTest.php:109, missing `media/clip.mp4`. | Neither ?RUNNING? nor full PASS is correct. Failure cause is not established by the log; do not call it a proven product regression or silently discard it. |
| Historical G1/G2 | `pint.log` failed concurrent AskLessonAssistant formatting; `pint-final.log` says passed. PHPStan first exhausted memory, second hit shared-cache access denial, isolated-cache log says no errors. | Keep run ordering and scope. Later PASS does not certify R2 or unfinished P7-04. |
| Historical G4/G5 | `arch.log`: 6 passed / 34 assertions. `invariants.log`: 8 passed, 1 TODO / 35 assertions. | G5 is still not satisfied: ?none skipped? is required. I01 policy weakening is separately ISSUE-042. |
| Historical G6/G11 | translation, RTL, audit, build and isolated migrate/seed logs report success. `doctor.log` reports scheduler and migrations FAIL, local sync queue, delivery untested. | Do not apply old local migration/doctor state to the current DB without rerunning; no shared DB migration was performed here. |
| Historical browser | Browser report/logs: 10 distinct selected tests, 8 passed / 2 failed; challenge rerun failed too. | Failed assertions expected Profile after navigation to learner dashboard. Earlier test stopped before later flow assertions. |
| Fresh focused features, R2 | [command/result](../agents/logs/review-continuation-2026-11-17/focused-tests-result.json), [output](../agents/logs/review-continuation-2026-11-17/focused-tests.log): **92 passed / 577 assertions, exit 0, 108.15 s**. Eight existing files; SQLite memory, fake/local services, PHP sockets + 1 GiB. | Existing fixes pass their focused regressions; CourseLifecycleTest also passes in this batch. This does not retrospectively turn the old full-suite failure into PASS or prove full-suite isolation. |
| Fresh browser, R2-era | [command/result](../agents/logs/review-continuation-2026-11-17/browser-focused-result.json), [output](../agents/logs/review-continuation-2026-11-17/browser-focused.log): **4 passed / 36 assertions, exit 0, 24.29 s**. Registration, 2FA enrollment/challenge, child password reset. | Corrected landing assertions, once-only recovery visibility and new-password login now have selected runtime evidence. Full family matrix, all themes/states and G7 remain unverified. |
| Fresh G10 | [46 results](../agents/logs/review-continuation-2026-11-17/g10-results.json): **32 PASS / 14 FAIL**. All 45 current review tasks plus historical P1-07 (blocked). Individual logs are `C/g10-T-Px-yy.log`. | Current review subset is **45: 31 PASS / 14 FAIL**. P6-01 now lacks A1 only; its new A2 regression is found. G10 is metadata coverage, not behavioral acceptance. |
| Fresh local diagnostic | [source](../agents/logs/review-continuation-2026-11-17/local-probe.php), [output](../agents/logs/review-continuation-2026-11-17/local-probe.log), [execution record](../agents/logs/review-continuation-2026-11-17/local-probe-result.json): exit 0. Explicit SQLite memory guard before migration; synthetic fixtures; external HTTP prohibited. | Confirms ISSUE-001 growth, remaining ISSUE-038 idle-gap completion and ISSUE-074 quiz-guard gaps. A diagnostic exit 0 means observations were collected, not that product requirements passed. |
| G7/G8 / release | No new complete visual matrix, equal-viewport reference comparison, UX score, AI evaluation, real PDF rendering or production benchmark in this continuation. | NOT VERIFIED / NOT RUN, not PASS. No new independent reviewer verdict is claimed; the prior independent reports remain tied to their revisions. |

The focused tests and probes leave application/test sources unchanged. Their covered fix files match the source manifest; unrelated P7-04 edits during execution prevent a whole-tree approval.

### Existing performance IDs: evidence qualification

- **ISSUE-001 ? corroborated on R2.** `LearnerCourseRows.php:22?36` loads relations per course and invokes both `ContinueLearning::progress()` and `::for()`; those each reach `RecalculateCourseProgress`. Controller `LearnerHomeController.php:42` takes three cards only after the full collection is built. Fresh component-level diagnostic: **1/5/20 courses ? 29/122/478 queries**, **84.15/135.25/658.04 ms**; fresh progress rows caused **1/4/15 writes** in the successive datasets. These are single component invocations, not HTTP medians or LCP. Requirement: PRD ?24 pagination/caching, ?10.1, ED-004 event-maintained progress, R3 no N+1. No new duplicate issue is opened.
- **ISSUE-002 ? retain as historical measurement / source-supported repeated work.** Current code uses `CurriculumOutline` and `EntitledCurriculumQuery`; the earlier `CurriculumTree.php` path is not present. Its illustrative trace path is not an exact artifact identity. This continuation does not claim a fresh 24-query or network measurement.
- **ISSUE-003 ? source-supported / prior benchmark only.** `CoursePolicy.php:25?36` executes fresh `exists()` checks per view/update call. The old 5?50 dataset also changes displayed rows from 5?10, so the measurement alone does not establish growth with total course count at fixed page size. Retain the repeated per-action checks finding; do not remove execution-time authorization to optimize it.
- **ISSUE-004 ? suspected only.** Seven queries at both sizes and lower large-dataset response time do not prove a scalability defect. No current production-engine EXPLAIN/contention evidence establishes full scans. Do not prescribe an index as a verified fix from this record.
- **ISSUE-005 ? optimization, not duplicate execution of an identical query.** Count statements have distinct parameter values. They can be considered for aggregation, but equal SQL shapes alone do not demonstrate redundant results.
- `scripts/benchmark_results.json` currently records median SQL times **47.85?578.53 ms** (/my), **38.41?613.79 ms** (/my/courses), **35.63?83.25 ms** (instructor courses), **10.37?3.95 ms** (course learners). These differ from the earlier prose/table. Use that exact JSON artifact when citing those runs; its capture revision is UNKNOWN.
- The earlier ??100 ms? and ?12 courses? expectations are proposals, not approved PRD thresholds. ED-030 defines mobile **LCP ?2.5 s, CLS ?0.1, INP ?200 ms**; network-idle/server timings do not measure these.
- The existing `benchmark_routes.php` deletes/reseeds shared local records and disables foreign keys while preparing fixtures. It was read, **not rerun** by this continuation. The fresh probe avoids that database entirely.

### Stable issue register

States describe findings, not task board status: **OPEN** = unresolved; **FIX VERIFIED (focused)** = existing working fix passed the named narrow tests, not task approval; **PARTIAL FIX** = original symptom covered but related behavior still fails; **EVIDENCE GAP** = required proof missing. Unless explicitly refreshed, carried findings retain the R0/R1 evidence boundary and are not claimed as newly reproduced races.

Report aliases: [P1](reviews/REVIEW_QUEUE_P1_2026-11-17.md), [P2/P3A](reviews/REVIEW_QUEUE_P2_P3A_2026-11-17.md), [P3B](reviews/REVIEW_QUEUE_P3B_2026-11-17.md), [P4](reviews/REVIEW_QUEUE_P4_2026-11-17.md), [P5](reviews/REVIEW_QUEUE_P5_2026-11-17.md), [P6](reviews/REVIEW_QUEUE_P6_2026-11-17.md), [P7](reviews/REVIEW_QUEUE_P7_2026-11-17.md), [browser](reviews/REVIEW_QUEUE_BROWSER_2026-11-17.md). Their named sections contain original acceptance maps and file:line references.

| ID | Severity / finding / requirement | Evidence and current disposition |
|---|---|---|
| ISSUE-006 | BLOCKER: gate/coverage provenance incomplete. ED-020/028/029; milestone rules. | P1 F6; original root gate table; corrected outputs above. EVIDENCE GAP. Old counts cannot approve a moving tree; G5 TODO, full coverage, CI, milestone demos and current full regression remain open. |
| ISSUE-007 | BLOCKER: full reference/UX acceptance absent. D-026, YG-GAP-QA-003/UX-001. | P1 F4; P2/P3A visual section; P3B G7; P4/P5 visual sections; P6 F8. EVIDENCE GAP. Existing dirty screenshots/UI_BASELINE/UI_AFTER are preserved, not claimed absent or accepted. Native layout/token deviations and real Arabic certificate PDF remain task-specific gaps. |
| ISSUE-008 | BLOCKER: required super-admin bypass audit absent. PRD ?6, P1-03 step. | P1 F1; `app/Providers/AppServiceProvider.php:72`. OPEN source finding; mutation audits do not establish bypass audit. |
| ISSUE-009 | EXTERNAL SPEC: production age-band seeds lack approved values. PRD ?5.1, O-015. | P1 F2; `database/seeders/DatabaseSeeder.php`, P1-07. OPEN; configurable resolver is present. Do not invent brackets. Board is already blocked. |
| ISSUE-010 | Historical browser assertions expected obsolete /profile. P1-10 A1 / P1-11. | P1 F3 + browser failures. FIX VERIFIED (focused): existing FamilyFlowsTest/TwoFactorScreensTest edits now pass four selected journeys in C/browser-focused.log. Full G7 still ISSUE-007. |
| ISSUE-011 | 2FA mutation/audit not atomic. YG-GAP-SEC-003, ED-016. | P1 F5. Existing transactions in `DisableTwoFactor.php`, `RegenerateRecoveryCodes.php`; audit-null/throw regressions in TwoFactorTest. FIX VERIFIED (focused), C/focused-tests.log. |
| ISSUE-012 | SHOULD: age style-tier consumer bypasses resolver hook. P1-07 ai line, O-015. | P1 F7; `AgeBandResolver.php:13`, now committed `AI/Assistant/StyleTier.php:16`. OPEN integration/spec mapping; null cutoffs do not authorize invented numeric ages. |
| ISSUE-013 | BLOCKER: signed protected media URL lacks viewer/current-access authorization. PRD ?9.8/17.2/23.13. | Same root cause as P2/P3A #1 and P4 #1. R2 source reconfirmed: `routes/web.php:36`, `MediaPlaybackController.php:18?30`, `PrivateFileProvider.php:88`. OPEN. Anonymous forwarding/revocation HTTP regressions still missing; signature is not a current entitlement check. |
| ISSUE-014 | Published paid media could become T3 / lose preview without guard. YG-GAP-VID-002. | P2/P3A #2; integrated P3-04/06. Existing `AttachLessonMedia` and `ManageLessons` locked mutation checks + PaidT3PublishTest pass in C. FIX VERIFIED (focused); no concurrency/provider certification. |
| ISSUE-015 | Generated long slug checked before truncation. PRD ?9.2. | P2/P3A #3; `SaveCourseWizard.php` now bounds candidate/suffix. New repeated/same-prefix AR/EN regression in CourseWizardTest passes in C. FIX VERIFIED (focused). |
| ISSUE-016 | BLOCKER: category raw image bypasses reencoding. YG-GAP-SEC-004, ED-031. | P2/P3A #4; `CategoryResource.php:79`, SaveCategory. OPEN; raw public upload is not sanitized-derivative evidence. |
| ISSUE-017 | BLOCKER: required course-list columns/sorting incomplete. PRD ?9.1. | P2/P3A #5; `CourseResource.php:74`, `ListCourses.php:23`. OPEN section/lesson/enrollment counts and sorting; pricing belongs to unresolved P3-03, not invented values. Performance repeat is ISSUE-003/005. |
| ISSUE-018 | BLOCKER / spec conflict: instructor cannot start required course-creation journey. PRD ?9.4, J03. | P2/P3A #6; R2 `CoursePolicy.php:20?22` requires admin-panel access. OPEN; direct Action/page policy disagreement. Restrictive tests are not owner approval. |
| ISSUE-019 | BLOCKER: heuristic PDF inspection misses mixed compressed pages / encoded active names. P3-08 A3, AI ?6.1, ED-031. | P2/P3A #7; `PdfInspector.php:24?33`. OPEN static finding; hostile mixed/encoded fixtures not executed in continuation. Do not label heuristic complete PDF validation. |
| ISSUE-020 | BLOCKER: course rich description remains plain textarea. PRD ?9.2. | P2/P3A #8; `CourseWizardSteps.php:57`, SaveCourseWizard. OPEN missing capability, not a demonstrated XSS exploit. |
| ISSUE-021 | SHOULD / protection acceptance: Vimeo URL alone assigned T2. PRD ?9.6, YG-GAP-VID-001. | P2/P3A #9; `VimeoProvider.php:30`. OPEN ownership/private/domain-restriction/playability evidence; O-005 remains UNKNOWN. |
| ISSUE-022 | SHOULD: identical concurrent PDF upload can fail instead of no-op. AI ?6.1. | P2/P3A #10; `UploadCurriculumDocument.php:63,78`. OPEN static race; uniqueness prevents duplicate rows, not a successful retry response. |
| ISSUE-023 | SHOULD: author cannot remove/edit/preview/link existing resources. PRD ?9.5?9.6. | P2/P3A #11; `ManageCurriculum.php:480` / saveDocuments vs ManageLessonResources. OPEN UI reachability; PDF approval UI does exist under P3-09. |
| ISSUE-024 | SHOULD: registry/traceability stale or invalid IDs. R3, ED-028. | P2/P3A #12; P3B traceability; P4 #11. `REUSE_REGISTRY.md:26?30` still marks built services planned; RecordProgress old module path; ED-037 matrix reference lacks current ED definition. OPEN; fresh G10 results take precedence over guessed failures from blanks. |
| ISSUE-025 | BLOCKER: nonempty poor-quality text skips OCR; punctuation may score readable. AI ?6.3?6.4. | P3B B01; `IngestCurriculumDocument.php:91?96`, `QualityScorer.php:17`. OPEN; fake empty-page test does not prove required fallback. |
| ISSUE-026 | BLOCKER: approved text corrections have hashes but no recoverable revisions. AI ?6.5. | P3B B02; `EditExtractedPage.php:42?44`, unique document/page schema. OPEN; PDF binary version is not text-edit history. |
| ISSUE-027 | BLOCKER: approval can overwrite concurrent correction / index old source. AI ?6.5?6.7. | P3B B03; `ApproveCurriculumDocument.php:45?56` reads before lock. OPEN static interleaving; no executed contention claim. |
| ISSUE-028 | BLOCKER: document Actions omit current course object authorization. R2, PRD ?23.2. | P3B B04; `ApproveCurriculumDocument.php:37`, `EditExtractedPage.php:25`, `ApproveKeyTerms.php:24`; mount-only course gate insufficient after revocation. OPEN. |
| ISSUE-029 | BLOCKER: mandatory G8 cannot be waived by progress prose. AI ?13, P3-09/10 and P7 gates. | P3B B05 + P7-B06. EVIDENCE GAP; no fresh fake-provider eval metrics. P7-05 future ownership does not itself waive present G8. Real provider O-014 remains legitimately open. |
| ISSUE-030 | BLOCKER: assessment associations support sections only. PRD ?12?13, P3-11 goal. | P3B B06; `ManageAssessmentItems.php:28,55`, nonnullable section schema. OPEN course/lesson association; mixed-order tests prove only implemented subset. |
| ISSUE-031 | BLOCKER: duplicated rich image blocks keep source resource IDs. PRD ?9.5, P3-12 A1. | P3B B07; `DuplicateCourse.php:144,158`, `RichContent.php:223`. OPEN despite passing existing lifecycle suite; no duplicate-image-render assertion. |
| ISSUE-032 | BLOCKER: safe-delete registry omits P6 history owners. PRD ?9.5/23.12, P3-12 A2. | P3B B08; `AppServiceProvider.php:98`, `DeleteCourse.php:55,85`. OPEN; restrict FK may throw rather than erase history. Missing deliberate refusal and race proof, not a claim of silent attempt deletion. |
| ISSUE-033 | BLOCKER: subtitle file removed before transactional audit commits. R2/R5, P3-14. | P3B B09; `ManageSubtitleTracks.php:71?73,104?105`. OPEN rollback can restore a row whose file is gone. Failure regression not run here. |
| ISSUE-034 | BLOCKER: rich block authoring incomplete; images not library-only. ED-003, P3-15. | P3B B10; `ManageCurriculum.php:428`, `RichContent.php:223`. OPEN; rendering constructed JSON does not prove authoring controls. |
| ISSUE-035 | SHOULD: mixed valid/invalid VTT accepted. YG-GAP-LRN-003. | P3B S01; `VttValidator.php:36?47`. OPEN; invalid timing line skipped if another valid cue exists. |
| ISSUE-036 | SHOULD: two reference-count queries per library row. R3 no N+1. | P3B S02; `LibraryResource.php:86`, `LibraryItem.php:42`. OPEN source-backed; 25 rendered rows imply 50 count calls, not a newly timed HTTP benchmark. |
| ISSUE-037 | Architecture violation: ingestion mutates outside Actions. YG-GAP-CODE-001 / AGENTS #4. | P3B S03; `AI/Ingestion/IngestCurriculumDocument.php:40,138,167`. OPEN; prior report calls SHOULD, R3 treats business writes outside Actions as a blocker. No source move made. |
| ISSUE-038 | BLOCKER / PARTIAL FIX: fabricated progress. PRD ?10.3, P4-04 A1. | P4 #2 / old ProgressSessionRegressionTest.log confirms same-time completion. Existing tolerance fix passes ProgressTest. **New R2 probe**: heartbeat 0, advance one day without playback, heartbeat 100 ? completed/100%. `RecordProgress.php:43?46` credits unbounded idle elapsed time. Same issue, not a duplicate. |
| ISSUE-039 | Idle session resume bypassed concurrent cap. YG-GAP-SEC-001. | P4 #3 / old regression log. Existing SessionLimiter transaction/enforcement and SessionLimitsTest now pass in C. FIX VERIFIED (focused); real multi-worker DB locking not established by SQLite tests. |
| ISSUE-040 | BLOCKER: T2 watermark/fullscreen coverage missing. YG-GAP-VID-003. | P4 #4 and fullscreen evidence gap; `learn/show.blade.php:87`, Vimeo EMBED descriptor. OPEN: overlay branch excludes embeds; containment screenshot is not fullscreen proof. |

| ISSUE-041 | BLOCKER: supported T2 embed lacks app progress/resume/control bridge. PRD ?10.2?10.3, YG-GAP-LRN-004, P4-03 ai hook. | P4 #5; `player.js:23,166`, `lesson-media.blade.php:17`. OPEN. P7-03 can pause native media directly, but that does not establish Vimeo timestamp/pause/progress integration. |
| ISSUE-042 | BLOCKER: I01 was weakened without Decision Register entry. P4-01 A3, AGENTS #8. | P4 #6; `I01_EntitlementOnlyViaServiceTest.php:15`, commit `7df6c04`, ManualGrantResource. OPEN; passing widened allowlist is not proof of the original invariant. |
| ISSUE-043 | BLOCKER: shared access order/cohort layer/drip basis incomplete. ED-005/025, YG-GAP-COH-001/LRN-002. | **Merged duplicates** P4 #7 and P5 #1. `EntitlementService.php:53?64,140`, EntitledCurriculumQuery. OPEN: preview/drip ordering differs from ED; no cohort-relative unlock or shared membership refusal; other-cohort metadata visible while join POST separately denies the link. |
| ISSUE-044 | BLOCKER: instructor insights lack promised fields and native table composition. YG-GAP-INS-004, D-026. | P4 #8; `filament/courses/learners.blade.php:2?8`, CourseLearnersPage/LearnerCourseDetail. OPEN last activity/completed-items/P6 assessment fields and authorized rendered-page proof. Performance suspicion stays ISSUE-004. |
| ISSUE-045 | BLOCKER: staff preview forces AI unavailable instead of showing read-only availability. P4-10 exact ai line. | P4 #9; `PreviewController.php:71`. OPEN; sending no request satisfies only half the hook. |
| ISSUE-046 | Incomplete free-enrollment caller and manual-grant start-date form. PRD ?18, P4-02. | P4 other gaps; `EnrollInFreeCourse.php:20` only tested, `ListManualGrants.php:65` end-date only. OPEN product steps despite valid domain tests. |
| ISSUE-047 | Parent dashboard lacks entry into child-context action. PRD ?5.2, P4-08 steps. | P4 other gaps; `parent/dashboard.blade.php:12` changes summary only. OPEN; relationship/security tests do not prove the missing navigation. |
| ISSUE-048 | SHOULD: future-start grants counted active by summary/filter. PRD ?18. | P4 #10; `CourseAccessSummary.php:28`, `ManualGrantResource.php:92,144` omit start-time predicate used by Entitlement::isCurrent. OPEN. |
| ISSUE-049 | Partial-channel retry duplicated inbox notice. PRD ?20, P5-04 A1. | P5 #2. Existing NotificationDispatcher stable UUID + LocalizedNotification::shouldSend; real mail-failure retry regression in LiveNoticesTest passes C. FIX VERIFIED (focused); recipient-scope and external delivery remain separate. |
| ISSUE-050 | BLOCKER: OPEN live session bypasses configured lower join-window bound. PRD ?11, P5-03 A2. | P5 #3; `JoinWindow.php:33`, OpenLiveSession. OPEN; PROGRESS assertion is not a higher-priority owner exception. |
| ISSUE-051 | BLOCKER: cohort enrollment-open/close dates and translated name absent. YG-GAP-COH-001, architecture ?10.1. | P5 #4; cohort migration, `ManageCohorts.php:87?96`, ManageCourseCohorts form. OPEN; instructor-assignment UI also unproven. Checkout remains later scope. |
| ISSUE-052 | BLOCKER evidence gap: replay's recorded entitlement/own-READY-PDF integration untested. PRD ?11, P5-05 A2/ai. | P5 #5; untracked LiveReplayTest covers draft/lifecycle but not required published replay flow. OPEN; source reuse is present, no proven AI bypass claimed. |
| ISSUE-053 | SHOULD: promotion seats a waitlisted learner after cohort closes. P5-01 state contract. | P5 #6; `PromoteFromWaitlist.php:26?36`; CohortEdgeCases TODO not a test pass. OPEN status-rule mismatch unless a governing exception is recorded. |
| ISSUE-054 | SHOULD: lifecycle notices use entitlement state without current account/course accessibility. PRD ?20 scoping. | P5 #7; `LiveSessionNotices.php:49?51`, `CourseLearners.php:26?31`. OPEN; distinguish permitted future-session announcements from inactive/suspended recipients. |
| ISSUE-055 | SHOULD: schedule form omits translated description. PRD ?11. | P5 #8; `Concerns/ManagesLiveSessions.php:36?49`. OPEN even though Action accepts description. |
| ISSUE-056 | Cross-course quiz prompts/keys disclosed by mutable Livewire ID. PRD ?12/23.2, P6-01 A2. | P6 F1 reproduced at R0 in ManageQuizIsolationTest.log. Existing `ManageQuiz.php` now locks ID, scopes by course and authorizes every lookup; QuizIsolationTest passes C. FIX VERIFIED (focused); G10 now finds A2 but still misses A1. |
| ISSUE-057 | BLOCKER: autosave can mutate finalized graded answers. PRD ?12 immutable history, P6-02. | P6 F2; R2 `SaveQuizAnswers.php:29?39` checks outside lock, QuizAnswerWriter writes after submit can commit. OPEN static concurrency finding; not reproduced concurrently here. |
| ISSUE-058 | BLOCKER: after-submission quiz does not gate next required item before submission. PRD ?12, ED-025. | P6 F3; `EntitlementService.php:180?184`, SubmitQuizAttempt completion. OPEN; required quiz filtered only for require_pass. |
| ISSUE-059 | BLOCKER: any graded assignment, including zero, counts accepted. PRD ?14, P6-05. | P6 F4; `CompletionReport.php:95?99`, `config/learning.php:22?25`. OPEN; no cited policy approves graded_counts_as_accepted=true. |
| ISSUE-060 | BLOCKER: certificate ignores AR/EN certificate names and does not lock first issuance. YG-GAP-ID-001. | P6 F5; `IssueCertificate.php:51?61`, CertificatePdf/UpdateCertificateNames. OPEN; manually locking a profile fixture does not prove issuance locking. |
| ISSUE-061 | BLOCKER: certificate template persistence absent. P6-06 explicit step, architecture. | P6 F6; CertificatePdf/settings/Blade substitute lacks specified certificate_templates. OPEN; final issuer assets remain O-019, no invented wording required. |
| ISSUE-062 | BLOCKER: acceptance tests absent/incomplete for P6/P7. ED-020/028, R1/R5. | P6 F7 + P7-B06. R2 exception: P6-01 A2 now has passing QuizIsolationTest; A1 still missing. Other P6/P7 G10 failures below persist. Test prompts, unrelated green suites and future-writer assignments are not acceptance evidence. |
| ISSUE-063 | Policy traceability uses unrelated OPEN IDs; assignment lifecycle incomplete. PRD ?12?13, R1. | P6 F9 and P6-03 map; ConfigureQuiz, QuizGrader, ConfigureAssignment reference O-021/022/023/024 for assessment choices, but register IDs mean providers, staffing, e-receipts and auto-renew. OPEN; Under Review/review-method requirements also require reconciliation. |
| ISSUE-064 | SHOULD: hidden quiz result has no normal release writer. PRD ?12 visibility. | P6 F10; `QuizAttemptView.php:62,73`, ReviewEssayAttempt. OPEN; released_at read is not a release workflow. |
| ISSUE-065 | SHOULD: retried grading decision creates a fresh decision/notice. PRD ?20, P6-04. | P6 F11; `DecideSubmission.php:57?74`, SubmissionDecisionNotice. OPEN; once per new decision ID does not deduplicate replay of the same operation. |
| ISSUE-066 | SHOULD / audit integrity: quiz config, assignment config and feedback can persist before failed audit. PRD ?22. | P6 F12; ConfigureQuiz:45?51, ConfigureAssignment:57?64, AddSubmissionFeedback:32?36. OPEN; separate from fixed 2FA ISSUE-011. |
| ISSUE-067 | BLOCKER: parent child context can reuse private child AI conversation. O-016, PRD ?5.2. | P7-B01 at R1; reconfirmed R2 `AssistantController.php:44` uses ActingLearner without private-conversation guard, `AskLessonAssistant::thread()` loads child's history. OPEN. No real minor/provider data used; launch flag does not close the latent authorization gap. |
| ISSUE-068 | BLOCKER: no permission/source recheck after provider call before delivery. Architecture ?5.5, PRD ?15.6. | P7-B02; R2 `AskLessonAssistant.php:157,172?174` rechecks before call, persists after call; controller streams reply. OPEN, current model refresh/source retirement cases untested. |
| ISSUE-069 | BLOCKER: lesson title can break prompt source delimiter. AI ?8.4. | P7-B03; R2 `PromptRenderer.php:25` substitutes title verbatim, only page body neutralized. OPEN; provider output validation does not repair this input boundary. |
| ISSUE-070 | BLOCKER: chunk mode expands retrieved chunks into whole pages. AI ?5. | P7-B04; R2 `ContextBuilder.php:77` assigns all-page text after top-k selection. OPEN bounded-context/performance requirement; a large single page defeats the threshold's purpose. |
| ISSUE-071 | BLOCKER: not-in-source/out-of-scope bypass full JSON schema. AI ?8.2/9.2. | P7-B05; R2 `OutputGuard.php:22?26` returns before required-field/extraneous-key validation. OPEN contract defect; fixed text means this is not itself arbitrary model-output disclosure. |
| ISSUE-072 | SHOULD: retry cost tracks last attempt only. AI ?7/10. | P7 SHOULD #1; R2 `AskLessonAssistant.php:342?346` sums tokens/latency but uses last costMinor. OPEN; budget gates inherit undercount. |
| ISSUE-073 | Required provider streaming / timeout proof absent. AI ?7.10/10.4, P7-01. | P7 SHOULD #2?4; complete() followed by validated SSE does not prove provider stream or wall-clock timeout. OPEN evidence/implementation mismatch; preserve validation-before-display. Context embedding also needs revocation/PII integration proof. |
| ISSUE-074 | BLOCKER, new: quiz guard misses older open attempts and frozen questions after bank edit. AI ?8.3, PRD ?12, P7-02 A2. | R2 runtime diagnostic below; `QuizGuard.php:26`, `QuizQuestionMatcher.php:34`, `QuizGuardQuestions.php:27`. OPEN. No answer keys are sent by the probe; it demonstrates guard decision bypass, not a live model answering. |
| ISSUE-075 | BLOCKER, new: assistant UI discards specific safeguarding/quiz-guard response. AI ?8.1/8.3, PRD ?15.6, P7-03. | R2b source `resources/js/assistant.js:222?232` replaces body.message with generic out-of-scope text, ignores guard/page hint. InputGuard returns meaningful fixed text and persistGuarded returns pages/guard. OPEN static UI behavior; no new browser fixture/score claimed. |
| ISSUE-076 | BLOCKER, new: nonanswer/fallback states hide human-help action. AI ?7.14/9.2/10.1, PRD ?16, P7-03/04 integration. | R2b `assistant.js:270,309?312`: actions hidden before request, revealed only on SSE answered result; JSON states do not set lastMessageId/reveal actions. P7-04 feedback wiring is present but cannot be used in these states. OPEN; concurrency snapshot preserved in C/source-assistant.js.txt. |
| ISSUE-077 | HIGH PERF: unmemoized course policy check in review queues multiplies to 500+ queries. R3, PRD §13/24. | Section 2 empirical Telescope benchmark: 5 -> 50 courses jumps from 63 to 513 queries (220.8 ms SQL, 2,725.0 ms latency). Telescope UUID `a34000ed-9220-473e-b69e-422a527b23d1`. OPEN. |
| ISSUE-078 | HIGH PERF: synchronous course progress recalculation on every player heartbeat. PRD §10.3, ED-004. | Section 2 empirical Telescope benchmark: POST /learn/1/1/progress triggers 25 queries and 2 synchronous writes per ping. Telescope UUID `a33ffe23-07c2-4f63-a2da-a92b3671cb88`. OPEN concurrency bottleneck. |
| ISSUE-079 | MEDIUM PERF: side-effect writes during GET read requests. DMC §5.2, PRD §5.2/12. | Section 2 empirical Telescope benchmark: GET /my executes 2 device/session INSERTs; GET /parent executes 3 course_progress INSERTs; GET /learn/.../quiz/{attempt} executes auto-submit and progress writes on timeout. OPEN. |
| ISSUE-080 | BLOCKER: missing `course_reviews` table crashes public course details `GET /courses/{slug}` with 500 error. PRD §9.2/23.1, T-P2-05. | Section 2 empirical Telescope benchmark: 500 Server Error (13,569 ms), 17 queries, QueryException on missing table. Telescope UUID `a3401454-cbba-4125-ad79-cb963d84f9af`. `ReviewSummary.php:21`, `CourseSeo.php:28`. OPEN. |

### New reproductions and bounded conclusions

**ISSUE-038 extension.** In C/local-probe.php, a synthetic entitled learner sends position 0 for a 100-second published video, clock advances one day, then sends position 100 without intervening playback/heartbeats. Current RecordProgress returns completed, 100 seconds, 100%. The earlier same-time exploit is repaired; the unbounded idle interval still earns credit. Required follow-up evidence is a pause/resume/offline interval dataset and downstream sequence/completion assertions. No implementation change was made.

**ISSUE-074.** Two in-progress attempts for the same learner in the same course hold different prompts. Matching the older prompt returns false; matching the latest returns true. Marking the latest submitted makes the older prompt match. Editing that older question in the live bank then makes its unchanged attempt-snapshot prompt fail matching. StartQuizAttempt.php:40 scopes its resume check per quiz, so multiple open quizzes are not prohibited globally. The diagnostic constructs legal database fixture states directly; it does not claim an end-to-end browser attack. Required proof: all relevant open attempts and their frozen prompt projections, without keys, must be guarded.

**ISSUE-075/076.** The server already returns the localized guard message and page hint, while the client substitutes generic copy. ?Still not understood? is wired for successful answers by concurrent P7-04 work; it is not accurate to say no help endpoint exists. The defect is the JSON/fallback/quota path's inaccessible help action and lost guard-specific content. In the R2b copy, `showJsonState` neither consumes `body.guard` nor displays its `source_pages`. Browser state fixtures remain required; these are source-traced findings, not a new G7 verdict.

### Current G10 missing identifiers

Every row is backed by the corresponding `C/g10-<task>.log`; command: `php artisan app:req-coverage --task=<task>`, process exit 1. Others exit 0 in C/g10-results.json.

| Task | Missing now | Change from historical evidence |
|---|---|---|
| T-P4-10 | YG-GAP-INS-004, YG-GAP-LRN-005, ED-024 | Same |
| T-P4-MG | A1 | Same |
| T-P5-01 | YG-GAP-COH-001, ED-013 | Same |
| T-P5-02 | ED-032 | Same |
| T-P5-05 | A1, A2 | Same; existing untracked tests do not supply matching task acceptance metadata |
| T-P6-01 | A1 | A2 now found; focused isolation regression passed |
| T-P6-02 | A1, A2, A3, A4 | Same |
| T-P6-03 | A1, A2, A3 | Same |
| T-P6-04 | A1, A2 | Same |
| T-P6-05 | A1, A2 | Same |
| T-P6-06 | A1, A2, A3, YG-GAP-ID-001 | Same |
| T-P7-01 | A1, A2, A3, A4, ED-009 | Separate historical addendum, absent from original 43-row CSV |
| T-P7-02 | A1, A2, A3, A4, ED-010 | Newly committed review task since R1 |
| T-P7-03 | A1, A2, A3, ED-009 | Newly committed review task since R1 |

### Task-by-task requirement ? implementation ? verification ? remaining gap

This covers **all 45 current review tasks**, plus historical **T-P1-07** (blocked), and identifies concurrent P7-04 separately. Exact acceptance-item and `ai:` assessments are retained in the linked phase reports; the task specifications in agents/tasks/P00?P07.md govern. ?Source? below means implementation/assertions inspected or carried from the named revision, not freshly executed acceptance. ?C focused? and ?C browser? mean the exact fresh runs above. Each row's G10 has its own current log even where the behavioral evidence is historical.

| Task | PRD / governing requirement | Implementation / existing evidence | Current verification and open findings |
|---|---|---|---|
| T-P0-MG | Milestone rules, Roadmap M0, PRD ?24/26 | docs/evidence/p0-closure/README.md; PROJECT_STATE/HANDOFF. [P1 map](reviews/REVIEW_QUEUE_P1_2026-11-17.md). | G10 PASS; continuity exists, no new clone/demo/current milestone run. ISSUE-006/007. |
| T-P1-03 | PRD ?6, permission catalog and audited bypass | RolePermissionSeeder, AppServiceProvider; RbacCatalogTest. P1 A1?A3. | G10 PASS, source-supported seeding/negative grants; ISSUE-008. No new whole RBAC suite run here. |
| T-P1-04 | PRD ?22 safe audit / parent actor provenance | AuditLogger, AuditLogResource; AuditLoggerTest/AuditViewerTest. | G10 PASS; prior source-supported A1?A3, current viewer QA/reference gap ISSUE-007. |
| T-P1-07 (blocked) | PRD ?5.1, O-015; resolver/style hook | AgeBandResolver/AgeRange; AgeBandResolverTest. | G10 PASS; configured computation exists, seed/classification unresolved ISSUE-009/012. No status change. |
| T-P1-10 | PRD ?5.2, family/auth journey; YG-GAP-UX-001 | PageController, family/auth views; FamilyFlowsTest. | G10 PASS; C browser registration passes, ISSUE-010 narrow fix verified; full states/themes ISSUE-007. |
| T-P1-11 | YG-GAP-SEC-003/FAM-007/FAM-008, ED-016/017 | 2FA Actions, child reset/router; TwoFactorTest, ChildRecoveryTest, TwoFactorScreensTest. | G10 PASS; C focused audit rollback + C browser 3 flows pass. ISSUE-011 focused fixed; all routing/visual acceptance not rerun, ISSUE-007. |
| T-P1-MG | Checklist ?3, PRD ?26 | P1DemoTest and p1-milestone README; historical evidence. | G10 PASS is not closure: unresolved prerequisites, G5 TODO, UX/performance/demo proof ISSUE-006/007/008/009. |
| T-P2-01 | PRD ?5.1, YG-WEB-003, SRCH-001, SEC-004 | CategoryResource/SaveCategory; CategoriesTest and browser matrix. | G10 PASS, source core coverage; raw image policy ISSUE-016; current G7 ISSUE-007. |
| T-P2-02 | YG-WEB-002/005, PRD ?19.2 | CMS Actions/controller/resources; CmsPagesTest and CMS browser tests. | G10 PASS; prior source acceptance supported; no additional confirmed scoped defect. Fresh complete QA/G7 ISSUE-007 remains. |
| T-P3-01 | PRD ?9.1/9.3/9.4, D-007 | CourseDirectory/CoursePolicy/CourseResource; I02_CourseScopeTest. | G10 PASS; counts/scoping tests exist; list/create gaps ISSUE-017/018; performance ISSUE-003/005; visual ISSUE-007. |
| T-P3-02 | PRD ?9.2 Basic/Information | SaveCourseWizard, CourseWizardSteps; CourseWizardTest and CourseWizardPagesTest. | G10 PASS; C focused slug regression verified ISSUE-015. Rich description/instructor flow ISSUE-018/020; reference mismatch ISSUE-007. |
| T-P3-04 | PRD ?9.5, YG-GAP-CNT-001, ED-002/024 | ReorderCurriculum/ManageLessons/sections; CurriculumManagerTest, CurriculumBrowserTest. | G10 PASS; source ordering/archive assertions; integrated media guard ISSUE-014 focused fixed. Actual layout/settings panel acceptance ISSUE-007 remains. |
| T-P3-05 | PRD ?9.6/9.8, VID-001/002/004, ED-007 | Media providers, AttachLessonMedia, LessonPublishRule; MediaProvidersTest/PaidT3PublishTest. | G10 PASS; C focused publication mutation fixed ISSUE-014; redemption ISSUE-013 and Vimeo assurance ISSUE-021 remain. |
| T-P3-06 | PRD ?9.6/9.8, ED-031 | UploadLessonMedia, ManageCurriculum; UploadLessonMediaTest, CurriculumPageTest. | G10 PASS; source upload assertions; integrated ISSUE-014 narrowly verified. Resource workflow ISSUE-023 / visual ISSUE-007. |
| T-P3-08 | PRD ?9.7/9.8, AI ?6.1/6.7 | UploadCurriculumDocument, PrivateFileAccess/SignedFileUrl; PrivateFilesTest. | G10 PASS; source signed-file/version coverage; decoded validation ISSUE-019, duplicate race ISSUE-022, workflow ISSUE-023; G7 open. |
| T-P3-09 | AI ?6/14, PRD ?9.7, ED-011 | IngestCurriculumDocument, approval/edit Actions; PdfIngestionTest/ReviewDocumentPageTest. | G10 PASS; real Arabic PDF/OCR proof incomplete; ISSUE-025?029/037 and G7 ISSUE-007. Fake strings are not real extraction evidence. |
| T-P3-10 | AI ?2/16, PRD ?15 | AssistantEligibility, provider/prompt contracts and FakeAiProvider; AssistantEligibilityTest. | G10 PASS; prior scoped eligibility assertions/source supported, no final G8 ISSUE-029. Later endpoint issues belong to P7. |
| T-P3-11 | PRD ?12?13, CNT-001 | ManageAssessmentItems; AssessmentItemsTest mixed-order/hook coverage. | G10 PASS; missing course/lesson association ISSUE-030, changed-row G7 ISSUE-007. |
| T-P3-12 | PRD ?9.1/9.5 safe duplicate/delete/history | DuplicateCourse/DeleteCourse; CourseLifecycleTest. | G10 PASS; C focused lifecycle suite passes; old aggregate failure preserved. Rich image remap/history ISSUE-031/032 untested and unresolved. |
| T-P3-13 | CNT-003, ED-026 | Library Actions/resource/attachment reuse; ContentLibraryTest/LibraryPageTest. | G10 PASS; prior static scope/delete coverage; reference-count N+1 ISSUE-036 and picker/G7 ISSUE-007. |
| T-P3-14 | LRN-003, ED-008 | ManageSubtitleTracks/VttValidator; SubtitleTracksTest. | G10 PASS; rollback integrity ISSUE-033, malformed mixed cues ISSUE-035; no AI captions required. |
| T-P3-15 | CNT-002, ED-003/031, D-018 | RichContent/editor/renderer; RichContentTest, RichLessonBrowserTest. | G10 PASS; XSS tests exist but block-authoring/library policy ISSUE-034 and authoring G7 ISSUE-007 remain. |
| T-P4-01 | PRD ?17.2/23.1?5, authoritative entitlement | Grant/Revoke/ExpireEntitlements, EntitlementService; EntitlementServiceTest/I01. | G10 PASS; issued media bypass ISSUE-013, invariant policy ISSUE-042; service unit behavior does not prove every entry point. |
| T-P4-02 | PRD ?18, Checklist ?5 | EnrollInFreeCourse, manual grant Actions/resource; EnrollmentAndGrantsTest. | G10 PASS; domain source coverage, product caller/date start ISSUE-046, future counts ISSUE-048, inherited ISSUE-013/042. |
| T-P4-03 | PRD ?10.2, player controls/ai hook | LearnController, player.js, EntitledCurriculumQuery; CoursePlayerTest/BrowserTest. | G10 PASS; historical one native-controls browser pass only. ISSUE-013/041 and G7 ISSUE-007; synthetic video fixture is not playback delivery proof. |
| T-P4-04 | PRD ?10.3, ED-004/LRN-006 | RecordProgress/Recalculate/ContinueLearning; ProgressTest. | G10 PASS; C focused same-time regression passes; C probe still fails idle rule ISSUE-038. Embed progress ISSUE-041; shared cost ISSUE-001. |
| T-P4-05 | PRD ?10.1, own data/dashboard | LearnerHomeController/LearnerCourseRows; LearnerDashboardTest/browser. | G10 PASS; scope source supported. C query-growth probe ISSUE-001; progress ISSUE-038; G7 ISSUE-007. |
| T-P4-07 | VID-003, SEC-001 | SessionLimiter/ResetUserDevices/Watermark; SessionLimitsTest/WatermarkBrowserTest. | G10 PASS; C focused idle session fix ISSUE-039. T2/fullscreen ISSUE-040 not established by ordinary screenshot. |
| T-P4-08 | PRD ?5.2, O-016 | Parent controller/dashboard, shared course rows; ParentDashboardTest/browser. | G10 PASS; linked-child/private-content source coverage; missing context link ISSUE-047, shared scaling ISSUE-001, G7 ISSUE-007. |
| T-P4-09 | LRN-001/002, ED-005/025 | EntitlementService/AccessDecision, locked views; AccessLayersTest/LockedLessonBrowserTest. | G10 PASS covers implemented subset; cohort/order/drip ISSUE-043, inherited forged completion ISSUE-038, quiz progression ISSUE-058. |
| T-P4-10 | INS-004/LRN-005, ED-024, D-026 | CourseLearnersPage/LearnerCourseDetail/PreviewController; InstructorInsightsTest. | G10 FAIL; missing fields/native table ISSUE-044, AI preview state ISSUE-045, G7 ISSUE-007. Test A-number drift must not replace actual behavior map. |
| T-P4-MG | Checklist ?6, PRD ?26 enrolled-learning demo | PROGRESS historical counts; delivery checklist still incomplete; P4-06 blocked context. | G10 FAIL A1; no newly executed full grant?child?resume?PDF?parent demo or reference acceptance. ISSUE-006/007 plus P4 defects. |
| T-P5-01 | COH-001..003, ED-013 | Cohort Actions/management page; CohortsTest + untracked edge cases. | G10 FAIL; actual capacity contention unproven; membership ISSUE-043, fields ISSUE-051, promotion ISSUE-053, G7. |
| T-P5-02 | PRD ?11, ED-032 | Schedule/Reschedule/lifecycle Actions and ManagesLiveSessions; LiveSessionsTest. | G10 FAIL ED-032; source UTC/scoping coverage exists. Description ISSUE-055; schedule-form G7 ISSUE-007. |
| T-P5-03 | PRD ?11 protected links/window/no live AI | GetJoinLink/JoinWindow/live card; JoinLinkTest and existing browser captures. | G10 PASS; join POST guarded, metadata ISSUE-043; OPEN-window bypass ISSUE-050; visual incomplete. |
| T-P5-04 | PRD ?20 locale/deduplication; help-notice hook | NotificationDispatcher/LocalizedNotification/router/reminders; LiveNoticesTest. | G10 PASS; C focused mail-retry regression passes ISSUE-049. Audience ISSUE-054; real SMTP not established. |
| T-P5-05 | PRD ?11 attendance/replay; own PDF hook | Attendance Actions/PublishLiveReplay; untracked AttendanceTest/LiveReplayTest. | G10 FAIL; correction assertions exist but replay access/own-source integration ISSUE-052 and attendance/replay G7 remain. |
| T-P6-01 | PRD ?12 authoring/scoping, text-only guard hook | ManageQuiz/ConfigureQuiz/ManageQuestions/QuestionRules; new QuizIsolationTest. | G10 FAIL A1 only; C focused A2 secrecy fix ISSUE-056 verified. Per-type proof ISSUE-062; policy ISSUE-063, tokens/G7 ISSUE-007. |
| T-P6-02 | PRD ?12 immutable attempts/timer/limits/keys | Start/Save/SubmitQuizAttempt, QuizSnapshot/View, expiry command. | G10 FAIL A1?A4; source scaffolding not test coverage. Autosave ISSUE-057, progression ISSUE-058, hidden release ISSUE-064, guard ISSUE-074, G7. |
| T-P6-03 | PRD ?13 private files/revisions/late policy | SubmitAssignment/AssignmentFiles/SubmissionView and learner uploader. | G10 FAIL A1?A3; missing behavioral proof ISSUE-062; lifecycle/review-method policy ISSUE-063 and G7. No AI grading required. |
| T-P6-04 | PRD ?13/20 human scoped grading/notices | ReviewAccess/DecideSubmission/GradeEssayAnswer and native queues. | G10 FAIL A1/A2; tests ISSUE-062, decision replay ISSUE-065, audit rollback ISSUE-066, G7. No AI feedback required. |
| T-P6-05 | PRD ?14, O-020 configured completion | CompletionReport/Evaluator/Trigger and manual override UI. | G10 FAIL A1/A2; grade?acceptance ISSUE-059, datasets/idempotency ISSUE-062; added UI still requires G7. |
| T-P6-06 | PRD ?14, ID-001, R-030 | Issue/Reissue/RevokeCertificate, CertificatePdf/controller/views. | G10 FAIL; identity lock ISSUE-060, templates ISSUE-061, tests ISSUE-062, actual Arabic PDF/render/privacy endpoint/G7 evidence missing. |
| T-P7-01 | PRD ?15.2/15.4/15.6, AI ?4?9/17/19, ED-009 | Committed AskLessonAssistant/Controller/ContextBuilder/OutputGuard. | G10 FAIL A1?A4/ED-009. R1 report rechecked against R2 core: ISSUE-067?073 remain; no G8 ISSUE-029 or acceptance proof ISSUE-062. |
| T-P7-02 | AI ?8.1/8.3/10, ED-010, PRD ?5.3/15.6 | Committed InputGuard/PiiMasker/QuizQuestionMatcher/AnswerCache/QuotaLedger/BudgetGate. | G10 FAIL A1?A4/ED-010; guards/quota/cache source exists, no acceptance/eval suite. C probe confirms ISSUE-074; inherited privacy/cost ISSUE-067/068/072. |
| T-P7-03 | PRD ?15.1/15.3, AI ?3/15, ED-009 | Committed assistant panel/opener + assistant.js; concurrent feedback integration changes. | G10 FAIL A1?A3/ED-009; no task state matrix/axe/reference proof. Guard copy/help paths ISSUE-075/076; embed interaction ISSUE-041. |

### Concurrent P7-04 slice: inventory only

P7-04 is outside the review-status queue but is already in the working tree. Its governing requirements are PRD ?15.5/16, D-019?D-021 and AI ?12. Present artifacts include `app/Domain/AI/Help/`, HelpRequest/AiFeedback models, `2026_11_17_390000_create_help_request_tables.php`, AssistantFeedbackController/HelpRequestController, HelpRequestResource, thread views and help escalation command. Routes, translations, registry and panel registration were changing between R2 and R2b.

No task completion, independent permission/idempotency/SLA verification or visual acceptance is inferred. The focused feature suite happened to migrate this working schema in memory; that is not P7-04 acceptance. Its not-understood fallback integration shares ISSUE-076, so do not create another issue for the same hidden action. No new G10/P7-04 status claim is made.

### Remaining acceptance evidence

Required next evidence, not implementation performed by this review: full stable-revision regression/coverage and G5 without TODOs; failed G10 acceptance mappings with meaningful tests; forbidden/cross-object/error/concurrency cases for open blockers; all affected AR/EN mobile/desktop light/dark journeys and equal-viewport reference review; actual Arabic certificate PDF; fake-provider G8 thresholds and later explicitly gated real-provider checks; production-engine capacity/locking and ED-030 mobile measurements. Unknown provider/legal/age/branding/deployment inputs remain UNKNOWN. None of these gaps is closed by a green narrow test batch.
