# Milestone demo script: P8 (commerce) and P9 (operations, privacy, reports)

Walk-through for the product owner (PO). Follow the steps in order: later steps reuse the orders, coupons and access created by earlier ones. Work on a **local** or **staging** build only, never production.

Base URL below is `http://localhost:8000` (the `php artisan serve` default). Replace it with your host.

Every step carries a status tag:

- **[SHOW]** can be shown end to end today.
- **[PARTIAL]** the screen or action exists, but part of the expected behaviour is missing or depends on an open owner decision. The step says what to check and what to skip.
- **[GAP]** cannot be shown yet. Do not improvise; record it as a gap in the checklist at the end.

Known gaps and demo receipts are tracked in `docs/reviews/GATE_READINESS_P8_P9.md`. Treat expected results as instructions to verify on the demo dataset.

---

## 0. Before you start

### 0.1 Setup (run once, in the project root)

```bash
php artisan migrate                  # adds demo_anchors and the P8/P9 tables
php artisan demo:seed                # creates the demo dataset (refuses if demo data already exists)
```

- Allowed environments: `local`, `staging`, `testing` (`config/demo.php`). `APP_ENV=production` is refused in code.
- Reset between runs: `php artisan demo:reset` (removes only demo rows and rebuilds them). `php artisan demo:reset --remove-only` removes them without rebuilding.
- Payments: with no Paymob credentials (B-07), checkout uses the **fake gateway** in `local` and `testing` only. No real provider is called.
- Mail uses the array mailer. Every demo address is on `demo.younggo.test`.

### 0.2 Accounts

Password for every demo account: **`DemoPass#2026`** (public, demo only; from `config/demo.php`).
Email = `<key>@demo.younggo.test`.

| Key | Login | Role | Used in steps |
|---|---|---|---|
| Admin | `admin@demo.younggo.test` | admin | most staff steps; default panel `/admin` |
| Sara (academy instructor) | `instructor.academy@demo.younggo.test` | instructor_academy | 12, 9, 10 (own lines and statements) |
| Karim (independent instructor) | `instructor.indie@demo.younggo.test` | instructor_independent | 12, 13 (agreement acceptance) |
| Nour (AR learner) | `learner.nour@demo.younggo.test` | student | 6, 11, 17, 18 |
| Omar (EN learner) | `learner.omar@demo.younggo.test` | student | 1 to 5, 7, 14, 16, 17 |
| Laila (AR learner) | `learner.laila@demo.younggo.test` | student | 13 (applicant), 17 (anonymization, run last) |
| Mona (parent) | `parent@demo.younggo.test` | parent | 6 (parent dashboard, optional) |
| Adam (child, 9) | username `demo.adam` | student, guardian-linked | 18 (sensitive child refusal) |
| Yara (child, 12) | username `demo.yara` | student, guardian-linked | optional |

**Finance account.** `finance@demo.younggo.test` (role `finance`, demo password) is created by the demo seeder, so the finance-only steps (3, 7, 8, 9, 10) can be run as written.

Staff panels (sign in at the panel URL; Filament redirects to its login):

| Panel | URL | Default landing |
|---|---|---|
| Admin | `/admin` | Workspace dashboard |
| Finance | `/finance` | Workspace dashboard |
| Instructor | `/instructor` | Workspace dashboard |

Learner pages use `/login` (learners and parents). Two-factor: if `identity.two_factor_enforced` is on, staff are sent to the two-factor setup first. Complete it with the PO's own authenticator. Do not bypass it.

### 0.3 Language

- Staff panels: the locale switcher sits in the global search bar. Public and learner pages: the locale switch in the header.
- For every step, check the **EN** view, then switch to **AR** and check the RTL layout and the Arabic labels. Use the AR column in each step as the checklist for that.
- Known gap (T-P9-09): `default_locale` and `market` are stored in settings but not read at runtime yet.

### 0.4 Test data rules

- Use the demo coupons, demo accounts and the test images in `tests/` or any non-personal image for uploads. **Never upload a real ID, bank slip or card.** Upload a placeholder image only.
- Do not enter real payout details, real Paymob keys or real IDs. Enter the demo values only.

---

## 1. Course catalog filters and course page CTA [SHOW]

**Role:** guest (signed out), then Omar (learner).
**URL:** `/courses`, then a course page `/courses/{course}` (click a course card).

**Steps**
1. Signed out, open `/courses`. Expected: course cards for the 8 demo courses (paid, free, subscription-only, live, hybrid and recorded), each with a Free or Paid label. Demo prices come from the seed (`acting-101` at 450.00 EGP). Verify on first run.
2. Filter by **Format = recorded** (`?format=recorded`). Expected: only recorded courses remain, and a removable chip shows the active filter.
3. Filter **Access = paid** (`?access=paid`), then add a price range (`price_min`, `price_max`). Expected: the list narrows and the chips update. Remove one chip at a time. The list must match the chips.
4. Sort by price (sort options come from `CatalogQuery`). Verify the order on first run.
5. Search `q=acting`. Expected: `Introduction to Acting` (`acting-101`) appears.
6. Open the `acting-101` course page as a guest. Expected CTA: **"Sign in to enroll"** (`data-cta="sign-in"`), linking to `/login`.
7. Sign in as Omar. Reopen the same course page. Expected CTA: **"Buy now"** (`data-cta="buy"`), linking to `/checkout/course/{id}`. Omar does not own `acting-101` yet. (The seed gives Omar only a cancelled order for it.)
8. Open the `drawing-basics` course page (free, granted to Omar in the seed). Expected CTA: **"Continue learning"** (`data-cta="continue"`), linking to `/learn/{course}`.

**AR:** check the same filters and the three CTA labels in Arabic. The filter chips must still read correctly in RTL.

---

## 2. Checkout with a coupon [SHOW]

**Role:** Omar (learner).
**URL:** `/checkout/course/{acting-101 id}` then `/checkout/orders/{order}`.

**Steps**
1. From step 1, click **Buy now** on `acting-101`. Expected: the checkout page `/checkout/course/{id}` shows the course title, the price (450.00 EGP per the seed; verify) and the learner selector. The learner selector is Omar (the acting account).
2. Click the checkout button. Expected: redirect to `/checkout/orders/{order}` with status **pending**. No gateway call is made yet.
3. On the order page, enter coupon **`DEMO-FLAT50`** (50 EGP off) and apply it. Expected: the total drops by 50 EGP to 400.00 EGP (derived from the seed; verify), a coupon chip appears, and a notice confirms it.
4. Remove the coupon (the remove action). Expected: total returns to 450.00 EGP.
5. Apply **`DEMO-WELCOME10`** instead (10%). Expected: 405.00 EGP. Keep this coupon on the order.
6. Try an unknown code, e.g. `NOPE-0001`. Expected: a validation error, the order total is unchanged.
7. Stop here. Keep the pending order with `DEMO-WELCOME10` applied for step 3.

**Notes:** the coupon is reserved at apply time and released when the order is cancelled or fails (T-P8-05). Coupon rate limits apply (`checkout-coupon` throttle). If you hit the throttle, wait one minute.

**AR:** the coupon field, the total and the error messages in Arabic. Check the numbers still read in the correct direction (LTR digits in an RTL page).

---

## 3. Manual payment proof upload and approval by finance [SHOW]

**Role:** Omar (upload), then **finance** user (approve). See the finance note in section 0.2.
**URL:** Omar: `/checkout/orders/{order}` then `/my/orders/{order}/payment-proof`. Finance: `/finance/manual-payments`.

**Steps**
1. As Omar, on the pending order from step 2, choose the **manual transfer** method (the list always offers manual alongside the gateway methods). Tick the terms checkbox and submit. Expected: redirect to `/my/orders/{order}/payment-proof`.
2. Enter the transfer reference (unique per order) and upload a placeholder image or PDF. Submit. Expected: a confirmation, and the order shows a **pending review** state. The order is not paid.
3. Try to upload the same reference again. Expected: refused (unique reference).
4. Sign in as finance, open `/finance/manual-payments`. Expected: the proof is listed as pending. The file is only reachable through the private file link (`/payments/proofs/{proof}/file`).
5. Open the proof. Expected: details and the private file link. Approve it with the approve action (requires `payment.approve_manual`). Add a note if the form asks for one.
6. Sign back in as Omar. Open `/my/payments`. Expected: the order is **paid**, and the course is available: `acting-101` now shows **Continue learning** on its course page.
7. Reject path (optional): submit a second proof on another order and reject it. Expected: the order returns to pending, with the rejection reason visible to Omar.

**Expected side effects (verify):** coupon redemption is recorded, access is granted for `acting-101` through `GrantOrderEntitlements`, and the eligible ledger entry uses the applicable commission rule (see step 9).

**AR:** the proof form labels, the reference hint and the approval buttons in Arabic.

---

## 4. Local fake gateway payment [SHOW]

**Role:** Laila (learner).
**URL:** her pending demo order, then `/fake-gateway/{reference}`.

1. Run locally with `PAYMENTS_GATEWAY=fake` and `PAYMENTS_LIVE_ENABLED=false`. As Laila, open her pending order or start checkout for an eligible paid course.
2. Select **card** or **wallet**, accept the terms and submit. Expect the local fake gateway page.
3. Choose **Fail payment**. Expect a return to the order with an attempt failure and an option to retry.
4. Retry and choose **Pay**. The fake callback follows the normal fulfillment path. Expect a paid order and access to its course.

Verify on first run. B-07 remains open for real Paymob.

---

## 5. Bundle purchase [SHOW]

**Role:** Omar (buyer), finance (approval).
**URL:** `/checkout/bundle/{bundle id}`; the bundle id is in `/admin/bundles` (admin). The seeded bundle is **Acting and Stage Bundle** (`acting-101` + `stage-workshop`).

**Steps**
1. As admin, open `/admin/bundles`. Expected: the bundle with its AR and EN names and its per-market price. Note the bundle id.
2. As Omar, open `/checkout/bundle/{id}`. Expected: the bundle checkout page with the two courses listed and the total. The buyer chooses a cohort for each eligible live course. To see the chooser, use Mona buying for Adam, who has no existing seat in the workshop.
3. Choose manual transfer, submit a placeholder proof (as in step 3), and have finance approve it.
4. Expected: the order is paid. Access is granted for both courses. `acting-101` is already held by Omar from step 3, so the allocation is recorded as **skipped (already held)** and not granted twice (verify the label on first run).

The bundle also has a public entry at `/bundles/{slug}`. Verify the purchase from that entry on first run.

---

## 6. Subscription plan purchase, renewal and expiry [PARTIAL]

**Role:** admin (view plan), Nour (learner, existing subscription), finance (renewal and expiry checks).
**URL:** `/admin/subscription-plans`, `/admin/subscriptions`, `/checkout/orders/{order}` (renewal link).

**Steps**
1. As admin, open `/admin/subscription-plans`. Expected: the monthly demo plan with its two subscription courses (`creative-sub` and `voice-dubbing`).
2. Open `/admin/subscriptions`. Expected: Nour's active subscription from the paid plan order (`demo-order-005`).
3. **Purchase:** Open `/plans` as an eligible learner and follow the plan CTA to checkout. Pay through the local fake or manual path.
4. **Renewal:** Sign in as Nour, open `/my/subscription` and use the manual renewal action when available. Expect a pending renewal order. Verify the next period after payment.
5. **Expiry:** On a dedicated scratch fixture with an elapsed period, run `php artisan entitlements:expire` and `php artisan subscriptions:process`. Expect the subscription to expire and plan access to end.

Verify the purchase and manual renewal on first run. Expiry requires an elapsed-period fixture.

---

## 7. Refund request, approval, receipt and credit note PDF [PARTIAL]

**Role:** finance (request), admin (approve, which needs `payment.refund`), then Omar (receipts).
**URL:** `/finance/refunds` (also `/admin/refunds`); `/my/payments`; `/my/payments/{order}/receipt`; `/my/payments/{order}/refunds/{refund}/receipt`.

**Steps**
1. Use the paid order from step 6 (Omar's flat-coupon order from `DEMO-FLAT50` if present, otherwise the seeded `demo-order-002` for `stage-workshop`). Pick a refund amount within the order total.
2. As finance, open `/finance/refunds` and use the **Request refund** header action (order reference, amount, reason of at least 10 characters). Expected: a row in **requested** status.
3. As admin, open the row and **approve** it (approve action, `payment.refund`). Expected: status **approved**.
4. Execute the refund from the Execute action. Expected: the modal shows the gateway or manual flow depending on the method. Gateway execution uses the fake gateway in local. Manual needs an external reference (min 3 characters). Expected: status **executed**, and the order shows refunded totals.
5. As Omar, open `/my/payments`. Expected: the order and the refund are listed.
6. Open the sale receipt `/my/payments/{order}/receipt`. Expected: receipt page; PDF download (receipt PDF, `ReceiptKind::Sale`).
7. Open the refund receipt (credit note) `/my/payments/{order}/refunds/{refund}/receipt`. Expected: a credit note that points at the sale it reverses (`ReceiptKind::Refund`).

**Status: [PARTIAL].** Caveats:
- **Learners cannot request refunds.** Requests are staff-only (`ListRefunds` header action). Record it as a gap if the owner expects a learner request.
- **PDF output** may return 503 when headless Chromium is missing (the statement PDF in T-P8-10 says so; the receipt PDF may behave the same). Record the status code and the reason.
- For an eligible full refund with access revocation, verify that bundle entitlements and funded subscription periods end as specified. For a paid order with a recorded cohort seat source, verify the seat becomes available if the learner has no other current grant for that course.
- Refund reversal of ledger entries runs on `RefundExecuted` (step 9).

**AR:** the refund statuses, the receipt headings and the credit note label in Arabic.

---

## 8. Payment reconciliation page [PARTIAL]

**Role:** finance (or admin).
**URL:** `/finance/payment-reconciliation` (also `/admin/payment-reconciliation`).

**Steps**
1. Open the page. Expected: the daily reconciliation view with mismatches listed, filtered by date (uncapped list, date filtered).
2. Run `php artisan payments:reconcile --date=YYYY-MM-DD` for a demo day. Expected: the command finishes without calling a real provider. Refresh the page.
3. Read the mismatch list. On the demo data, expect few or no mismatches. Anything listed must be explained by the demo data (for example the seeded cancelled or failed orders).

**Status: [PARTIAL].** Paymob reconciliation and refund endpoints are unverified against real Paymob docs (B-07). Show the page and the command. Do not claim provider reconciliation works.

---

## 9. Commission rules and instructor ledger [PARTIAL]

**Role:** finance (rules and adjustments), admin (read only), Sara (own earnings).
**URL:** `/finance/commission-rules`, `/finance/instructor-ledger`, `/instructor/my-earnings` (Sara).

**Steps**
1. As finance, open `/finance/commission-rules`. Expected: a rule per course (or none). O-008 through O-011 define the approved default splits and accounting base. Verify the effective rule for the selected course and order.
2. Open `/finance/instructor-ledger`. Expected: one entry per paid line (from steps 3 and 5), status **accrued**, with the applicable rule source and recorded amount.
3. Try to add a rule as **admin**. Expected: refused or not shown (admin cannot add rules; `settlement.manage` is finance only).
4. Add a rule as finance: a course, an effective date, a percentage. Expected: the rule is saved. Rules are immutable; to change one, end it (`effective_to`) and add a new one.
5. Pay one more demo order (or re-run `php artisan ledger:backfill`, which is idempotent). Expected: the new entry uses the new rule. Earlier entries do not change.
6. As Sara, open `/instructor/my-earnings`. Expected: only her own lines. No other instructor's lines and no learner names.

**Status: [PARTIAL].** Use demo amounts. Live VAT treatment depends on the confirmed seller and tax details.

---

## 10. Statements and settlement batch [PARTIAL]

**Role:** finance (create statements, batch, approve, record payout), Sara or Karim (view own statement and PDF).
**URL:** `/finance/instructor-statements`, `/finance/settlements`, `/instructor/my-statements`, `/my/statements/{statement}/pdf`.

**Steps**
1. Generate statements for last month: `php artisan statements:generate YYYY-MM` (or use the finance page). Expected: one statement per instructor and currency for that month, with a frozen snapshot.
2. As finance, open `/finance/instructor-statements`. Expected: statements listed with balance forward, net, settled and closing.
3. Create a settlement batch from **accrued** entries (list header action on `/finance/settlements` or the ledger bulk action). Expected: entries move to **pending**.
4. Approve the batch. Expected: status **approved**. Use a second authorized approver when four-eyes applies. O-012 gives super admins an exception.
5. Record a payout for one instructor line (method, reference, date). Expected: entries become **paid**, and the batch becomes **paid** when all lines are paid.
6. Check the negative and no-profile case. If an instructor has no payout profile (YG-GAP-INS-003), the line stays **accrued** and appears under carried-forward. Expected behaviour per the T-P8-10 notes; verify on first run.
7. As Sara, open `/instructor/my-statements` and download the PDF `/my/statements/{id}/pdf`. Expected: her statement only. Another instructor's statement id returns 404.

**Status: [PARTIAL].** A settlement is a record only. **The app does not move money.** O-012 sets monthly settlement and configurable minimum payouts of 500 EGP and 100 SAR. PDF output may return 503 without headless Chromium.

---

## 11. Reports and CSV export [SHOW]

**Role:** admin (all reports), finance (sales, refunds, earnings, reconciliation), Sara (own course reports).
**URL:** `/admin/reports`, `/finance/reports`, `/instructor/reports`; export link `/reports/{report}/export`.

**Steps**
1. As admin, open `/admin/reports`. Select Sales for the last 30 days. Also exercise Platform overview, Student progress and Content with the same period.
2. Expected: rows and totals per currency. Totals are never mixed across currencies.
3. Click **Export CSV**. Expected: a download with a UTF-8 BOM, translated headings and money as numbers in major units. Cells starting with `=`, `+`, `-` or `@` are prefixed with `'`. Open the file in a text editor to confirm.
4. Try a report above the row cap (`reports.max_rows`, 50 000). Expected: refused with 422 (never truncated).
5. As Sara, open `/instructor/reports`. Expected: enrollments, attendance, assessments, certificates, help SLA and own earnings for her courses only. No sales or refund reports.
6. Check the audit log: the export is recorded as `report.exported` with filters and row count.

**Status: [SHOW].** The current export delivers CSV from the selected scope and report period. Migration exceptions require a counted legacy import and its report.

**AR:** headings, number formats and the BOM check (Arabic text opens correctly in a spreadsheet tool).

---

## 12. Role dashboards: admin, finance, instructor [SHOW]

**Role:** admin, finance, Sara (instructor).
**URL:** `/admin`, `/finance`, `/instructor`.

**Steps**
1. As admin, open `/admin`. Expected: KPI tiles for the last 30 days from the report definitions, plus the live queues (manual proofs, refunds, instructor applications, privacy requests). KPIs are cached for 60 seconds, so a new order can take a minute to show.
2. As finance, open `/finance`. Expected: finance KPIs (sales, refunds, earnings, reconciliation) and the queues finance owns.
3. As Sara, open `/instructor`. Expected: her courses, enrollments and help-request backlog for assigned courses. No sales or refund KPIs.
4. Compare the admin KPI for sales with the sales report from step 11. Expected: the same figure for the same period.

**Status: [SHOW].** The learner dashboard is out of scope (no spec KPIs, T-P9-07).

**AR:** tile labels and the number format in Arabic.

---

## 13. Instructor application and agreement [SHOW with a note]

**Role:** Laila (applicant), admin (review), Karim (accepts agreement).
**URL:** `/teach` (applicant), `/admin/instructor-applications`, `/admin/instructor-agreements`, `/instructor/instructor-agreement` (Karim).

**Steps**
1. As Laila (signed in, verified adult), open `/teach`. Expected: the "Teach with Young Go" page with the application form (AR and EN).
2. Submit the form with a **placeholder** ID image. Expected: confirmation and an application in **pending** status. The ID file is private.
3. As admin, open `/admin/instructor-applications`. Expected: Laila's application. Download the ID document only from the staff link (`/admin/instructor-applications/{id}/id-document`). Expected: download works for admin. A learner cannot reach it.
4. Approve the application. Expected: Laila gets the independent instructor role and a draft profile. (This changes demo data; `demo:reset` removes it.)
5. As admin, open `/admin/instructor-agreements`. Expected: the seeded agreement is a **draft placeholder**. Publish a version for the demo only.
6. As Karim, open `/instructor/instructor-agreement`, read it and accept. Expected: acceptance is recorded with version, time and IP. Course creation then passes the agreement rule.

**Status: [SHOW] for steps 1 to 4 and 6 mechanically. The agreement wording is not final (D-026 and agreement wording open).** Do not present the seeded text as the legal agreement.

**AR:** the form labels and the agreement in Arabic (check the placeholder notice is visible).

---

## 14. Wishlist and reviews moderation [SHOW]

**Role:** Omar (learner), admin (moderation).
**URL:** `/courses/{course}`, `/my/wishlist`, `/courses/{course}/reviews` (POST via the form), `/admin/course-reviews`.

**Steps**
1. As Omar, open `/courses/{course id of acting-101}` and click the heart. Expected: the course is saved.
2. Open `/my/wishlist`. Expected: `acting-101` listed. Click the heart again. Expected: it is removed.
3. As Omar (now owns `acting-101` from step 3), write a review (rating and short text) on the course page. Expected: the review is submitted as **pending**, with the first name and initial shown as the display name.
4. As admin, open `/admin/course-reviews`. Expected: Omar's review in the queue and the two seeded **approved** reviews. Approve Omar's review. Expected: it appears on the course page; the rating summary updates.
5. Hide an approved review (hide action). Expected: it disappears from the public course page.
6. Check that a learner who does not own the course cannot submit a review (eligibility check).

**Status: [SHOW].** Reviews need entitlement; the demo data supplies the approved reviews.

**AR:** the heart label, the review form and the moderation actions in Arabic.

---

## 15. Consent banner and marketing tags [SHOW]

**Role:** guest (signed out), admin (tags).
**URL:** any public page (for example `/courses`), `/cookies`, `/admin/marketing-tags`.

**Steps**
1. Open `/courses` in a private window (no cookie). Expected: the consent banner appears with Accept and Decline choices.
2. Choose **Decline** (privacy default). Expected: the banner closes, and no marketing tags are emitted. Check the page source for the GA4 or Pixel scripts. Expected: absent.
3. Reload. Expected: the banner does not show again (the decision cookie is set; wording version applies).
4. Open `/cookies`. Expected: the full cookie policy page with the current decision, and a way to change it.
5. As admin, open `/admin/marketing-tags`. Expected: GA4 and Pixel ID fields, **empty by default**. Do not enter a real ID. Leave empty, or use a test string and remove it after the check.
6. Accept the banner on a second private window and check the page source. Expected: tags appear only on the public variant (not on learner or staff pages). If a tag appears on a learner page, record it as a defect.

**Status: [SHOW].** Gaps: counsel wording (YG-GAP-PRV-004) and O-027 are open. Keep the banner text as draft.

**AR:** banner text, buttons and the cookies page in Arabic.

---

## 16. Notification templates, preferences and an announcement [PARTIAL]

**Role:** admin (templates, announcement), Omar (preferences), Sara (sees the announcement).
**URL:** `/admin/notification-templates`, `/profile/notifications` (Omar), `/admin/announcements`, `/instructor/announcements` (Sara).

**Steps**
1. As admin, open `/admin/notification-templates`. Expected: templates with AR and EN text (the matrix is `docs/NOTIFICATION_MATRIX.md`). Open one and read it. Do not change the wording without the owner.
2. As Omar, open `/profile/notifications`. Expected: per-channel opt-ins. Reminders and announcements are optional; the defaults are documented as O-default. Change one preference and save.
3. As admin, create an announcement (EN and AR text, audience: learners, dated). Publish it. Expected: it appears on the learner side for the audience. Check it on Omar's `/my` page.
4. As Sara, open `/instructor/announcements`. Expected: the announcement, read only.
5. Run one notification source: the order-paid or manual-proof approval from step 3 should have queued the sale notice (check the mail log with the array mailer, or the in-app list).

**Status: [PARTIAL].** Access and assignment due reminders are scheduled 7, 3 and 1 days before the deadline. Use a scratch fixture with an eligible deadline to demonstrate them. Phone reminder delivery depends on the actual channel provider.

**AR:** template text and preference labels in Arabic.

---

## 17. Privacy request: export and anonymization [PARTIAL]

**Role:** Omar (request export), admin (approve and complete), Laila (anonymization, run last).
**URL:** `/profile/privacy`, `/admin/privacy-requests`, `/profile/privacy/requests/{id}/download`.

**Steps**
1. As Omar, open `/profile/privacy`. Expected: the privacy page with request types (export, correction, deletion).
2. Submit an **export** request. Expected: a request in **pending** status.
3. As admin, open `/admin/privacy-requests`. Expected: Omar's request. Approve it, then **Complete** (runs the export). Expected: status complete.
4. As Omar, download the export from `/profile/privacy/requests/{id}/download`. Expected: the personal data export. Check that the file opens and that it contains Omar's data only.
5. Cancel a second request (cancel action). Expected: status cancelled and the request is not run.
6. **Anonymization (run last, on Laila only).** As Laila, submit a **deletion** request. As admin, approve and complete it. Expected: the account is anonymized **in place**. Financial, audit and certificate records are kept. Verify on first run which fields are blanked.
7. After this step, run `php artisan demo:reset` to restore the demo data.

**Status: [PARTIAL].** O-025 keeps learning records while the account is active and for two years after documented account closure. Rehearse `privacy:prune` with a scratch fixture older than that cutoff. The finance and certificate evidence survives anonymization and pruning.

**AR:** the request types and the status labels in Arabic.

---

## 18. Learner 360 and view-as with its banner [SHOW]

**Role:** admin (`learner.360.view`, `learner.360.sensitive`, `support.view_as`).
**URL:** `/admin/learner-360`, then the view-as pages `/support/view-as` (banner shows in every staff panel).

**Steps**
1. As admin, open `/admin/learner-360` and search **Nour**. Expected: profile, entitlements, orders (masked where needed), attempts, refunds, progress, privacy requests, audit trail and view-as history.
2. Check masking: the admin sees full email and phone for Nour. For **Adam** (child), the contact details and birth year are never shown, even to admin.
3. Start a view-as session on Nour. Enter a **reason of at least 10 characters**. Expected: a session of **20 minutes** starts. Adam's session is **10 minutes** and needs `learner.360.sensitive` (admin has it).
4. Expected **banner**: a role=status banner appears in the admin panel and on the view-as pages. The admin panel is read only: every Filament action is refused until you press Stop.
5. Try to change something in the admin panel during the session (for example, open an action). Expected: refused.
6. Open `/support/view-as` and view Nour's courses (read only). Expected: the learner view, not the admin view.
7. Press **Stop** on the banner (`POST /support/view-as/stop`). Expected: the session ends, the banner disappears, and the admin panel is editable again. The audit log shows `support.view_as_started` and `support.view_as_stopped`.
8. Refusal check: try a view-as on the **parent** Mona, or on your own account. Expected: refused. The refusal is audited (`support.view_as_refused`).

**Status: [SHOW].** Gaps: expired sessions close lazily (on the next request), not by a scheduler; an unknown lesson id on the assistant block returns 404 rather than 403; no browser screenshots yet (A3, G7).

**AR:** the banner text, the reason field and the Stop button in Arabic.

---

## 19. Settings hub and sidebar parity [SHOW]

**Role:** admin, finance, Sara (instructor), then compare.
**URL:** `/admin/settings`, `/admin/general-settings`, `/admin/payment-payee`, `/admin/retention-policies`, and the sidebar in each panel.

**Steps**
1. As admin, open `/admin/settings` (the settings hub). Expected: links to general settings, payment payee, retention policies, notification templates, marketing tags and the audit log.
2. Open general settings. Expected: the form loads with the demo values. Change nothing.
3. Open payment payee. Expected: the manual transfer payee shown on the checkout page (step 3). Do not enter real bank details. Use the demo values only.
4. Open retention policies. Expected: the configured periods, alongside the O-025 policy in the decision register.
5. **Sidebar parity (admin).** Dashboard comes first, followed by **New course** if the user can create courses. The groups start with **Review and grading**, **Support and messages**, **Courses** and **Sales and enrolment**. Confirm that the available links follow the role-specific catalog and match `docs/LEGACY_PARITY.md`.
6. **Sidebar parity (finance).** The finance sidebar shows only the classes the finance panel registers (`/finance` panel: dashboard, payment reconciliation, reports, and the finance resources such as coupons, bundles, plans, subscriptions, manual payments, refunds, payout profiles, commission rules, ledger, statements and settlements).
7. **Sidebar parity (instructor).** The instructor sidebar shows only: dashboard, reports, AI pages (test console, insights, availability), instructor agreement, payout profile, courses, library, submission and essay review, help requests, announcements, my earnings, my statements.
8. Confirm each group label in EN and in AR.

**Status: [SHOW] for the hub and the sidebar. Gap:** `default_locale` and `market` are stored but not read at runtime (T-P9-09).

**AR:** group labels and the RTL order of the sidebar.

---

## 20. Checklist

Fill in the **Pass / Fail** column and the notes. Use **N/A** only when the step is marked GAP and you have recorded it. Keep one row per step. Use the role column to see who must be signed in.

| Step | Topic | Role | Pass / Fail | Notes |
|---|---|---|---|---|
| 1 | Catalog filters and course CTA | Guest, Omar | | |
| 2 | Checkout with coupon | Omar | | |
| 3 | Manual proof upload and finance approval | Omar, finance | | |
| 4 | Fake gateway payment | Laila | | Local fake gateway; verify fail and retry |
| 5 | Bundle purchase | Omar, finance | | Public bundle entry |
| 6 | Subscription plan, renewal, expiry | Admin, Nour, finance | | Expiry needs an elapsed-period fixture |
| 7 | Refund request, approval, receipts, credit note | Finance, admin, Omar | | Partial: learner cannot request; PDF may 503 |
| 8 | Payment reconciliation | Finance | | Partial: Paymob unverified (B-07) |
| 9 | Commission rules and instructor ledger | Finance, admin, Sara | | Verify the effective rule and accounting base |
| 10 | Statements and settlement batch | Finance, Sara | | Verify threshold and second approval (O-012) |
| 11 | Reports and CSV export | Admin, finance, Sara | | |
| 12 | Role dashboards | Admin, finance, Sara | | KPIs cached 60 s |
| 13 | Instructor application and agreement | Laila, admin, Karim | | Agreement wording not final (D-026) |
| 14 | Wishlist and review moderation | Omar, admin | | |
| 15 | Consent banner and marketing tags | Guest, admin | | Counsel wording open (YG-GAP-PRV-004) |
| 16 | Notification templates, preferences, announcement | Admin, Omar, Sara | | Check 7/3/1-day reminders in a dated fixture. Phone delivery requires a provider. |
| 17 | Privacy export and anonymization | Omar, admin, Laila | | Run anonymization last, then demo:reset |
| 18 | Learner 360 and view-as banner | Admin | | Lazy expiry; no screenshots yet |
| 19 | Settings hub and sidebar parity | Admin, finance, Sara | | locale and market not read at runtime |
| AR | RTL and Arabic labels across steps | All | | Check each step's AR column |
| Setup | Finance account seeded | PO | | Confirm login before steps 3 and 7–10 |

---

## Appendix: commands used in this script

```bash
php artisan migrate
php artisan demo:seed
php artisan demo:reset                     # rebuild demo data after the demo
php artisan demo:reset --remove-only       # remove demo data only
php artisan statements:generate YYYY-MM
php artisan payments:reconcile --date=YYYY-MM-DD
php artisan ledger:backfill
php artisan subscriptions:process
php artisan entitlements:expire
```

## Appendix: open owner decisions that affect the demo

- O-007: legal seller, tax registration and VAT information.
- O-023: accountant confirmation of ETA applicability. The adapter stays disabled.
- O-013 and T-P12-01: Saudi market (disabled).
- O-021: phone reminder provider (fake channel only).
- O-027 and YG-GAP-PRV-004: consent wording.
- D-026: agreement and visual review wording.
- B-07: Paymob credentials and endpoint verification.
