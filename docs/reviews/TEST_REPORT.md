# Deep test pass report

Date: 2026-11-18. Scope: tests and review docs only. Production code, migrations,
routes, config and views were not changed.

## Result summary

- Baseline before this pass: 692 passed, 2 todos (`Unit,Feature,Arch`).
- Selected Wave 1�8a files (latest scoped run): 175 passed, 7 todos
  (BUG-004..007), 708 assertions. Three earlier findings (BUG-001..003) were
  fixed in production by concurrent work during the pass and now pass, so their
  todo markers were removed. Wave 8a alone: 8 passed, 4 todos, 71 assertions.
- This pass itself changed no production code. Seven defects were documented in
  `BUGS_FOUND.md`; three are fixed by other agents and four remain `->todo()`.
  BUG-007 is a checkout blocker: every order update throws before status or
  totals logic runs.
- Full-suite verification is still pending a quiet machine: on 2026-11-18 several
  Pest, PHPStan, browser and `artisan serve` processes from other agents shared the
  same checkout, which caused intermittent 500s, missing compiled views and a
  30-minute run that completed only about 370 cases. Each new file is green when
  run alone and in its directory group.

## Waves

| Wave | Area | File | Cases |
|---|---|---|---|
| 1 | Live sessions | `tests/Feature/Live/AttendanceTest.php`, `RecordJoinTest.php`, `JoinWindowTest.php`, `LiveReplayTest.php` | 28 |
| 2 | Entitlements | `tests/Feature/Entitlements/EntitlementEdgeCasesTest.php` | 13 passing (BUG-003 test now passes) |
| 3 | Progress | `tests/Feature/Learning/ProgressEdgeCasesTest.php` | 16 |
| 4 | Cohorts | `tests/Feature/Cohorts/CohortEdgeCasesTest.php` | passing (BUG-001/BUG-002 tests now pass) |
| 5 | Notifications | `tests/Feature/Notifications/NotificationRoutingTest.php` | 11 |
| 6a | Curriculum | `tests/Feature/Curriculum/CurriculumEdgeCasesTest.php` | 12, 1 todo (BUG-004) |
| 6b | Library | `tests/Feature/Library/LibraryEdgeCasesTest.php` | 7 |
| 6c | Media primitives | `tests/Feature/Media/MediaPrimitivesEdgeCasesTest.php` | 6 definitions, 40 data rows |
| 7a | Identity | `tests/Feature/Identity/IdentityEdgeCasesTest.php` | 13 |
| 7b | Catalog | `tests/Feature/Catalog/CatalogEdgeCasesTest.php` | 13 passing, 2 todos (BUG-005/BUG-006) |
| 8a | Commerce coupons and manual payments | `tests/Feature/Commerce/CommerceEdgeCasesTest.php` | 8 passing, 4 todos (BUG-007) |

Covered behavior includes: attendance windows and replay authorization; join
window boundaries with `config/live.php`; entitlement expiry/grant alignment and
reporting; progress speed caps, slack, completion and manual completion; cohort
waitlist and schedule editing; learner/guardian notification routing and locale;
curriculum bulk, reorder, publish/unpublish and preview rules; library upload,
re-encode and delete rules; media source allowlists, WebVTT validation,
watermarking, playback descriptors and signed URLs; identity link-code
normalisation/retention/suspension, child-context password expiry and pending or
revoked links, session rotate/device/touch/labels, avatar replacement and
dimension limits, locale return-target safety; catalog category parent cycles,
image rollback and orphan cleanup, age ranges/bands, public category exclusion,
public curriculum visibility/minutes, description sanitising and course pricing;
coupon normalisation/audit/validation/freeze/caps/date windows, per-line discount
allocation, fixed-currency and course restrictions, manual-payment reference
normalisation/private storage/SLA/duplicate and pending-proof rules, rejection and
reference reuse. Order updates, coupon reservation/removal and manual approval are
blocked by BUG-007 and remain todo-tested.

## Commands used

```powershell
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Live
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Entitlements
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Learning
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Cohorts
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Notifications
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Curriculum
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Library
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Media/MediaPrimitivesEdgeCasesTest.php
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Identity/IdentityEdgeCasesTest.php
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Catalog/CatalogEdgeCasesTest.php
php -d extension=sockets -d memory_limit=2G vendor\bin\pest tests/Feature/Commerce/CommerceEdgeCasesTest.php
php -d extension=sockets -d memory_limit=2G vendor\bin\pest --testsuite=Unit,Feature,Arch --colors=never
```

## Test conventions followed

- File header: `// T-Pxx-yy | REQ-IDs | A1 ... A3`.
- Every test carries `->group('T-Pxx-yy', 'Ax', ...)` where applicable.
- No real sleeps; time control via `travelTo`.
- No dependency on test execution order; per-file helper prefixes avoid Pest
  global function collisions.
- Error messages asserted from `lang/ar` and `lang/en`, never hard-coded prose.

## Next

Wave 7 (Identity and Catalog) and Wave 8a (Commerce coupons/manual payments) are
complete. BUG-007 blocks the order-update portion of commerce and needs an owner
decision. Wave 8 continues with the remaining Filament resources. Wave 9: settings
and shared HTTP. Wave 10: browser/visual matrix. Re-run the full suite on a quiet
machine before declaring the package green.

