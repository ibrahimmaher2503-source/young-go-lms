# Shared component contract

These IDs describe reusable visual patterns, not permission to add parallel component engines. The approved reference and current semantic CSS tokens govern appearance. Inspect docs/REUSE_REGISTRY.md before creating code. Native Filament fields/actions/tables own staff behavior; public/learner views reuse existing x-ui primitives.

| ID | Shared pattern | Reuse / implementation boundary |
|---|---|---|
| C01 | Brand and account topbar | Existing resources/views/filament/shared/brand.blade.php, staff locale switcher and WorkspaceAvatarProvider; learning/public navigation remains a dedicated shell. |
| C02 | Staff sidebar | Existing StaffPanelsServiceProvider + native Filament navigation; same widths/icons/selected tint, permission-scoped destinations. |
| C03 | Public / learner / parent navigation | Design pattern; learner/public layouts are planned. Same brand, controls and tokens; never copy staff module navigation into learning pages. |
| C04 | Page title, subtitle, primary action | Existing native Page header; one clear primary action, quiet secondary controls. |
| C05 | Summary strip | Existing filament.shared.summary-strip with actual authorized values; no invented KPIs. |
| C06 | Surface card / section | Existing native Section or x-ui.card. One token layer for radius, border, shadow and padding. |
| C07 | Data table | Existing native Filament Table inside WorkspaceListRecords; resource owns columns/query/actions. |
| C08 | Search, tabs and filters | Existing native table search/tabs/filters, scoped toolbar hooks. No copied search/filter engine. |
| C09 | Pagination | Native paginator state and Filament item controls; real totals/page boundaries and logical arrows. Generic x-ui.pagination remains for ordinary links. |
| C10 | Identity, badges and activity row | Existing x-ui.avatar/badge, StatusBadgeColumn, scoped role/activity views. Status meaning comes from domain enums. |
| C11 | Fields and form actions | Existing native schema fields or x-ui.form-field/input/select/textarea/checkbox/file-upload. Preserve server validation, focused/error states and bilingual copy. |
| C12 | Course card | Learning course-card remains planned in the registry. Compose the existing card, image, badge, price and progress primitives; use grid or compact arrangement, not separate course logic. |
| C13 | Progress | Existing x-ui.progress-bar/progress-ring and tokens; learning completion/query rules are downstream. Render actual numeric progress, never a fixed mockup width. |
| C14 | Curriculum section / item | Planned curriculum pattern; one ordered curriculum_items source. Same row in builder, preview and player, with mode-specific actions. |
| C15 | PDF attachment and readiness | Reuse existing file/upload/chip patterns and AI readiness tokens. PDF ingestion/eligibility remains planned; missing/processing/review/failure/ready are not interchangeable. |
| C16 | Recorded player and resource toolbar | Planned learning player/adapters; same source for desktop/mobile. No AI on live, unauthorized or ineligible lesson. |
| C17 | Lesson assistant | Planned contextual assistant; recorded eligible adult scenario in these images. Timestamp, current-PDF page citation, brief clarification and feedback; no global chatbot. |
| C18 | Lesson help request | Planned scoped queue/thread; instructor sees assigned context. Parent variant is status only, not conversation history. |
| C19 | Order, proof, ledger and settlement panels | Money/PriceFormatter exist; business workflows remain governed by commerce tasks. Share card/field/table primitives; pending proof never implies paid entitlement. |
| C20 | Quiz question / timer / navigation | Planned quiz behavior; reuse x-ui.radio/checkbox/button/progress primitives. No answer key/AI during an active graded attempt. |
| C21 | Save/resume wizard | Native wizard / x-ui.stepper available; seven course steps and persistence are planned course behavior, not implemented by this image. |
| C22 | Child context | Planned child-context-strip; verified link and permitted child scope required. No silently switching identities. |
| C23 | Feedback states | Existing alert/toast/skeleton/empty-state/modal/drawer/button primitives; provide loading, empty, validation, error/retry, success, disabled and focus. |
| C24 | Mobile navigation and sheet | Reuse logical spacing, x-ui.drawer/modal and the relevant feature's state. Four-item learner bottom nav; assistant sheet shares C17, not another AI engine. |

## Rules shared by all pages

- Same palette, Arabic/Latin font roles, border/radius/shadow, icon family, input/button size, spacing rhythm and semantic state vocabulary.
- Staff uses native panel composition. Public/student/parent screens share primitives with dedicated information hierarchy; do not force every page into an admin table.
- Minimum interactive targets44px; keyboard/focus/accessible labels; contain wide table scroll and expose a scroll cue. Mirror logical layout in English without reversing emails, IDs or numbers.
- Current light reference does not remove dark-mode requirements. Use semantic tokens; raster swatch values are not a competing palette.
- No new wrapper, strategy, repository or framework solely because a visual group has an ID here. These IDs are a reference vocabulary; create code only when a real task needs it.
- Reuse authorization/domain actions and the future entitlement service; never implement business rules in a visual component.

See manifest.json for each screen's component IDs, and PAGE_MAP.md for PRD traceability. An existing primitive does not mean a depicted feature is implemented.
