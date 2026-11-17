# YOUNG GO LMS --- UX FLOWS, PATTERNS & ACCEPTANCE

**Version:** 1.0 · **Date:** 16 November 2026 · **Status:** RECOMMENDED-DEFAULT (ED-023)\
Companion to `11_DESIGN_SYSTEM.md` (how it looks). This file defines **how it
works**: one interaction system for every screen, and step-level specs for
the core journeys with measurable UX acceptance.

## 1. UX principles (scored by `ux-reviewer`)

| # | Principle | Test |
|---|---|---|
| U1 | One primary action per screen | Exactly one primary button visible per view |
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

## 3. Interaction patterns (use these, nothing else)

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
| Dates | Relative for recent ("منذ ساعتين"), absolute on hover/detail; learner timezone |
| Feedback | Toast for success; inline error near field; banner for page-level issues |
| Loading | Skeletons for content; spinner on buttons; never block whole page |
| Empty | Illustration + one sentence + primary action |
| Errors | Friendly message + retry + support path; branded 403/404/419/429/500/maintenance pages in AR/EN |
| Locked content | Lock icon + reason + unlock date/condition (ED-005 reason codes) |
| Upload | Drag/drop + browse, type/size hints, progress, retry, preview |
| Search | Debounced 300 ms, Arabic normalization, highlighted matches |
| Navigation (learner) | Max 5 top-level items; bottom tab bar on mobile for dashboard/my courses/notifications/profile |
| Child context | Persistent colored strip "أنت تتصفح كـ [اسم الطفل]" with exit |
| AI | Only `ai-assistant-panel` pattern (AI spec §15); never chat bubbles elsewhere |

### 3.1 Shared Filament table workspace (D-026)

Extend `App\Filament\Shared\Pages\WorkspaceListRecords` for staff record lists. The resource remains the owner of its native `Table`, columns, filters, and row/header actions; the list page keeps native tabs and render hooks. Filament retains search, sorting, pagination, and authorized exports. Do not render or copy table markup into a sidebar. The base adds an optional summary strip and optional Filament schema sidebar.

Override `getWorkspaceSummary(): array` with `label`, `value`, and optional `icon` entries. Override `getWorkspaceSidebar(): array` with native schema components such as `Section` and `View`. Return `[]` when no sidebar content is available; the table then uses the full workspace width. Use only real values from the resource's authorized query, and gate each summary/sidebar data source with the matching resource or ability check. Never infer a pending status or expose data hidden from the current role.

At small widths the summary remains above the workspace grid, the native table comes first, and sidebar cards stack after it; keep horizontal scrolling within the table. Use semantic tokens so the approved calm light direction, dark theme, and RTL/LTR layout remain consistent. The implementation example is in [TABLE_WORKSPACE.md](design-system/TABLE_WORKSPACE.md).

## 4. Core journeys (step specs + UX acceptance)

Targets are maximums for a returning user on mobile unless stated.

### J01 Visitor → enrolled learner
1. Home → category or search → catalog (filters persist in URL)
2. Course page: price/access card visible without scrolling (mobile sticky bar)
3. "Enroll / Buy" → login/register inline (return to the same course)
4. Checkout (learner selector if parent) → pay (Paymob) or upload proof
5. Success page → "Start first lesson"
**Acceptance:** ≤ 6 screens from course page to first lesson for free courses, ≤ 8 for paid; no dead ends; payment pending state explains next steps.

### J02 Parent sets up children
1. Register (email verified) → "Add your child" CTA on empty parent dashboard
2. Child form: name, birth year/month, username, password (strength meter) → done
3. Child card shows "login details" copy action and "enroll" action
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
2. Streamed short answer with page chip → فهمت / اشرحها أبسط / لسه مش فاهم
3. "لسه مش فاهم" → request created toast + link to thread
4. Notification when instructor replies → thread → mark understood
**Acceptance:** first token ≤ 2.5 s p95; escalation in 1 tap; thread reachable from notification in 1 tap.

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
  with a tooltip reason (quota, processing) — never an error after click.
- Staff screens show AI **status**, never AI conversations: lesson readiness
  indicator, usage/cost, insights, help requests.
- Parent screens show help-request status only (O-016).
- The same copy deck is used everywhere: `lang/*/ai.php`.

## 6. UX acceptance in tasks

Every UI task includes in its plan a short **UI spec** (screens, states,
primary action, empty/error/loading, AR/EN copy keys) before building, and in
review: screenshots + `ux-reviewer` scores + journey step count where the task
touches a journey above.


### D-026 owner clarification: top-level staff lists

The approved reference deliberately has no breadcrumb above the top-level list title. D-026 supersedes the generic breadcrumb convention for WorkspaceListRecords. Keep the descriptive title and selected navigation item; do not add a breadcrumb that diverges from the reference. Nested/detail flows retain their applicable navigation requirements.
