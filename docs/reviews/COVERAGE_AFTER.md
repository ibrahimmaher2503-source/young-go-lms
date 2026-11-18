# Coverage after the deep test pass

Measured: **unavailable** (no `pcov`/`xdebug`, see `COVERAGE_BASELINE.md`). No
percentage is claimed.

## Structural additions

New test files and the production behavior they now pin:

| New file | Production code newly exercised |
|---|---|
| `tests/Feature/Live/AttendanceTest.php` | `Domain/Live` attendance + replay actions, `LiveAccess` |
| `tests/Feature/Live/RecordJoinTest.php` | join recording, `RecordLiveJoin` |
| `tests/Feature/Live/JoinWindowTest.php` | `config/live.php` window, `JoinWindow` |
| `tests/Feature/Live/LiveReplayTest.php` | replay availability + authorization |
| `tests/Feature/Entitlements/EntitlementEdgeCasesTest.php` | `EntitlementService`, `CourseAccessSummary` |
| `tests/Feature/Learning/ProgressEdgeCasesTest.php` | progress recording, speed cap, completion threshold |
| `tests/Feature/Cohorts/CohortEdgeCasesTest.php` | `ManageCohorts`, `PromoteFromWaitlist`, `CohortAccess` |
| `tests/Feature/Notifications/NotificationRoutingTest.php` | `NotificationDispatcher`, `LearnerNotificationRouter`, `GuardianCopyNotification`, `LiveSessionNotices` |
| `tests/Feature/Curriculum/CurriculumEdgeCasesTest.php` | `CurriculumAccess`, curriculum actions and queries |
| `tests/Feature/Library/LibraryEdgeCasesTest.php` | `LibraryAccess`, library actions, `LibraryItem` |
| `tests/Feature/Media/MediaPrimitivesEdgeCasesTest.php` | `SourceUrl`, `VttValidator`, `Watermark`, `PlaybackDescriptor`, `ImageReencoder`, `SignedFileUrl` |
| `tests/Feature/Identity/IdentityEdgeCasesTest.php` | link-code hashing/lifecycle, child context, `SessionLimiter`, avatar updates, locale redirects |
| `tests/Feature/Catalog/CatalogEdgeCasesTest.php` | `SaveCategory`, `CourseDescription`, `AgeRangeRule`, `AgeBandResolver`, `PublicCategory`, `PublicCourseDetail`, course pricing |
| `tests/Feature/Commerce/CommerceEdgeCasesTest.php` | `SaveCoupon`, `ValidateCoupon`, `CouponUsage`, coupon caps/freeze/allocation, `SubmitManualPaymentProof`, `RejectManualPaymentProof` |

## Horizontal coverage added

- Every new file has a task header and requirement/A-item groups.
- Error paths assert translated messages from both locales.
- Boundary values are table-driven where the production rule is a list
  (URL allowlist, WebVTT rejection reasons, source types).
- Four defects are still captured as `todo` tests (BUG-004..007); BUG-001..003
  were fixed in production during the pass and their tests now pass. BUG-007
  blocks all order-update paths, so coupon removal/cancellation and manual
  approval stay todo-tested until it is fixed.

## Verification status

Each file passes in isolation and in its directory run. A full-suite percentage
cannot be measured, and a clean full-suite pass is still required on an idle
machine before the package is called green.
