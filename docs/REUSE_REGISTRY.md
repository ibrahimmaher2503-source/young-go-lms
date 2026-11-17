# Young Go LMS --- Reuse Registry

Living index of shared code. **Check here before creating anything new.**
Every task that creates a shared piece adds a row in the same commit.
The orchestrator rejects work that duplicates a registered piece.

Status: `planned` (defined, not built) · `built` · `deprecated` (with replacement).

## Backend

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| ArabicNormalizer | `app/Domain/Shared/Search/ArabicNormalizer.php` | Single Arabic/Latin normalization | built | T-P0-06 |
| HasSearchText | `app/Domain/Shared/Search/HasSearchText.php` | Fill `search_text` on save | built | T-P0-06 |
| Money / PriceFormatter | `app/Domain/Shared/Money/` | Integer-minor money + locale formatting | built | T-P0-06 |
| Market / MarketResolver | `app/Domain/Shared/Markets/` | EG active / SA disabled | built | T-P0-06 |
| LocalizedDate | `app/Domain/Shared/Time/` | UTC storage, local display | built | T-P0-06 |
| AgeBandResolver | `app/Domain/Catalog/AgeBandResolver.php` | Resolve a learner's configured age band from birth year and month | built | T-P1-07 |
| AgeRange / AgeRangeRule | `app/Domain/Catalog/AgeRange.php`, `app/Domain/Catalog/Rules/AgeRangeRule.php` | Inclusive, nullable age bounds and validation for course and band ranges | built | T-P1-07 |
| RBAC catalog and BasePolicy | `config/permissions.php`, `app/Policies/BasePolicy.php` | Initial role defaults and reusable policy permission check | built | T-P1-03 |
| AuditLogger | `app/Domain/Audit/AuditLogger.php` | Audit sensitive actions | built | T-P1-04 |
| EntitlementService | `app/Domain/Entitlements/EntitlementService.php` | Single access decision | planned | T-P4-01 |
| PrivateFileController + SignedFileUrl | `app/Domain/Media/` | Authorized private downloads | planned | T-P3-08 |
| LocalizedNotification | `app/Domain/Notifications/` | Base notification with dedupe | planned | T-P5-04 |
| PaymentGateway (contract) | `app/Domain/Commerce/Contracts/` | Gateway abstraction | planned | T-P8-02 |
| AiProviderInterface + FakeAiProvider | `app/Domain/AI/Contracts/` | AI abstraction | planned | T-P3-10 |
| Feature flags + EnableGatedFlag | `app/Domain/Settings/` | Pennant flags with approval reference | built | T-P0-07 |
| app:check-translations / app:check-rtl | `app/Console/Commands/` | AR/EN key parity and logical direction utility enforcement | built | T-P0-03 |
| PhoneNumbers | `app/Domain/Shared/Phone/PhoneNumbers.php` | EG/SA phone validation, E.164 storage format, national display | built | T-P0-07 |
| SetLocale / UpdatePreferredLocale | `app/Http/Middleware/SetLocale.php`, `app/Domain/Identity/Actions/UpdatePreferredLocale.php` | Locale resolution and persisted preference | built | T-P1-01 |
| CreateChildAccount / GuardianAccess | `app/Domain/Identity/Actions/CreateChildAccount.php`, `app/Domain/Identity/GuardianAccess.php` | Parent-created child accounts and guardian→child access check (gate `guardian.child.access`, 404 on deny) | built | T-P1-05 |
| ActingLearner | `app/Domain/Identity/ActingLearner.php`, `app/Http/Middleware/ResolveActingLearner.php` | Single source for the learner a request acts as (parent child-context); learner-facing queries must use current() | built | T-P1-06 |
| x-layouts.base + x-ui.ajax-form + forms.js | `resources/views/components/layouts/base.blade.php`, `resources/views/components/ui/ajax-form.blade.php`, `resources/js/forms.js` | Learner/parent/auth/public page shell and progressive JSON form submit with inline field errors | built | T-P1-10 |
| x-family.child-switcher | `resources/views/components/family/child-switcher.blade.php` | Password-confirmed switch into a child context | built | T-P1-10 |
| ErrorReporter | `app/Domain/Shared/Observability/` | Local exception reporting adapter | built | T-P0-07 |
| app:doctor / app:req-coverage | `app/Console/Commands/` | Health check / requirement coverage (G10, G11) | built | T-P0-07 |
| AccessDecision + canAccessItem() | `app/Domain/Entitlements/` | Layered access with reason codes | planned | T-P4-09 |
| RecordProgress | `app/Domain/Learning/Actions/` | Only writer of progress | planned | T-P4-04 |
| HtmlSanitizer | `app/Domain/Shared/Security/` | Single sanitizer (rich text, messages) | planned | T-P3-15 |
| ImageReencoder | `app/Domain/Media/` | Strip EXIF/GPS on upload | planned | T-P3-08 |
| AssistantEligibility | `app/Domain/AI/` | AI availability decision | planned | T-P3-10 |

## Filament shared

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| TranslatableTabs | `app/Filament/Shared/Forms/TranslatableTabs.php` | AR/EN tabs for translatable fields | built | T-P1-08 |
| StatusBadgeColumn | `app/Filament/Shared/Tables/` | Enum → colored badge | built | T-P1-08 |
| MoneyColumn | `app/Filament/Shared/Tables/` | Money display | built | T-P1-08 |
| ApproveRejectAction + RecordReviewDecision | `app/Filament/Shared/Actions/ApproveRejectAction.php`, `app/Domain/Shared/Actions/RecordReviewDecision.php` | Review-queue approve/reject: reason rules, auth recheck, domain mutation + audit in one transaction | built | T-P1-08 |
| LocalizedDateColumn | `app/Filament/Shared/Tables/` | Date in user tz | built | T-P1-08 |
| ApproveRejectAction | `app/Filament/Shared/Actions/` | Approve / reject with reason + audit | planned | T-P1-08 |
| ExportCsvAction | `app/Filament/Shared/Actions/` | Permission-aware CSV export | built | T-P1-08 |
| SeoFieldsGroup | `app/Filament/Shared/Forms/` | Bilingual SEO fields | planned | T-P2-05 |
| WorkspaceListRecords | `app/Filament/Shared/Pages/WorkspaceListRecords.php` | Native ListRecords shell with optional summary strip and Section/View schema sidebar; resource keeps ownership of the table | built | T-P1-08 |
| WorkspaceDashboard | `app/Filament/Pages/WorkspaceDashboard.php` | Shared panel landing with permission-scoped actual resource links and summary | built | T-P1-08 |
| WorkspaceLogin | `app/Filament/Auth/WorkspaceLogin.php` | Native Filament authentication in the shared bilingual responsive layout | built | T-P1-08 |
| WorkspaceAvatarProvider | `app/Filament/Shared/WorkspaceAvatarProvider.php` | Local escaped initial SVG; no external avatar request | built | T-P1-08 |

## UI components (Blade)

| Name | Path | Status | Task |
|---|---|---|---|
| ui primitives (button, input, card, modal, ...) | `resources/views/components/ui/` | built | T-P1-09 |
| layouts (public, student, parent, auth) | `resources/views/layouts/` | planned | T-P1-10 |
| course-card | `resources/views/components/learning/course-card.blade.php` | planned | T-P2-04 |
| curriculum-list | `resources/views/components/learning/curriculum-list.blade.php` | planned | T-P4-03 |
| lesson-player | `app/Livewire/Player/LessonPlayer.php` | planned | T-P4-03 |
| ai-assistant-panel | `app/Livewire/Player/AiAssistantPanel.php` | planned | T-P7-03 |
| rich-content renderer | `resources/views/components/learning/rich-content.blade.php` | planned | T-P3-15 |
| library-picker | `app/Filament/Shared/Forms/LibraryPicker.php` | planned | T-P3-13 |
| locked-state | `resources/views/components/learning/locked-state.blade.php` | planned | T-P4-09 |
| child-context-strip | `resources/views/components/ui/child-context-strip.blade.php` | planned | T-P1-10 |
| error pages | `resources/views/errors/` | planned | T-P1-10 |

