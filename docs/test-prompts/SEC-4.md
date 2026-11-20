# Tests prompt: SEC-4 — Order History Safe-Delete Blocker, Row Lock & 120-Char Duplicate Slug Clamp (`T-P3-12`)

Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. Write regression tests only in `tests/Feature/Catalog/CourseLifecycleTest.php`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Run `npm run build` before any test run. On Windows run Pest with `php -d memory_limit=2G vendor/bin/pest <path>`.

## Vulnerabilities / Gaps Being Tested
1. **Missing Orders History Blocker & Lock (`DeleteCourse` / `OrderHistory`):** `DeleteCourse` previously lacked a `CourseHistoryChecker` for `order_items` (`product_type = 'course'`) and `order_item_allocations` (`course_id`), allowing courses referenced by pending/failed/historical orders (which have no DB foreign key on `order_items.product_id`) to be hard-deleted. Also `DeleteCourse::blockers()` ran outside `DB::transaction()` without `lockForUpdate()`.
2. **Slug Length Overflow (`DuplicateCourse`):** `DuplicateCourse::uniqueSlug()` appended `-copy` / `-copy-<n>` without clamping `$base` within the 120-character `slug_ar`/`slug_en` column limit, risking truncation/constraint errors on MySQL 8 when duplicating courses with max-length slugs.

## Files & Classes Under Test
- `App\Domain\Commerce\OrderHistory` (implementing `App\Domain\Catalog\Contracts\CourseHistoryChecker`, tagged `'course.history'` in `AppServiceProvider`)
- `App\Domain\Catalog\Actions\DeleteCourse`
- `App\Domain\Catalog\Actions\DuplicateCourse`
- `App\Domain\Commerce\Order`, `OrderItem`, `OrderItemAllocation`, `OrderStatus`, `ProductType`

## Behaviours, Attack Scenarios & Edge Cases to Assert
Tag all tests with `->group('T-P3-12', 'A1', 'A2', 'PRD-09.5', 'SEC-4')`:

1. **Direct course order blocks `DeleteCourse` (pending, failed, cancelled, or paid, with NO entitlement/progress):**
   - Create a course with no entitlements, progress, cohorts, or quiz attempts.
   - Create an `Order` (`OrderStatus::Pending`, or `Failed` / `Cancelled`) + `OrderItem` with `product_type => ProductType::Course`, `product_id => $course->id`.
   - Assert `DeleteCourse::blockers($course)` contains `'orders'`.
   - Assert `app(DeleteCourse::class)($admin, $course)` throws `ValidationException` with localized message (`__('courses.blocked.orders')`).
   - Assert the course, its curriculum rows, and its storage files still exist, AND `Activity::query()->where('event', 'course.deleted')->count() === 0` (confirming blockers check runs before audit/deletion inside the transaction).

2. **Bundle `OrderItemAllocation` blocks `DeleteCourse` even without a direct course `OrderItem`:**
   - Create a bundle `Order` + `OrderItem` (`product_type => ProductType::Bundle`, `product_id => $bundleId`) and an `OrderItemAllocation` referencing `course_id => $course->id`.
   - Assert `DeleteCourse::blockers($course)` contains `'orders'` and `DeleteCourse` throws `ValidationException`, leaving the course, rows, and files intact.

3. **Unrelated `product_type` with the same numeric `product_id` does NOT block deletion:**
   - Create an `OrderItem` with `product_type => ProductType::Bundle` (or `ProductType::Plan`) and `product_id => $course->id` (no `OrderItemAllocation` referencing `$course->id`).
   - Assert `DeleteCourse::blockers($course)` is `[]` and `DeleteCourse` succeeds cleanly (verifies `OrderHistory` scopes strictly by `ProductType::Course`).

4. **120-character ASCII & Arabic slug clamping in `DuplicateCourse`:**
   - Create a course whose `slug_en` is 120 ASCII characters (e.g. `str_repeat('a', 120)`) and `slug_ar` is 120 Arabic characters (e.g. `str_repeat('د', 120)`), and another test where the 115th/114th character is `'-'` (`str_repeat('a', 114).'-'.str_repeat('b', 5)`).
   - Duplicate the course repeatedly (e.g., 1st copy `-copy`, 2nd copy `-copy-2`, up through `-copy-10` by seeding copies or looping).
   - Assert for EVERY copy:
     - `mb_strlen($copy->slug_en) <= 120` and `mb_strlen($copy->slug_ar) <= 120`.
     - Valid UTF-8 (`mb_check_encoding($copy->slug_ar, 'UTF-8') === true`).
     - No double hyphen `--copy` at the cut boundary.
     - All generated slugs are distinct (`slug_ar` and `slug_en` unique across copies and source).

## SQLite Limits
- `SQLite :memory:` executes on a single connection where `SELECT ... FOR UPDATE` (`lockForUpdate()`) is a syntax-level no-op and true two-connection concurrent delete vs order-insert races cannot be executed in SQLite; verify the transactional execution path (no `course.deleted` audit written when blocked) and `OrderHistory` behavior deterministically.

## Verification Commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/Catalog
php -d memory_limit=2G vendor/bin/pest tests/Feature/Commerce/OrderEngineTest.php
php -d memory_limit=2G vendor/bin/pest tests/Arch tests/Feature/Invariants
```
