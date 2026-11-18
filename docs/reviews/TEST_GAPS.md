# Test gaps and environment limits

## 1. No coverage or mutation driver

`pcov` and `xdebug` are not installed for the local PHP 8.4.21 build, so line
coverage and mutation scores cannot be produced. `COVERAGE_BASELINE.md` and
`COVERAGE_AFTER.md` record a structural inventory (production class -> test file
that exercises it) instead of percentages. Any future claim about the 90% / 80%
targets needs a PHP build with `pcov` enabled.

## 2. Full-suite runs are not isolated from other PHP processes

On 2026-11-18 this checkout hosted several simultaneous workloads from other agents: a 4-worker `--parallel --coverage` Pest run, `artisan test --group=ISSUE-080`, a full-project `phpstan analyse` with 8 workers, two browser Pest runs, and two `artisan serve` processes. The shared
`storage/framework/views` and `storage/framework/testing/disks/local` directories
are process-global, so the sequential run produced non-deterministic 500s and `filemtime(): stat failed` errors in unrelated files (Pages, Notifications, Observability, Milestone, UiPrimitives). A later full run reached only about 370 of 833 cases in 30 minutes and was stopped, because the other workloads kept the CPU saturated.

Rules for a trustworthy full run:

- no other `php`, `artisan serve`, or Pest process against this checkout;
- run `php -d extension=sockets -d memory_limit=2G vendor\bin\pest --testsuite=Unit,Feature,Arch`;
- run browser tests separately, never in parallel with the Feature suite.

Until that quiet run exists, the full suite result is unverified. Every new file
passes on its own and in its directory run (see `TEST_REPORT.md`).

## 3. SQLite in-memory

- Row locks (`lockForUpdate`) and real transaction isolation cannot be proven.
  Tests assert the surrounding behavior (counts, status transitions) and the
  structural test in `tests/Arch` covers service boundaries.
- Parallel writes are serialized by the single connection; deadlock behavior of
  MySQL is not exercised.
- `Storage::fake('local')` clears the fake disk on every call. Test files must not
  assume files written by another file still exist.

## 4. Browser and screenshots (Wave 10)

`php artisan test --testsuite=Browser` needs `-d extension=sockets` because
`sockets` is disabled in the local `php.ini`. Playwright browser binaries are not
confirmed installed for this machine. Browser/visual acceptance remains open.

## 5. Partial areas

- Filament resource pages: only `CurriculumPageTest`, `CurriculumManagerTest`,
  `AssessmentItemsTest` and the shared table workspace were inspected; the other
  resources have no new edge-case file yet.
- HTTP/Livewire controllers outside Curriculum/Library were not re-audited.
- AI: `IngestCurriculumDocument` and the retrieval guard have existing tests; no
  new edge-case file was added in this pass.
- Identity and Catalog now have dedicated edge-case files (`IdentityEdgeCasesTest`,
  `CatalogEdgeCasesTest`); their remaining gaps are policy questions rather than
  untested paths. Commerce now has `CommerceEdgeCasesTest`, but order updates,
  coupon reservation/removal and manual approval are blocked by BUG-007. Settings,
  shared HTTP middleware and most Filament resources are still scheduled for later
  waves.

## 6. Known flakes

- `filemtime(): stat failed` under `storage/framework/views` appears only when
  processes share the checkout. It is an environment race, not an application
  defect.
- `tests/Feature/Filament/CurriculumPageTest.php` "saves a lesson with a private
  upload" can see a stale `media/clip.mp4` left by a concurrent run. It passes in
  a clean, isolated run.
