# T-G7-FIXES-G: tests prompt (for another model)

Write Pest tests only; do not change application code. Repo rules: `AGENTS.md`. Group `T-G7-FIXES-G`. Context: `docs/reviews/G7_P9_REREVIEW_2026-11-19.md` (defects X1 to X5 and D1). Helpers: reuse the existing page, cohort and commerce test helpers (`pageUser`, `asUser`, demo seed) instead of new ones.

## Under test
- X2 seat state and waitlist: `Cohorts\CohortAvailability::stateFor()`, route `POST /courses/{course}/waitlist` (`catalog.waitlist`, `CourseWaitlistController`, uses `Actions\JoinWaitlist`), `catalog/_cta.blade.php`, `catalog/show.blade.php` (`data-seat-state`, `data-cta="waitlist|waitlisted|buy"`), lang `cohorts.join_waitlist|on_waitlist|waitlist_joined|waitlist_hint`, `commerce.cohort_full` (reworded), `checkout.kind_course_recorded|live|hybrid`, `checkout/_summary.blade.php`, `CheckoutController::formats()`.
- X1: 429 page rendered inside `SetLocale` (`bootstrap/app.php` render callback for `ThrottleRequestsException`).
- X3: `PageController::family()` (student without the parent role: friendly `pages.family-unavailable`, status 200; other roles keep 403), `pages/family.blade.php` now on `layouts.learner`.
- X5: `OrderDirectory::query()` (`paid_gateway`, `last_gateway`, `has_manual_proof`), `OrderResource::gatewayLabel()`, lang `orders.gateway_*`.
- X4: `SafeguardingRecipientsSettings::getViewData()` (translated role names), `filament/settings/safeguarding.blade.php`, lang `settings.safeguarding.roles_note|explain`.
- D1: `WorkspaceDashboard::quickAccess()` (grouped, capped, de-duplicated), views `filament/staff/dashboard-links.blade.php` and `dashboard-kpis.blade.php` (`data-count`), CSS in `resources/css/filament-theme.css` (end of file).

## Acceptance
**A. Seat state (X2)**
- A1 Course with one enrolling cohort, capacity 5, 2 seated: page shows `data-seat-state="seats"` with "3 seats left" (AR: `متبقي 3 مقاعد`); CTA is `buy`. Capacity null or no cohort: no seat state, CTA `buy`.
- A2 All enrolling cohorts full (seated, or live seat holds): page shows `data-seat-state="full"`, the "Full" badge and the hint; the CTA is a form posting to `catalog.waitlist` (`data-cta="waitlist"`), and no `data-cta="buy"`. A closed cohort or one outside its window does not count. One cohort full and another with room: `buy`.
- A3 Guest sees the full badge but the sign-in CTA; an entitled learner sees "Continue learning"; a free course has no waitlist button.
- A4 `POST /courses/{id}/waitlist` as a signed-in learner on a full course: creates a `waitlisted` `CohortMember` with `queued_at` (first full enrolling cohort), audit `cohort.waitlisted`, redirects to the course page with the `waitlist_notice`; the page then shows `data-cta="waitlisted"` and no hint. Posting twice keeps the first place (one row). Guest gets the login redirect; a course with room or no cohort returns the `cohort` validation error (`cohorts.not_full`) and writes nothing. Throttle `10,1`.
- A5 `stateFor()` unit cases: null learner, learner who already holds a seat (never full), learner whose own hold is the last seat (not full for them), waitlisted learner (`waitlisted` true, `waitlist_cohort` is their cohort).
- A6 Checkout on a full course: `POST /checkout/start` fails with the `order` error whose text is the new `commerce.cohort_full` (AR and EN); the text must not mention choosing another cohort. Existing `T-COHORT-SEATS` behaviour is unchanged.
- A7 Checkout summary label for a course line is `checkout.kind_course_<format>`: Hybrid course (AR `دورة مدمجة`, matching the course page word `courses.hybrid`), Live course, Recorded course; bundle and plan lines keep `kind_bundle|kind_plan`. Both step 1 (`checkout.product`) and step 2 (`checkout.order`) show it; a Hybrid course never shows "Online course" or `دورة عبر الإنترنت`.

**B. 429 page (X1)**
- B1 Arabic session (user locale `ar`, or session locale): hit a throttled route past its limit (for example `POST /checkout/start` 21 times); the 429 response has `lang="ar"`, `dir="rtl"` and the Arabic `errors.429_title`. English session: `lang="en"`, `dir="ltr"`. JSON requests still get the JSON throttle answer. The `Retry-After` header is kept.

**C. /family (X3)**
- C1 Student (adult): `GET /family` returns 200 with `data-family-unavailable`, the title `pages.family_unavailable_title` in AR and EN, the learner header and `data-bottom-nav`, and no bare 403 title. Super admin and other staff roles still get 403. A parent without `guardian.child.manage` still gets 403.
- C2 Parent: 200, uses the learner layout (`data-bottom-nav` present, the three-item text header of the old `parent` variant is gone); the add-child, link-code and guardian verification cards and child-context behaviour are unchanged.

**D. Orders payment method (X5)**
- D1 Orders list rows: order with a `fulfilled` `PaymentEvent` shows that gateway label (`paymob` as Paymob / `باي موب`, `fake` as Test gateway); order with a manual payment proof shows `orders.gateway_manual`; a paid, refunded, partially refunded or disputed order with neither shows `orders.gateway_manual` (same rule as the receipt); a pending, failed or cancelled order with only an attempt shows that attempt's gateway; with nothing shows a dash. A paid-by-gateway order whose earlier attempt used another gateway shows the fulfilling one. Same on `/admin/orders` and `/finance/orders`, AR and EN.

**E. Safeguarding recipients (X4)**
- E1 The page shows translated role names (EN `Administrator, Support`; AR `مسؤول، الدعم`), never the raw slugs `admin, support` in the roles line, plus the explanation `settings.safeguarding.explain` (`data-safeguarding-explain`). A role slug without a `roles.*` key falls back to the slug. Saving and permissions are unchanged.

**F. Dashboards (D1)**
- F1 Admin, finance and instructor dashboards: `[data-quick-access]` has at most 8 `.yg-dashboard-link` anchors, at most 3 per `data-quick-group`, no duplicate href or label, and, while at least one queue has work (so the queue block lists links), no href equal to a queue link above; when every queue is clear the queue targets stay in quick access. Group titles are the translated `navigation.groups.*`. A user without access to a section never sees it. Empty groups are not rendered; no accessible section shows the empty state.
- F2 `dashboard-kpis` renders `data-count` equal to the number of tiles shown (AI tiles with no data are hidden first).
- F3 Optional browser check (do not edit `tests/Browser/*.php` files that exist; add a new file only if asked): admin dashboard at 390 wide is under 2000px tall with demo data; at 1440 the KPI tiles in each row have equal width and the last row is full.

## Existing assertions that encode old behaviour (adjust only these)
- ADJUSTED ALREADY (by this fix batch): `tests/Feature/Pages/FamilyPagesTest.php`, test "limits the family and link screens to their roles" (student now `assertOk` plus `data-family-unavailable`; parent on `/link` and super admin on `/family` still 403) and test "serves the 404 page for unknown routes and 403 for denied pages" (denied page now exercised with `super_admin` because a student gets the friendly page). Extend, do not revert.
- Any test asserting the old `commerce.cohort_full` wording by literal text (the lang key itself is unchanged, so `__('commerce.cohort_full')` assertions still hold).
- `tests/Feature/Filament/WorkspaceSurfacesTest.php` and the Reports dashboard tests, only if they count quick-access links or look for every resource in the list (the list is now capped and grouped).
- Orders list tests that expect a dash for paid orders without a payment (now "Bank transfer").
