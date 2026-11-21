# SEC-9: Re-check Support View-As Eligibility on Session Redemption

## Context
- **Finding:** Medium (`SECURITY_REVIEW_2026-11-20.md`) — Related tasks `T-P9-10`, `T-P11-01`.
- **Fixed files:**
  - `app/Domain/Identity/Models/ViewAsSession.php` (`END_REVOKED`)
  - `app/Domain/Support/ViewAsEligibility.php` (`redeemRefusal()`)
  - `app/Domain/Support/ViewAsState.php` (`current()`)

## Reproduction / Attack Scenario
1. An authorized staff member starts a valid support view-as session for an eligible learner.
2. While the session is still within `expires_at`, one of the following happens:
   - The staff member loses `support.view_as` or `learner.record.view` (or `learner.record.sensitive` for a minor).
   - An open `AiSafeguardingFlag` or active `DataRequest` (`Deletion`) is created for the learner.
   - The learner becomes inactive or anonymized.
3. Previously, `ViewAsState::current()` only checked `staff_id`, `ended_at`, and `isExpired()`, so `GET /support/view-as` and `/support/view-as/courses/{course}` continued serving the learner's data until expiry.

## Regression Test Requirements (write in `tests/Feature/`)
1. Start a valid `ViewAsSession` for an eligible learner and verify `GET route('support.view-as.home')` returns `200`.
2. For each of:
   - Revoking `support.view_as` from the staff user (and flushing permission cache),
   - Opening an `AiSafeguardingFlag` (`status = 'open'`) for the learner,
   - Creating a `Deletion` `DataRequest` (`Requested`) for the learner,
   - Setting the learner's `status` to `'suspended'`,
   request `GET route('support.view-as.home')` (and `GET route('support.view-as.course', $course)`).
3. Assert `403 Forbidden`, `$session->fresh()->ended_at` is non-null with `end_cause === ViewAsSession::END_REVOKED`, an audit entry `'support.view_as_stopped'` with `'cause' => 'revoked'` is recorded, and `session('view_as_session_id')` is cleared.
