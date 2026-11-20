# 03: Independent code and security review (early T-P11-01)

> Read `docs/agent-prompts/00_COMMON.md` first. Use the strongest model (`reviewer`). This task is **read-only**: report findings, do not fix them.

## Scope (highest risk first)
1. Commerce and payments: `app/Domain/Commerce*`. Check the Paymob webhook (HMAC verification, idempotency, replay), manual payment proofs (file upload), coupons (race conditions, double use), refunds and reconciliation.
2. Entitlements: any bypass of I01, IDOR on courses, lessons and files, and access that continues after a refund or expiry.
3. Media protection: signed URLs, the watermark, device and session limits, private files and PDFs.
4. Identity: staff 2FA, child password reset, parent→child links (can a parent see another family's child?), and view-as in Learner 360 (audit and scope).
5. Privacy: anonymization, `privacy:prune`, and CSV exports (does data leak outside the user's permissions? is CSV injection possible?).
6. AI: prompt injection from curriculum PDFs, data leaking between courses, limits and cost.
7. Filament: every Resource has a Policy, and tenant and instructor scoping is correct.
8. General: mass assignment, raw SQL, `{!! !!}` in Blade, rate limiting on login, OTP and checkout, security headers, CSRF exceptions, secrets in logs.

Also run `composer audit` and `npm audit --omit=dev`.

## For each finding
Record: severity (Critical/High/Medium/Low) | file:line | the attack scenario with concrete inputs | impact | proposed fix | related task.
Confirm each finding by reading the code and following the path through it. A guess is not a finding: put unconfirmed suspicions in a separate list.

## Output
- `docs/reviews/SECURITY_REVIEW_2026-11-xx.md`.
- A summary in Arabic: the count at each severity, with Critical and High listed first.
- Do not fix anything. Fixes go through prompt 04 after the PO approves them.
