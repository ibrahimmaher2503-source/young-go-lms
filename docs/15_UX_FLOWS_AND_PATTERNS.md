# YOUNG GO LMS --- UX FLOWS, PATTERNS & ACCEPTANCE

**Version:** 1.0 · **Date:** 16 November 2026 · **Status:** RECOMMENDED-DEFAULT (ED-023)\
Companion to `11_DESIGN_SYSTEM.md` (how it looks). This file defines **how it
works**: one interaction system for every screen, and step-level specs for
the core journeys with measurable UX acceptance.

## 1. UX principles (scored by `ux-reviewer`)

| # | Principle | Test |
|---|---|---|
| U1 | Clear action hierarchy per task | One visually dominant action per task region; essential player controls, row actions and dialog actions remain available |
| U2 | Always know where you are | Page title + breadcrumb (staff) or back + title (learner) |
| U3 | Always know what's next | Learner screens show the next step (continue, next lesson, complete profile) |
| U4 | Explain blocked states | Every disabled/locked element states why and how to unlock |
| U5 | Forgiving | Destructive actions confirm with consequence text; undo where cheap; drafts autosave |
| U6 | Fast feedback | Every action shows loading within 100 ms and result toast/inline message |
| U7 | Plain language, both locales | No jargon to learners/parents; AR and EN equally natural |
| U8 | Child-safe | No external links without warning on child accounts; no open chat; no ads/trackers on learning screens |
| U9 | Consistent | Same pattern for the same job everywhere (tables, forms, filters, empty states) |
| U10 | Accessible | WCAG 2.1 AA, keyboard, screen-reader labels, 44 px targets |

`ux-reviewer` scores each touched screen 1–5 on U1–U10; any score < 4 is a
rework item; average must be ≥ 4.5.

## 2. Page anatomy

**Learner/parent (custom UI)**
```
[Header: logo · nav · locale · account/child switcher]
[Page title + 1-line helper]                [Primary action]
[Main content (cards/list/player)]
[Secondary content]
[Footer (public only)]
```
**Staff (SKIT/Filament)**
```
[Sidebar (role-aware)] [Topbar: search · locale · theme · account]
[Breadcrumb] [Title]                         [Primary action] [More ▾]
[KPI row (dashboards only)]
[Filters bar] [Table / Form]
```

## 3. Shared interaction patterns

| Job | Pattern |
|---|---|
| List of records (staff) | Table: search, filters bar, sortable columns, row actions menu, bulk actions, pagination 25, CSV export when permitted, empty state with primary action |
| List of courses (learner) | Card grid (catalog) / list with progress (my courses) |
| Create/edit (short) | Slide-over or modal form ≤ 6 fields |
| Create/edit (long) | Full page with sections or stepper (course wizard); sticky save bar; autosave drafts |
| Approve/reject | `ApproveRejectAction`: reason required for reject, shown to the submitter |
| Destructive | Confirm modal naming the record and consequence; type-to-confirm for irreversible finance/data deletion |
| Status | Badge with color + text (never color only) |
| Money | `x-ui.price` / `MoneyColumn` (currency, Western digits, end-aligned) |
| Dates | Relative for recent ("منذ ساعتين"), absolute in accessible detail (hover may supplement); learner timezone |
| Feedback | Toast for success; inline error near field; banner for page-level issues |
| Loading | Skeletons for content; spinner on buttons; disable duplicate submission while preserving navigation and entered data |
| Empty | Illustration + one sentence + primary action |
| Errors | Friendly message + retry + support path; branded 403/404/419/429/500/maintenance pages in AR/EN |
| Locked content | Lock icon + reason + unlock date/condition (ED-005 reason codes) |
| Upload | Drag/drop + browse, type/size hints, progress, retry, preview |
| Search | Debounced 300 ms, Arabic normalization, highlighted matches |
| Navigation (learner) | Max 5 top-level items; bottom tab bar on mobile for dashboard/my courses/notifications/profile |
| Child context | Persistent colored strip "أنت تتصفح كـ [اسم الطفل]" with exit |
| AI | Only `ai-assistant-panel` pattern (AI spec §15); never chat bubbles elsewhere |

## 4. Core journeys (step specs + UX acceptance)

Targets are maximums for a returning user on mobile unless stated.

### J01 Visitor → enrolled learner
1. Home → category or search → catalog (filters persist in URL)
2. Course page: price/access card visible without scrolling (mobile sticky bar)
3. "Enroll / Buy" → login/register inline (return to the same course)
4. Checkout (learner selector if parent) → pay (Paymob) or upload proof
5. Confirmed entitlement → "Start first lesson"; pending verification → status page with order reference, expected next step and return-to-course link
**Acceptance:** ≤ 6 screens from course page to first lesson for free courses, ≤ 8 for paid; no dead ends; payment pending state explains next steps.

### J02 Parent sets up children
1. Register (email verified) → "Add your child" CTA on empty parent dashboard
2. Child form: name, birth year/month, username, password (strength meter) → done
3. Child card shows username copy and enroll actions; initial password is disclosed only during setup, never retrieved later. Reset replaces it when needed.
**Acceptance:** add child ≤ 1 form, ≤ 60 seconds; child can log in immediately.

### J03 Instructor creates a course
1. Course list → New → wizard step 1 (autosave) … step 7
2. Curriculum manager: add section → add item (type picker) → upload media + PDF
3. PDF readiness indicator → approve extracted text
4. Preview as learner → submit/publish
**Acceptance:** add a video lesson with PDF in ≤ 2 minutes excluding upload; every step resumable; validation summary lists exact missing fields.

### J04 Learner studies a recorded lesson
1. Dashboard "Continue" → player resumes at last position
2. Toolbar: resources, PDF, captions, speed, AI
3. Completion → celebration micro-moment → "Next lesson" card
**Acceptance:** continue → playing in 1 tap; next lesson in 1 tap; player usable one-handed on mobile.

### J05 Learner asks the AI and escalates
1. Tap AI (video pauses, timestamp chip) → type or tap starter chip
2. Pending/validating state → validated short answer with page chip (no unvalidated text streamed to the learner) → فهمت / اشرحها أبسط / لسه مش فاهم
3. "لسه مش فاهم" → request created toast + link to thread
4. Notification when instructor replies → thread → mark understood
**Acceptance:** immediate pending feedback; target first validated answer ≤ 2.5 s p95, measured separately from loading feedback; escalation in 1 tap; thread reachable from notification in 1 tap. Unverified output stays hidden, with retry/help when validation fails.

### J06 Learner attends live
Dashboard card with countdown → join enabled in window → after session replay item appears.
**Acceptance:** join button state always explains timing; timezone shown.

### J07 Assessment
Quiz intro (time, attempts, pass mark) → questions with progress → submit confirm → result with explanation per settings → next item unlocked/blocked with reason.
**Acceptance:** timer always visible; answers autosaved; refresh does not lose answers.

### J08 Certificate
Completion → certificate card on dashboard → download PDF → share verification link.
**Acceptance:** verification page loads without login and shows only safe fields.

### J09 Manual payment (Instapay)
Choose manual transfer → instructions with amount/reference → upload proof → status "under review" with SLA → approval notification → access.
**Acceptance:** learner never wonders what to do next; status visible in payment history.

### J10 Finance settles instructors
Ledger filters → statement → create settlement batch → approve → record paid reference.
**Acceptance:** every total drills down to source rows in ≤ 2 clicks.

### J11 Instructor answers help requests
Notification → queue (filters: course, overdue) → request with question, AI answer, PDF page link, video timestamp → reply (templates) → status.
**Acceptance:** context complete without leaving the request; reply in ≤ 3 actions.

### J12 Admin approves an independent course/revision
Review queue → diff summary → preview as learner → approve/reject with reason.

## 5. AI touchpoints in UX (consistency rules)

- The AI entry point exists **only** in the recorded-lesson player toolbar.
- Wherever AI is unavailable, the button is hidden (not applicable) or muted
  with a persistent adjacent reason (quota, processing), associated through
  accessible description. A tooltip may supplement but never carry the only
  explanation. Recheck eligibility on action and explain changed access inline.
- Staff screens show readiness, usage/cost and insights according to permissions.
  Authorized help-request reviewers see only the question/answer context needed
  for that request (J11), not an unrestricted learner chat browser.
- Parent screens show help-request status only (O-016).
- The same copy deck is used everywhere: `lang/*/ai.php`.

## 6. UX acceptance in tasks

Every UI task includes in its plan a short **UI spec** (screens, states,
primary action, empty/error/loading, AR/EN copy keys) before building, and in
review: screenshots + `ux-reviewer` scores + journey step count where the task
touches a journey above.


## 7. Shared behavior acceptance (all core journeys)

- **Context and roles:** title, course/lesson and selected learner stay visible.
  Child context uses text as well as color. Switching child or role updates
  data, permissions and navigation together; unsaved work prompts before
  switching. Checkout repeats the learner, course, currency and total before
  payment. Preview mode is labelled and cannot record learner progress or orders.
- **Keyboard and focus:** all actions work without hover or drag. Curriculum
  reorder has keyboard controls. Dialogs/sheets move focus inside, contain it,
  close with Escape where safe, and restore focus to their trigger. Validation
  focuses a linked error summary; async status is announced without stealing focus.
- **Mobile:** test at 390 px and narrow 320 px reflow, with long AR/EN content,
  200% text zoom and the software keyboard open. Sticky navigation, payment CTA,
  save bars and AI composer must not cover focused fields or each other. Tables
  may scroll within a labelled region; the page must not scroll horizontally.
- **State contract:** specify loading, empty, success, validation error, service
  failure, forbidden, locked and pending states where applicable. Retain form
  data after failure, prevent duplicate payment/help submissions, and show a
  safe recovery action. Returning from a gateway is not proof of payment.
- **Copy:** reuse translation keys and terms for course, section, lesson,
  learner and child. Keep learner messages free of implementation terminology.
  Give a reason and next action for every block; show absolute date/time and
  timezone where timing changes access. Do not promise an unconfigured SLA.
- **AI context:** show current lesson and approved PDF identity/page references.
  A lesson change resets answer context; late responses from the previous
  lesson must not appear. Quota, processing, unavailable PDF, validation failure
  and instructor escalation have distinct states. No extra AI entry points.

## 8. Evidence required before UI acceptance

For each touched journey, save screenshots using Design System §7 and label
role, learner context, locale, viewport, theme and state. Include normal and
material blocked/error/pending states, not just a populated happy path. Record
keyboard/focus, mobile keyboard, permission/context-switch and payment-return
checks separately: screenshots alone cannot prove these behaviors. Scores
are a reviewer aid, never a substitute for passing those checks. Attach paths,
results and unresolved failures to the task; missing evidence stays **not
verified**. This documentation pack supplies requirements, not runtime proof.
