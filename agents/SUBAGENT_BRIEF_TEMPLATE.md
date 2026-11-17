# SUBAGENT BRIEF TEMPLATE (v3.2)

Fill every field. Name files, classes, tests and requirement IDs explicitly.
Keep it ≤ 60 lines.

```markdown
## Brief: <TASK-ID> / <S#> — <short title>
Agent: test-writer | implementer | ui-builder | chore

### Goal (one sentence)
<what exists and works when you are done>

### Requirements you prove (read these sections first)
- YG-GAP-FAM-001 (PRD §29.1) · ED-017 · D-006
- Task acceptance items you own: A1, A3

### AI line (from task)
<e.g. "ai: hook — add `ai_enabled` flag to curriculum_items; do not call any AI provider"> or "ai: none — add nothing AI-related">

### Allowed files (only these; report if you need more)
- app/Domain/Guardians/Actions/CreateChildAccount.php (new)
- tests/Feature/Guardians/CreateChildAccountTest.php (exists from S0 — make it pass, do not weaken)

### Reuse (must use, do not re-create)
- App\Domain\Audit\AuditLogger · App\Domain\Shared\Search\ArabicNormalizer
- <x-ui.form-field>, <x-ui.button>, TranslatableTabs

### Requirements detail
1. <fields, enums, status values, unique keys>
2. <authorization / idempotency rule>
3. <edge cases: duplicate, expired, unauthorized (404), empty, AR/EN>

### UI spec (ui-builder only)
Screens, primary action, states (loading/empty/error/success/locked), copy keys, screenshot list.

### Out of scope
- <explicitly excluded work>

### Checks before returning
- vendor/bin/pest --group=<TASK-ID>
- vendor/bin/phpstan analyse <paths>
- php artisan app:check-translations && php artisan app:check-rtl   (if UI/strings)
- screenshots → tests/Browser/screenshots/<TASK-ID>/   (UI)

### Return (short)
Files changed · new shared pieces (registry rows) · check results · not done + why
```

## test-writer brief extras (S0)
- One test per acceptance item minimum, tagged `->group('<TASK-ID>', '<REQ-ID>')`.
- Include forbidden-role and wrong-owner cases (expect 404 for hidden resources, 403 for visible-but-forbidden actions).
- Tests must fail now because the feature is missing (not because of syntax errors); report the failure reason per test.
