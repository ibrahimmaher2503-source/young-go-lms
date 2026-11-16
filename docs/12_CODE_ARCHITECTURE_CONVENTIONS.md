# YOUNG GO LMS --- CODE ARCHITECTURE, REUSE & CONVENTIONS

**Document ID:** YG-CODE-001 · **Version:** 1.0 · **Date:** 16 November 2026\
**Status:** RECOMMENDED (R-026, R-027, R-024). Binding for all agents once
adopted in T-P0-03. Versions of Laravel/Filament/packages are confirmed in
T-P0-02 (O-004).

------------------------------------------------------------------------

## 1. Goals

- Business logic lives in **one** place and is reused by every entry point
  (web controllers, Livewire, Filament, API, jobs, console, migration).
- Modules are clear and testable; boundaries are enforced by tests, not
  memory.
- Shared UI and shared backend building blocks are found before they are
  rebuilt (`docs/REUSE_REGISTRY.md`).
- Small, consistent files that a lighter-model subagent can extend safely.

## 2. Stack (to confirm in T-P0-02)

| Concern | Choice |
|---|---|
| Framework | Laravel/PHP candidates per R-001; pin a supported pair after T-P0-02 |
| Admin/instructor/finance panels | Filament 5 (SKIT themed) |
| Student/public UI | Blade + Livewire + Alpine.js + Tailwind CSS v4 |
| DB | MySQL 8.4 or PostgreSQL 16+ (O-004) |
| Queue/cache | Redis + Horizon |
| Auth | Laravel session auth (web) + Sanctum (API/mobile-ready) |
| Permissions | spatie/laravel-permission (+ Filament Shield or custom) |
| Audit | spatie/laravel-activitylog |
| Translatable fields | spatie/laravel-translatable (JSON columns) |
| Files/media | Laravel filesystem disks (private by default) + spatie/laravel-medialibrary for model attachments |
| Money | brick/money (integer minor units + currency) |
| PDF (certificates, invoices) | spatie/laravel-pdf (headless Chromium) for correct Arabic shaping |
| Sitemap | spatie/laravel-sitemap |
| Feature flags | laravel/pennant (ED-015) |
| Typed settings | spatie/laravel-settings (ED-015) |
| Phone numbers | propaganistas/laravel-phone (libphonenumber) (ED-019) |
| 2FA | Fortify TOTP or Filament multi-factor (ED-016) |
| Rich text | Filament rich editor (Tiptap JSON) + server-side renderer/sanitizer (ED-003, ED-031) |
| Monitoring | Horizon, Pulse, error reporter adapter (ED-021) |
| Tests | Pest (unit, feature, arch) + Pest browser testing (or Laravel Dusk) |
| Quality | Pint, Larastan (level ≥ 6, raise to 8 by P4), composer audit |

Every package is checked for compatibility with the chosen Laravel/Filament
versions in T-P0-02; incompatible choices are replaced and recorded in the
Decision Register.

## 3. Directory layout

```text
app/
├── Domain/
│   ├── Shared/            # cross-domain primitives (Money, Locale, ArabicNormalizer, HasSearchText, Enums base, Results)
│   ├── Identity/          # users, profiles, auth flows, sessions/devices
│   ├── Guardians/
│   ├── Catalog/           # categories, discovery queries, search
│   ├── Cms/
│   ├── Courses/           # courses, ownership, revisions, cohorts
│   ├── Curriculum/        # sections, lessons, ordering
│   ├── Media/             # media assets, tiers, providers, documents
│   ├── Learning/          # player, progress, completion engine
│   ├── Live/
│   ├── Assessments/       # quizzes, questions, attempts
│   ├── Assignments/
│   ├── Certificates/
│   ├── Entitlements/      # THE access service
│   ├── Commerce/          # orders, payments, coupons, refunds, gateways
│   ├── Subscriptions/     # plans, periods, renewal
│   ├── Bundles/
│   ├── InstructorFinance/ # commission rules, ledger, settlements
│   ├── Instructors/       # applications, agreements, payout profiles
│   ├── Engagement/        # wishlist, reviews
│   ├── HelpRequests/
│   ├── AI/
│   ├── Notifications/
│   ├── Privacy/           # consents, data requests, retention
│   ├── Reporting/
│   ├── Audit/
│   ├── Settings/
│   └── Migration/
├── Filament/
│   ├── Admin/             # panel: resources, pages, widgets
│   ├── Instructor/
│   ├── Finance/
│   └── Shared/            # reusable columns, filters, actions, form components, concerns
├── Http/
│   ├── Controllers/{Public,Student,Parent,Api/V1,Webhooks}/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/         # API resources
├── Livewire/{Public,Student,Parent,Checkout,Player}/
├── Console/Commands/
└── Providers/
resources/
├── css/{tokens.css,app.css,filament-theme.css}
├── js/
├── views/
│   ├── components/ui/        # design-system primitives
│   ├── components/learning/  # learning components
│   ├── layouts/{public,student,parent,auth}.blade.php
│   └── pages/...
lang/{ar,en}/
tests/{Unit,Feature,Arch,Browser}/
docs/  agents/
```

### 3.1 Inside a domain module

```text
app/Domain/Commerce/
├── Actions/          # one public method `handle()`, single responsibility (CreateOrder, ApplyCoupon, ...)
├── Data/             # immutable DTOs (input/output)
├── Enums/            # OrderStatus, PaymentStatus (with label() via lang)
├── Events/  Listeners/
├── Exceptions/
├── Contracts/        # PaymentGateway
├── Gateways/         # PaymobGateway, ManualTransferGateway
├── Models/           # Order, OrderItem, Payment (relations, casts, scopes only)
├── Policies/
├── Queries/          # read models / complex queries (OrdersForFinanceQuery)
└── Support/          # internal helpers (not used outside module)
```

Create a folder only when the module needs it.

## 4. Layer rules (enforced by arch tests)

| Rule | Check |
|---|---|
| Business logic only in `Domain/*/Actions` (and domain services called by them) | Controllers, Livewire, Filament resources call Actions; arch test forbids `DB::` and `Model::create` in `Http`, `Livewire`, `Filament` |
| `Domain` never depends on `Http`, `Livewire`, `Filament` | arch: `app/Domain` must not use those namespaces |
| Modules talk through public Actions, Queries, Events and Contracts; never through another module's `Support/` | arch test per module |
| Models: relations, casts, scopes, accessors only; no side effects in model events except audit/search indexing traits | review + arch (no `Http`/`Mail` usage in models) |
| Every access check for learner content goes through `Entitlements\\EntitlementService` | arch: only `Entitlements` may query `entitlements` table directly |
| Use narrow contracts for payment/AI/video boundaries; use framework APIs/fakes directly for ordinary infrastructure (ED-033) | arch: no `Http::` calls outside `Gateways/`, `Providers/`, `Clients/` |
| `declare(strict_types=1);` in all PHP files | Pint/arch |
| No `env()` outside `config/` | arch |
| Final classes for Actions, DTOs, Gateways | arch |
| Money never as float | Larastan rule/review: `brick/money` or int minor units |
| Timestamps stored UTC; display via helper with user/market timezone | review |

## 5. Actions pattern (core reuse mechanism)

The public mutation Action validates input, resolves a trusted actor/context,
authorizes the operation and checks current state. Entry-point policies provide
early rejection but are not the only protection: jobs, CLI and imports call the
same boundary. System-only paths are documented and cannot be selected by input.

`GrantEntitlement` contract (ED-034/035):

1. Validate source ownership, learner/course membership and approved grant terms.
2. Within the caller's local transaction, create the grant under unique
   `(source_type, source_ref, learner_id, course_id)`.
3. An identical replay returns the existing grant without another activation
   audit/event; conflicting terms reject with a conflict, never overwrite.
4. Only an actual transition records its audit and durable pending delivery in
   the same transaction. Dispatch after commit; recover pending delivery after
   worker/process crashes. Consumers dedupe by business source.
5. Treat revoke/expire/reactivate as explicit state transitions; replaying a
   purchase cannot resurrect a refunded or revoked grant.

This is a specification, not executable sample code. `firstOrCreate()` alone
neither makes side effects idempotent nor guarantees post-commit delivery. Prove
concurrent duplicate, conflicting replay, rollback and worker-restart cases.

### 5.1 Practical SOLID (ED-033)
- **Single responsibility:** one Action owns a business operation; policies own
  access, Queries own reads, adapters own provider transport. Do not split a
  short cohesive operation merely to increase class count.
- **Open/closed:** extend a demonstrated provider/type boundary; a small explicit
  branch for three known sellables is acceptable. No speculative plugin system.
- **Substitution:** all implementations honor the same outcomes, errors,
  idempotency and timeout contract; a fake does not establish provider support.
- **Interface segregation:** completion, OCR and embedding are separate needed
  capabilities; do not force unrelated methods onto every driver.
- **Dependency inversion:** inject narrow payment/AI/video seams into workflows.
  Use Laravel storage/mail APIs and built-in fakes without custom wrappers unless
  an actual requirement justifies one.

Do not require repositories over Eloquent, a DTO for every call, generic base
Actions, event sourcing, or empty module folders. Introduce typed input/results
only where they make a real boundary clearer. Registration means reuse intent,
not permission to build every planned helper in advance.

## 6. Shared building blocks (create when needed, reuse thereafter)

| Block | Location | Used by |
|---|---|---|
| `ArabicNormalizer` + `HasSearchText` trait | `Domain/Shared/Search` | courses, categories, instructors, blog, quiz candidate matching; never exact AI cache identity |
| `Money` value + `PriceFormatter` | `Domain/Shared/Money` | catalog, checkout, finance, reports |
| `Market` / `MarketResolver` (EG active, SA disabled) | `Domain/Shared/Markets` | pricing, checkout, invoices |
| `LocalizedDate` helper | `Domain/Shared/Time` | all views, exports |
| `AuditLogger` | `Domain/Audit` | all sensitive Actions |
| `HasTranslations` usage + `TranslatableTabs` Filament component | `Filament/Shared/Forms` | every bilingual form |
| `StatusBadgeColumn`, `MoneyColumn`, `LocalizedDateColumn`, `ProtectionTierColumn` | `Filament/Shared/Tables` | all resources |
| `ApproveRejectAction` (with reason), `ExportCsvAction` (permission + filters) | `Filament/Shared/Actions` | approvals, finance, reports |
| `PrivateFileController` + signed URL service | `Domain/Media` | PDFs, resources, submissions, proofs |
| Notification base (`LocalizedNotification`, dedupe key) | `Domain/Notifications` | every notification |
| UI primitives `x-ui.*` | `resources/views/components/ui` | all student/public screens |

Before creating any new class/component, the agent searches
`docs/REUSE_REGISTRY.md` and the codebase (`rg`) for an existing one.

## 7. Naming

| Thing | Convention | Example |
|---|---|---|
| Action | Verb + noun | `SubmitCourseRevision` |
| DTO | Noun + `Data` | `CreateOrderData` |
| Query | Purpose + `Query` | `EntitledCurriculumQuery` |
| Event | Past tense | `PaymentVerified` |
| Enum | Singular noun | `LessonType::RecordedVideo` |
| Livewire | Feature noun | `Player\LessonPlayer` |
| Blade component | kebab | `<x-learning.course-card>` |
| Table | plural snake | `cohort_members` |
| Migration | timestamp + verb | `create_cohorts_table` |
| Permission | `module.action` | `course.publish`, `payment.approve_manual` |
| Translation keys | `module.area.key` | `commerce.checkout.pay_now` |
| Test | describes behavior | `it('does not grant access twice for duplicate paymob webhooks')` |
| Branch | `feat/T-P8-03-paymob-webhook` | |
| Commit | Conventional + task ID | `feat(commerce): verify paymob webhook [T-P8-03]` |

## 8. Arabic and localization rules

- `ArabicNormalizer::normalize()` is the only normalization implementation:
  أ/إ/آ/ٱ → ا, ة → ه, ى → ي, ؤ → و, ئ → ي, remove diacritics (U+064B–U+0652,
  U+0670) and tatweel (U+0640), NFKC, collapse whitespace, lowercase Latin.
- Translatable model fields use JSON columns (`title->ar`, `title->en`).
- UI strings only via `__()`; CI fails on missing keys in either locale
  (translation check command).
- Validation messages localized in both locales.

## 9. Testing conventions

- **Unit:** pure logic (normalizer, money, commission calculator, completion
  rules, quote verification).
- **Feature:** every Action and HTTP/Livewire/Filament entry point;
  authorization matrix tests (role × resource) using datasets.
- **Invariant suite (`tests/Feature/Invariants/`):** one test file per PRD
  §23 invariant; must never be deleted or weakened without a Decision
  Register entry.
- **Arch (`tests/Arch/`):** rules in §4 and §10.
- **Browser:** key journeys + screenshots per design system §7.
- Factories for every model with realistic Arabic/English data; seeders for
  demo: 3 categories, 6 courses (recorded/live/hybrid), 1 parent with 2
  children, instructors academy/independent.
- External services: always fakes (`FakeAiProvider`, `FakePaymobGateway`,
  `Http::fake`). No real network in tests.
- Coverage target for `Domain/Entitlements`, `Domain/Commerce`,
  `Domain/InstructorFinance`, `Domain/AI`: ≥ 90% lines.

## 10. Frontend rules

- Only design tokens (Tailwind theme from `tokens.css`); no arbitrary hex
  values in classes (`bg-[#...]` forbidden) except inside `tokens.css`.
- Only logical direction utilities (see design system §3). CI script greps
  views for forbidden physical classes: `\b(ml|mr|pl|pr|left|right)-`,
  `text-left`, `text-right`, `rounded-l`, `rounded-r`, `border-l`, `border-r`.
- Every page uses a layout from `layouts/` and primitives from
  `components/ui`. New primitives require registry entry.
- Livewire components: thin; call Actions/Queries; no queries in Blade.
- Images: responsive `srcset`, lazy loading, fixed aspect ratio.
- Alpine for small interactions only; no second SPA framework.

## 11. Security conventions

- Policies for every model; `authorize()` in every controller/Livewire
  action; Filament resources use `can*` methods.
- Private files only through `PrivateFileController` with object/purpose policy and short-lived signed URLs; entitlement alone does
  not authorize another learner's submission, payment proof or privacy export.
- Webhooks: signature verification middleware per gateway; raw payload
  stored sanitized; processing idempotent by event id.
- Rate limiters named per use (`login`, `register`, `ai-assistant`,
  `contact`, `webhooks`).
- Secrets only in `.env`/secret manager; `.env.example` lists keys without
  values.
- Logging: never log passwords, tokens, full card data, full AI messages
  or full ID documents.

## 11A. Requirement tags in tests (ED-020, ED-028)

```php
it('lets a parent create a child account without email', function () { ... })
    ->group('T-P1-05', 'YG-GAP-FAM-001');
```
- Every acceptance criterion's test carries the task tag and the PRD/decision IDs it proves.
- `php artisan app:req-coverage` parses PRD IDs, D-*, ED-* and task acceptance items and reports any in-scope item without a tagged test or recorded evidence (Gate G10).

## 11B. Access, progress and curriculum rules
- Ordering only from `curriculum_items` (ED-002). Never sort lessons by their own column.
- All "can the learner open this?" decisions use `EntitlementService::canAccessItem()` and render its `reason_code` (ED-005).
- Progress writes go through `Learning\Actions\RecordProgress` only; preview/view-as never writes (ED-024, ED-027).

## 12. Definition of Done (per task)

1. Acceptance criteria of the task met with real persisted data.
2. Authorization enforced server-side; policy tests added.
3. Tests added/updated; CI gates in `agents/VERIFICATION_GATES.md` green.
4. No duplicated logic: shared pieces reused or registered.
5. AR/EN strings added; RTL/LTR screenshots for UI tasks.
6. Docs updated: `REUSE_REGISTRY.md`, `PROGRESS.md`, `CHANGELOG.md`, and
   decision register if a choice was made.
7. Orchestrator review passed (`agents/REVIEW_PROTOCOL.md`).
8. Acceptance tests written first and tagged (G10 green); verification wave PASS (prd-auditor, qa-verifier, ux-reviewer for UI, reviewer for high risk).
9. The task's `ai:` line honored.
