# Young Go LMS --- Reuse Registry

Living index of shared code. **Check here before creating anything new.**
Every task that creates a shared piece adds a row in the same commit.
The orchestrator rejects work that duplicates a registered piece. Planned rows
are design intent, not proof of existing code or a mandate to create scaffolding.
Build a shared piece only when its task needs it; ED-033 governs minimal boundaries.

Status: `planned` (defined, not built) · `built` · `deprecated` (with replacement).

## Backend

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| ArabicNormalizer | `app/Domain/Shared/Search/ArabicNormalizer.php` | Single Arabic/Latin search normalization; not exact AI cache identity | planned | T-P0-06 |
| HasSearchText | `app/Domain/Shared/Search/HasSearchText.php` | Fill `search_text` on save | planned | T-P0-06 |
| Money / PriceFormatter | `app/Domain/Shared/Money/` | Integer-minor money + locale formatting | planned | T-P0-06 |
| MarketResolver | `app/Domain/Shared/Markets/` | EG active / SA disabled | planned | T-P0-06 |
| LocalizedDate | `app/Domain/Shared/Time/` | UTC storage, local display | planned | T-P0-06 |
| AuditLogger | `app/Domain/Audit/AuditLogger.php` | Audit sensitive actions | planned | T-P1-04 |
| EntitlementService | `app/Domain/Entitlements/EntitlementService.php` | Independent source grants; mode/capability-aware access (ED-035) | planned | T-P4-01 |
| PrivateFileController + SignedFileUrl | `app/Domain/Media/` | Object/purpose-authorized private downloads; entitlement is not sufficient for proofs/submissions | planned | T-P3-08 |
| LocalizedNotification | `app/Domain/Notifications/` | Base notification with dedupe | planned | T-P1-11; extended T-P5-04 |
| PaymentGateway (contract) | `app/Domain/Commerce/Contracts/` | Gateway abstraction | planned | T-P8-02 |
| AiProviderInterface + FakeAiProvider | `app/Domain/AI/Contracts/` | Completion-only seam + fake; separate OCR/embedding capability when needed (ED-033/036) | planned | T-P3-10 |
| Feature flags + EnableGatedFlag | `app/Domain/Settings/` | Pennant flags with approval reference | planned | T-P0-07 |
| app:doctor / app:req-coverage | `app/Console/Commands/` | Health check / requirement coverage (G10, G11) | planned | T-P0-07 |
| AccessDecision + canAccessItem() | `app/Domain/Entitlements/` | Layered access with reason codes | planned | T-P4-09 |
| RecordProgress | `app/Domain/Learning/Actions/` | Only writer of progress | planned | T-P4-04 |
| HtmlSanitizer | `app/Domain/Shared/Security/` | Single sanitizer (rich text, messages) | planned | T-P3-15 |
| ImageReencoder | `app/Domain/Media/` | Strip EXIF/GPS on upload | planned | T-P3-08 |
| GrantEntitlement | `app/Domain/Entitlements/Actions/` | Replay-safe source transition; no duplicate audit/event (ED-034) | planned | T-P4-01 |
| AssistantEligibility | `app/Domain/AI/` | AI availability decision | planned | T-P3-10 |

## Filament shared

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| TranslatableTabs | `app/Filament/Shared/Forms/TranslatableTabs.php` | AR/EN tabs for translatable fields | planned | T-P1-08 |
| StatusBadgeColumn | `app/Filament/Shared/Tables/` | Enum → colored badge | planned | T-P1-08 |
| MoneyColumn | `app/Filament/Shared/Tables/` | Money display | planned | T-P1-08 |
| LocalizedDateColumn | `app/Filament/Shared/Tables/` | Date in user tz | planned | T-P1-08 |
| ApproveRejectAction | `app/Filament/Shared/Actions/` | Approve / reject with reason + audit | planned | T-P1-08 |
| ExportCsvAction | `app/Filament/Shared/Actions/` | Permission-aware CSV export | planned | T-P1-08 |
| SeoFieldsGroup | `app/Filament/Shared/Forms/` | Bilingual SEO fields | planned | T-P1-08; used T-P2-05 |

## UI components (Blade)

| Name | Path | Status | Task |
|---|---|---|---|
| ui primitives (button, input, card, modal, ...) | `resources/views/components/ui/` | planned | T-P1-09 |
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
