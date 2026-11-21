# Test Prompt: `SEC-02` — Help Queue Link Name & Scrollable Table Keyboard Access

Write automated Pest Feature / Browser tests (do **not** modify production code) verifying the Demo Rehearsal P2 accessibility fixes (`T-P7-04`, `T-P9-06`).

## Target files & classes
- `app/Filament/Resources/HelpRequests/HelpRequestResource.php` (`overdue` column `->placeholder('-')`)
- `resources/views/filament/reports/index.blade.php` (`.yg-ai-scroll` `tabindex="0"` + `role="region"` + `aria-label`)
- `resources/views/filament/ai/usage.blade.php` (`.yg-ai-scroll` `tabindex="0"`)
- `resources/views/filament/commerce/reconciliation.blade.php` (`.yg-ai-scroll` `tabindex="0"`)

## Required scenarios
1. **Help Requests SLA column non-empty link text (`link-name`):**
   - Seed an instructor with a non-overdue `HelpRequest` (where `isOverdue()` is `false`).
   - Visit `/instructor/help-requests` in English (390 and 1440) in Pest Browser (or render `ListHelpRequests` via Livewire) and run `assertNoAccessibilityIssues()`.
   - Assert `.fi-ta-cell-overdue` contains `'-'` (no empty `<a>` link).
2. **Scrollable regions keyboard-focusable (`scrollable-region-focusable`):**
   - Visit `/admin/reports` (390x844 EN/AR) and `/admin/assistant-usage` (390x844 EN/AR) with seeded data and run `assertNoAccessibilityIssues()`.
   - Assert every `.yg-ai-scroll` element has `tabindex="0"`.

## Acceptance / Requirement IDs
- `T-P7-04`, `T-P9-06`, `YG-GAP-A11Y-001`

## Setup hints
- Use `RefreshDatabase` and `config(['identity.two_factor_enforced' => false])` for staff panel browser tests.
- **Rule:** Test writer must **not** modify production code.
