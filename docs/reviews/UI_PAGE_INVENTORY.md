# UI page and shared-component inventory

Inventory date: 2026-11-17. Scope: owner UI-only request, step 0; D-026 and ED-023. AI: none. This is source inspection, not a visual acceptance verdict. Task status remains exclusively in `agents/PROGRESS.md`.

Sources: `php artisan route:list --method=GET --except-vendor --json` (successful), `routes/*.php`, `StaffPanelsServiceProvider`, resource `getPages()`, `tests/Browser/*.php`, and the final reference-pack manifest/page map. No application, backend, route, configuration, test or fixture file was changed for this inventory.

## Matrix notation and boundaries

- **L4**: existing screenshot test declares AR/EN × 390×844/1440×900 in its default/light theme. **M8**: AR/EN × both sizes × light/dark. These describe test code, not a successful fresh run.
- All ordinary product pages support dark through `resources/js/app.js`; missing dark coverage therefore remains a gap. Staff themes use native Filament theme persistence.
- References below are filenames under `docs/design-system/reference-pack-2026-11-17/`. “No dedicated reference” means reuse the stated shell/primitives and record the limitation; it does not approve a new visual language.
- Routes with `{panel}` below expand only to the explicitly listed panels; permissions still determine whether a particular record/user may enter them.
- A route, an empty resource list and a populated state are separate evidence requirements. Existing broad staff captures populate staff users/roles/audit data, but leave several other resources empty.

## Public, learner, family and identity pages

| Page / exact route pattern | View and shared composition | Nearest final reference | Existing browser coverage | Missing evidence to close requested matrix |
|---|---|---|---|---|
| Homepage `/` | `cms/home.blade.php`; layouts.base, ui.card/button | `15-public-home.png` | CmsPagesBrowserTest, L4 | Dark M4; all real visible block variants/empty configuration |
| Catalog `/courses` | `catalog/index.blade.php`, `catalog/filters.blade.php`; learning.course-card, drawer, select, pagination | `05-public-course-catalog-v2.png` | PublicCatalogBrowserTest, L4 results/empty; mobile filters | Dark M4; filter combinations and active/reset states |
| CMS `/pages/{slug}` | `cms/page.blade.php`; layouts.base | No dedicated CMS article reference; use 15/18 primitives | CmsPagesBrowserTest, L4 published page | Dark M4; long mixed-language article/content states |
| Signed CMS preview `/admin/cms-preview/{page}` | `cms/page.blade.php` | No dedicated preview reference; same CMS primitives | Feature test CmsPagesTest only | All eight captures and preview indicator review |
| Learner dashboard `/my` | `learner/dashboard.blade.php`; layouts.learner, learner.course-row/thumb, progress, live schedule | `04-learner-dashboard.png`, `16-mobile-learner-dashboard.png` | LearnerDashboardBrowserTest L4; empty AR390/AR1440/EN1440; LiveSessionBrowserTest L4 schedule | Dark M4; empty EN390; live schedule dark |
| My courses `/my/courses` | `learner/courses.blade.php`; layouts.learner, learner.course-row | 04/16 related pattern; no dedicated directory reference | LearnerDashboardBrowserTest, L4 populated | Dark M4; empty and other filter states |
| Player `/learn/{course}/{item}` | `learn/show.blade.php`; layouts.learner, curriculum-list, lesson-media, tabs/drawer, progress | `07-recorded-player-ai-v2.png`, `17-mobile-lesson-assistant.png` (layout only; do not add AI) | CoursePlayerBrowserTest L4 player/drawer; PDF/attachments M8 plus reference-size captures; RichLessonBrowserTest L4; LockedLessonBrowserTest L4; WatermarkBrowserTest L4 | Dark for plain/rich/locked/watermark; audio/external source and empty/material failure states; actual fullscreen evidence |
| Live item `/learn/{course}/{item}` (same route) | `learn/show.blade.php`; learning.live-session-card, curriculum-list | No dedicated live-card reference; same 07/18 shell/primitives, AI absent | LiveSessionBrowserTest, L4 scheduled-not-yet-joinable | Dark M4; joinable/ended/cancelled/replay states if already represented by backend |
| Staff learner preview `/preview/{course}/{item}` | `learn/show.blade.php`; same player with `data-preview-banner` / draft states | No dedicated preview reference; 07/18 shell/primitives | Feature tests InstructorInsightsTest and JoinLinkTest only | All eight captures; draft, locked, live and empty-content preview states |
| Parent dashboard `/parent` | `parent/dashboard.blade.php`; layouts.learner, course rows, progress | `08-parent-dashboard-v2.png` | ParentDashboardBrowserTest, L4 with children/no children | Dark M4; child without courses covered inside populated fixture but needs visual review |
| Family management `/family` | `pages/family.blade.php`; layouts.base, family.child-switcher/password-reset, forms/modal | No dedicated management reference; 08 child-context + 18 forms | FamilyFlowsTest L4; child-context AR/EN390; TwoFactorScreensTest L4 reset dialog | Dark M4; context desktop; pending/unverified/empty variants |
| Learner linking `/link` | `pages/link.blade.php`; layouts.base, ajax-form | No dedicated linking reference; 18 forms | FamilyFlowsTest, L4 and functional code/confirm flow | Dark M4; pending/issued/expired/error capture states |
| Sign-in `/login` | `pages/login.blade.php`; layouts.base(auth), ui forms | No dedicated public-auth reference; 18 forms | FamilyFlowsTest L4 + AR/EN dark390 | Dark1440; server error/expired-session states |
| Registration `/register` | `pages/register.blade.php`; layouts.base(auth), form-field, radio/checkbox/strength-meter | No dedicated registration reference; 18 forms | FamilyFlowsTest L4 + AR/EN dark390; invalid form journey | Dark1440; unavailable terms/privacy, parent selection and validation captures |
| Forgot password `/forgot-password` | `pages/forgot-password.blade.php`; layouts.base(auth), ajax-form | No dedicated reference; 18 forms | FamilyFlowsTest, L4 | Dark M4; success/error states |
| Reset password `/reset-password/{token}` | `pages/reset-password.blade.php`; layouts.base(auth), strength-meter | No dedicated reference; 18 forms | FamilyFlowsTest, L4 (`example-token`) | Dark M4; token expiry/result validation states |
| Verification notice `/email/verify` | `pages/verify-email.blade.php`; layouts.base, ajax-form | No dedicated reference; 18 feedback | FamilyFlowsTest, L4 unverified learner | Dark M4; resend/success/throttled states |
| Profile `/profile` | `pages/profile.blade.php`; layouts.base, form fields/uploads and account forms | No dedicated reference; 18 forms | FamilyFlowsTest, L4 learner | Dark M4; parent variant, changed/invalid/disabled fields |
| 2FA setup `/security/two-factor` | `pages/two-factor-setup.blade.php`; layouts.base, forms/QR/recovery list | No dedicated reference; 18 forms/feedback | TwoFactorScreensTest, L4 setup/enabled; functional enrollment | Dark M4; recovery result screenshot matrix |
| 2FA challenge `/two-factor-challenge` | `pages/two-factor-challenge.blade.php`; layouts.base, input/forms | No dedicated reference; 18 forms | TwoFactorScreensTest L4 and code challenge journey | Dark M4; recovery-code/error states |
| Errors 403/404/419/429/500/503 | `errors/{status}.blade.php`; ui.empty-state, layouts.base(minimal) | No dedicated reference; 18 feedback primitives | ErrorPagesBrowserTest L4 for 403/419/429/500/503; FamilyFlowsTest L4 fallback404 | Dark M4 per status; retry/back focus review |

## Staff panels and native resource pages

Shared shell/theme: native Filament + `resources/css/filament-theme.css`, `filament/shared/brand`, `filament/staff/locale-switcher`, `WorkspaceListRecords`, summary strip, table-scroll hint and pagination. Preserve native schemas/actions/authorization; do not rebuild tables.

| Page / exact route pattern | Composition / source | Nearest final reference | Existing browser coverage | Missing evidence |
|---|---|---|---|---|
| `/admin/login`, `/instructor/login`, `/finance/login` | WorkspaceLogin; native form and shared auth classes | No dedicated login reference; 18 primitives/00 visual system | WorkspaceSurfacesBrowserTest M8 for each panel | Error/loading/focus screenshots and actual reference comparison |
| `/admin`, `/instructor`, `/finance` | WorkspaceDashboard; dashboard-intro/links + scoped summaries | `01-admin-overview-v2.png`; no dedicated instructor/finance landing reference | WorkspaceSurfacesBrowserTest M8 for each | Populated authorized summaries where implemented; no invented finance data |
| `/admin/staff-users` | ListStaffUsers; person cell, role-breakdown, recent-activity, users-toolbar | `00-owner-approved-users.png` | StaffPanelsTest M8 plus AR light1568 geometry and populated users | All additional table/filter/action/empty states require comparison |
| `/admin/role-permissions` | ListRolePermissions; native table + permission editor | No dedicated roles reference; 00/18 primitives | StaffPanelsTest M8 plus permission modal, keyboard trap/focus return | Changed/validation/save feedback screenshots |
| `/admin/audit/audit-logs` | ListAuditLogs / WorkspaceListRecords | No dedicated audit reference; 00/02 table primitives | StaffPanelsTest M8, sample audit entry | Filters/detail/empty state captures |
| `/admin/categories` | ListCategories / WorkspaceListRecords | No dedicated categories reference; 02/19 table primitives | CategoriesBrowserTest M8; StaffPanelsTest M8 empty; CMS empty-table keyboard check | Populated create/edit modal states |
| `/admin/cms-pages` | ListCmsPages / WorkspaceListRecords; native modals | No dedicated CMS editor reference; 02/13/18 | CmsPagesBrowserTest M8 populated; StaffPanelsTest M8 | Editor modal/publish/validation states |
| `/admin/homepage-blocks` | ListHomepageBlocks / WorkspaceListRecords; native modals | No dedicated block editor reference; 02/13/18 | CmsPagesBrowserTest M8 populated; StaffPanelsTest M8 | Every block editor variant and validation |
| `/{panel}/courses` (`admin`, `instructor`) | ListCourses / WorkspaceListRecords | `02-course-directory.png`, `19-course-directory-en-v2.png` | CoursesBrowserTest M8 in both panels; StaffPanelsTest admin M8 empty | Filters/actions/empty instructor list visual review |
| `/{panel}/courses/{record}` (`admin`, `instructor`) | ViewCourse; native infolist | No dedicated staff-detail reference; 13/18 sections | CoursesBrowserTest M8 in both panels | Long description and all actions states |
| `/{panel}/courses/create` (`admin`, `instructor`) | CreateCourse; native two-step wizard + wizard-progress | `13-course-wizard-v2.png` (implemented subset only) | No direct browser creation capture | All eight per panel; only Basic/Information steps currently exist; do not add reference-only steps |
| `/{panel}/courses/{record}/edit` (`admin`, `instructor`) | EditCourse; CourseWizardSteps, native fields/progress | `13-course-wizard-v2.png` | CourseWizardBrowserTest L4 admin Basic/Information | Dark M4; instructor M8; field validation and draft feedback |
| `/{panel}/courses/{record}/curriculum` (`admin`, `instructor`) | `filament/courses/manage-curriculum.blade.php`, curriculum-readiness; native lesson/live modals | `03-curriculum-builder.png` | CurriculumBrowserTest L4 instructor populated/lesson editor/PDF; functional reorder | Dark M4; admin M8; live editor and empty/loading/error state captures |
| `/{panel}/courses/{record}/curriculum/documents/{document}` (`admin`, `instructor`) | `filament/courses/review-curriculum-document.blade.php` | No dedicated approval reference; 03/18 PDF/field primitives | CurriculumBrowserTest L4 admin approval | Dark M4; forbidden instructor (do not fabricate access), approval result states |
| `/{panel}/courses/{record}/cohorts` (`admin`, `instructor`) | `filament/courses/cohorts.blade.php`; native forms/modals | **No dedicated cohort reference**; 00/02/18 primitives | Feature CohortsTest only | All eight per authorized panel, populated/empty/waitlist/member modal states; preserve `data-member-row` |
| `/{panel}/courses/{record}/learners` (`admin`, `instructor`) | `filament/courses/learners.blade.php`; scoped detail/action | **No dedicated learners reference**; 00/02/18 primitives | Feature InstructorInsightsTest only | All eight per authorized panel, populated/empty/detail states |
| `/{panel}/manual-grants` (`admin`, `finance`) | ListManualGrants / WorkspaceListRecords | No dedicated grants reference; 00/02/18; reference14 is payment proof, not entitlement rules | ManualGrantsBrowserTest L4 populated admin; StaffPanelsTest M8 empty admin | Populated admin dark; finance M8; grant/revoke dialogs |
| `/{panel}/library` (`admin`, `instructor`) | ListLibraryItems / WorkspaceListRecords | No dedicated library reference; 02/18 file/table primitives | LibraryBrowserTest L4 populated admin; StaffPanelsTest M8 empty admin | Populated admin dark; instructor M8; add/attach/delete dialogs |
| `/admin/learner-devices` | ListLearnerDevices / WorkspaceListRecords | No dedicated devices reference; 00/18 | StaffPanelsTest M8, empty devices | Populated devices/reset dialog and feedback |

## Developer galleries and non-page routes

| Route | Existing evidence and treatment |
|---|---|
| `/_design/components?locale={ar,en}&theme={light,dark}` | `design/components.blade.php`, UiComponentGalleryTest M8 screenshot + keyboard/modal/tab/dropdown journeys; reference `18-shared-component-library-v2.png`. Capture full page in the new evidence: existing gallery capture is viewport-only. |
| `/_design/tokens?locale={ar,en}&theme={light,dark}` | `design/tokens.blade.php`, DesignTokenPreviewTest M8 assertions but no screenshot call. Existing old token PNGs do not prove a fresh run. Add captures without modifying assertions. |
| `/home`, `/learn/{course}`, `/confirm-password`, `/verify-email/{id}/{hash}` | Redirects/action endpoints, no independent page UI. Verify destination screens and redirect behavior using existing functional tests; do not invent a screen. |
| `/files/{type}/{id}`, `/media/{asset}` | Authorized file/media responses, not HTML pages. Player/resource UI covers presentation; preserve access checks and signed URLs. |
| `{fallbackPlaceholder}` | The branded404 page above, reached through web middleware. |

No public course-detail, checkout, quiz attempt, help workspace, instructor ledger, settlements or payment-proof review page is registered currently. Their images are design references for later tasks, not scope for this UI refinement. `resources/views/welcome.blade.php` contains only an unused comment and is not a registered screen.

## Existing reusable pieces

| Layer | Files/pieces (under `resources/`) |
|---|---|
| Tokens/theme | `css/tokens.css` (local Noto Sans Arabic variable font with `font-display: swap`, semantic light/dark tokens, Tailwind mapping/global rules); `css/app.css`; `css/filament-theme.css` |
| Layouts | `views/components/layouts/base.blade.php` (public/auth/student/parent/minimal variants); `views/components/layouts/learner.blade.php` (learning/parent shell and bottom navigation) |
| UI primitives | `views/components/ui/`: accordion, ajax-form, alert, avatar, badge, bdi-text, breadcrumb, button, card, checkbox, chip, date, drawer, dropdown, empty-state, file-upload, form-field, icon, input, link, locale-switcher, modal, month-select, pagination, price, progress-bar, progress-ring, radio, rating, select, skeleton, stepper, strength-meter, switch, tabs, textarea, toast, tooltip |
| Learning | `views/components/learning/`: course-card, curriculum-list, lesson-media, live-session-card, rich-content; `views/components/learner/`: course-row, course-thumb |
| Family | `views/components/family/`: child-switcher, child-password-reset |
| Staff | `views/filament/shared/`: brand, summary-strip, table-scroll-hint, workspace-pagination, tables/person; `views/filament/staff/`: locale-switcher, users-toolbar, role-breakdown, recent-activity, dashboard-intro, dashboard-links; `views/filament/courses/`: wizard-progress, curriculum-readiness and page views named above |
| JavaScript | `js/app.js` imports player, live, ui-primitives and forms; those modules own theme application, media/heartbeat, live join, dialog/tab/dropdown/table accessibility, AJAX/error/focus/copy/password-strength behavior. Keep selectors, IDs, form names, command/popover attributes, wire bindings and `data-*` hooks unchanged. |

## Audit priorities from source inspection

These are concrete audit targets, not claims that an unseen screenshot failed.

| Page/scope | Problem or evidence gap | Root cause | Impact | Priority |
|---|---|---|---|---|
| All pages | Existing browser fixtures do not provide complete fresh M8 screenshots for all implemented pages | Test-matrix coverage | Cannot claim owner step0/G7 completion from existing PNGs | P0 |
| Preview, learners, cohorts, CMS preview | User-visible current routes lack browser screenshots entirely | Test coverage | Shared styling can regress unseen pages; preview/draft/privacy indicators matter | P0 |
| Shared layouts | Public/auth/profile/family use base while learner/player/parent dashboard use learner shell | Shared composition | Navigation, widths and hierarchy need side-by-side review, without changing destinations | P1 |
| Public and learner templates | Individual utility sizes and some arbitrary widths coexist with semantic tokens | Templates/shared sizing | Responsive rhythm and token consistency need measured audit | P1 |
| Learning states | Most rich/locked/live/watermark captures are light only | Shared components plus matrix | Dark contrast and RTL truncation remain unproven | P1 |
| Staff pages | Broad resource loop often captures empty lists while dedicated fixtures cover only selected populated resources | Fixtures | Cannot judge row density, wrapping or real scoping from empty-state capture alone | P1 |
| Course wizard | Two implemented steps; visual reference illustrates a broader future flow | Reference scope | Compare layout/fields without adding unimplemented workflow | P1 |
| All pages | Existing matrix is largely390/1440; 360/768/1024/1920 absent | Responsive test coverage | Intermediate wrapping and breakpoint behavior unproven | P1 |
| Galleries | Component screenshot is not full-page; token preview test does not save screenshots | Capture routine | Lower-page primitives/tokens lack current visual evidence | P2 |

## Minimal complete baseline capture plan

1. Before product edits, run the existing browser suite unchanged, preserving its assertions. Record run start/end, revision and failures. Copy only PNGs freshly written by that run into `docs/reviews/UI_BASELINE/`, preserving task-relative paths and hashes; do not relabel old PNGs as current evidence.
2. Keep the original task PNGs and references separate. The additional evidence-only browser runner is stored at `docs/reviews/UIRefinementMatrixTest.php` to respect the final diff allowlist. Copy it temporarily to `tests/Browser/UIRefinementMatrixTest.php` for execution, then remove only that copy. Pest browser discovery requires the configured test directory. No existing assertion, route/config/backend file is changed. Its factories/actions create isolated test-database fixtures, never development or production data.
3. Use one explicit eight-combination dataset for each HTML archetype and authorized panel variant. For ordinary pages set localStorage theme then refresh (matching current player tests); for Filament use its existing theme event/refresh sequence. Await `document.fonts.ready` and settled frames before screenshots. Keep theme in the filename even for light captures.
4. Reuse existing `browserLearner`, `browserPlayer`, curriculum and family fixtures inside their test runtime rather than seeding the development database. Current helpers live in browser test files, so avoid copying domain logic into a new capture engine.
5. Add missing existing-page scenarios: staff preview (draft/locked/live), learners and cohorts with actual fixture members, signed CMS draft preview, create-course basic form and authorized instructor/finance resource variants. Use test-generated signed URLs and identities; never fabricate credentials or broaden permissions for a screenshot.
6. Run additional width checks at360/768/1024/1920 on shared archetypes and any page that wraps differently; record exact per-page coverage. Mandatory before/after PNGs remain at390×844 and1440×900. Compare reference-size captures at their matching viewport as extra evidence, preserving the fixed owner reference.
7. After each wave repeat the same fixtures/matrix into `UI_AFTER`, then root visually inspects matched page pairs and references. Keep missing captures and visible mismatches open in the refinement report. Screenshot existence and axe success do not close G7.

### Additional matrix runner

Run serially with other Pest browser processes; they share a browser server lifecycle. Example PowerShell invocation after the existing browser suite finishes:

```powershell
Copy-Item -LiteralPath docs/reviews/UIRefinementMatrixTest.php -Destination tests/Browser/UIRefinementMatrixTest.php
$env:YG_UI_CAPTURE_PHASE = 'BASELINE' # AFTER for the identical post-change matrix
php -d opcache.enable_cli=0 -d extension=sockets vendor/bin/pest tests/Browser/UIRefinementMatrixTest.php
Remove-Item -LiteralPath tests/Browser/UIRefinementMatrixTest.php
```

The runner saves full-page PNGs and per-screen JSON (actual route, dimensions, headings, overflowing elements, all failed checks) directly under `UI_BASELINE/matrix` or `UI_AFTER/matrix`. It captures before checking axe/JS/overflow, collects every check failure and fails the group after all its screens are visited; this preserves failing baseline evidence without weakening acceptance. The browser screenshot API prefixes `tests/Browser/Screenshots`, so the runner uses a fixed `../../../docs/reviews/UI_{phase}/matrix/` path and restricts phase to `BASELINE`/`AFTER`.

The supplemental runner includes populated current hybrid curriculum, playable local fixture video, draft/locked/live previews, upcoming/joinable/cancelled/ended/error live card states, parent with/no children, identity/2FA/family/context, both authorized staff course panels including learners/cohorts/create/edit steps, populated staff library/grants variants, signed CMS preview, branded errors and full galleries. It reuses current domain Actions for fixture construction and calls no external provider. Live “error” uses the existing `join_error` session state with translated copy; it does not claim to simulate a provider outage. Replay, all modal/empty/resource states and additional responsive widths remain separate coverage work.

## Verification of this inventory

Route names/patterns were checked against the actual GET route list, not inferred solely from filenames. Panel resource registrations and all course page suffixes were checked directly. Screenshot matrix claims were checked against test datasets and screenshot calls. This file does not certify pixel matching, test success, accessibility success or product completeness; those require fresh execution and visual review by the root agent.
