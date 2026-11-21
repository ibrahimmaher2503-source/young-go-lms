# Test Prompt: Performance & N+1 Query-Count Assertions (`PERF-QUERIES`)

Write automated Pest Feature tests (do **not** modify production code) verifying bounded query counts and absence of N+1 regressions on the public, learner, and Filament pages optimized in `[T-P11-02]` (`6f572f4`).

## Target files & classes
- `app/Filament/Pages/LearnerThreeSixtyPage.php` (`mount()`, `getViewData()`, `target()`)
- `app/Domain/Support/Queries/Learner360Record.php` (`for()`)
- `app/Domain/Reports/Queries/PlatformOverviewReport.php` (`rows()`, `totals()`)
- `app/Domain/Commerce/Queries/OrderDirectory.php` (`summary()`)
- `database/migrations/2026_11_30_200000_add_performance_query_indexes.php`
- `app/Http/Controllers/PublicCatalogController.php`
- `app/Http/Controllers/PublicCourseController.php`
- `app/Http/Controllers/LearnerHomeController.php`
- `app/Http/Controllers/ParentDashboardController.php`

## Required behaviours & query-count assertions

1. **Learner 360 single-evaluation (`LearnerThreeSixtyPage`):**
   - Seed an admin user (`super_admin` or `admin` with `learner.record.view` + `learner.record.sensitive` + `payment.view` + `learner.progress.view` + `audit.view`) and a learner with at least 2 entitlements, 2 orders, 2 certificates, 2 help requests, and 3 notification dispatches.
   - Use `DB::enableQueryLog()` / `DB::listen()` around `GET /admin/learner-360/{learner}` (or `Livewire::test(LearnerThreeSixtyPage::class, ['learner' => (string) $learner->id])`).
   - Assert:
     - No normalized SQL query executes more than **2 times** (specifically `entitlements`, `orders`, `certificates`, `help_requests`, `notification_dispatches`, `user_devices`, `activity_log`, `view_as_sessions` each execute **once**, not twice).
     - Total query count on initial render stays `<= 40` (baseline before fix was 58–59).

2. **`PlatformOverviewReport` single-query user aggregation & memoization:**
   - Seed users across `student`, `parent`, `instructor_academy`, `instructor_independent` roles.
   - Run `rows($filters)` followed by `totals($filters)` on the same `PlatformOverviewReport` instance while counting queries via `DB::listen()`.
   - Assert:
     - Calling both `rows()` and `totals()` executes `<= 7` queries total (no `roles` table N+1, and `totals()` does not re-run the DB queries).
     - Counts for `users`, `students`, `parents`, `instructors`, `courses`, `new_enrollments`, `new_completions`, `sessions`, `issued` match the seeded records.

3. **`OrderDirectory::summary()` single grouped query:**
   - Seed `Pending`, `Paid`, and `Cancelled` orders.
   - Count queries while calling `OrderDirectory::summary()`.
   - Assert exactly **1** SQL query executes and returns accurate `['pending' => ..., 'paid' => ...]`.

4. **Public & Learner pages N+1 scaling check (constant query count as rows grow):**
   - Measure `DB::listen()` query count for `GET /en/courses`, `GET /en/courses/{slug}`, `GET /my`, and `GET /parent` with 2 courses vs 8 courses.
   - Assert query count does not grow linearly with course/item count (`<= 20` on catalog, `<= 22` on course detail and student dashboard, `<= 16` on parent dashboard).

5. **Performance indexes exist (`Schema::getIndexes()`):**
   - Assert indexes exist on:
     - `entitlements`: `['learner_id', 'starts_at']`
     - `notification_dispatches`: `['recipient_id', 'id']`
     - `user_devices`: `['user_id', 'last_seen_at']`
     - `orders`: `['payer_id', 'id']` and `['paid_at']`
     - `courses`: `['status', 'category_id']` and `['status', 'created_at']`

## Acceptance / Requirement IDs
- `T-P11-02`, `ED-030`, `YG-GAP-PERF-001`

## Setup hints & SQLite limits
- Use `RefreshDatabase` and `$this->withoutVite()` for HTTP/Livewire feature tests.
- Set `config(['identity.two_factor_enforced' => false])` when testing Filament admin pages (`LearnerThreeSixtyPage`, `ReportsPage`).
- SQLite `:memory:` runs single-connection; query-count assertions via `DB::listen()` work identically on SQLite and MySQL.
- **Rule:** Test writer must **not** modify production code.
