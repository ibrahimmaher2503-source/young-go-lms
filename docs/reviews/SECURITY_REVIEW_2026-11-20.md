# Independent security review — 2026-11-20

Reviewed `feat/lms-build` at `2424721e095d47c3885bfec90f9b42672a382df7` in the canonical checkout. Reviewer session `acceptance_security`. This is defensive source review and a lockfile audit for early T-P11-01. The verdict is **changes required**. Confirmed findings in the reviewed paths comprise two high and three medium issues.

## Findings

### High: private upload forms trust existing storage paths

- **Evidence:** The lesson media, curriculum PDF, resource and assignment-attachment fields and library add form lack the configured Filament path guard. The locked Filament implementation passes existing path strings through and validates file rules on temporary upload objects. The five application consumers resolve the path on the shared private disk and delete it in cleanup. Authorization in the domain actions applies to the destination course or library entry. See [curriculum forms and handlers](../../app/Filament/Resources/Courses/Pages/ManageCurriculum.php#L319), [lesson fields](../../app/Filament/Resources/Courses/Pages/ManageCurriculum.php#L511), [PDF and resource cleanup](../../app/Filament/Resources/Courses/Pages/ManageCurriculum.php#L567), [media cleanup](../../app/Filament/Resources/Courses/Pages/ManageCurriculum.php#L639), and [library add](../../app/Filament/Resources/Library/Pages/ListLibraryItems.php#L33).
- **Impact:** An instructor authorized to write to their own course or library can cause a known private object outside their scope to be deleted. When the object's type is accepted by the destination, it can also be copied into content accessible to that instructor. Random object names limit discovery. The authorization check is still missing.
- **Remediation:** Use temporary upload objects with `storeFiles(false)` and path tampering prevention, validate the object type in each handler, and only delete temporary objects owned by the operation. Apply to all five inputs together. Verify authorized uploads still work and existing private files survive rejected input. Related tasks T-P3-08, T-P3-09, T-P3-13, T-P6-04 and T-P11-01.

### High before Paymob activation: verified transaction is not bound to its checkout attempt

- **Evidence:** The signature covers provider `order.id`. The normalization selects the local attempt reference from unsigned callback metadata. Checkout stores a provider order ID when returned. The fulfillment lookup uses the local reference and checks gateway, state, currency and total without verifying that the signed provider order ID identifies that same attempt. Successful processing can fill an absent provider order ID from that same callback. See [Paymob signature fields](../../app/Domain/Commerce/Payments/PaymobGateway.php#L22), [callback normalization](../../app/Domain/Commerce/Payments/PaymobGateway.php#L264), [checkout persistence](../../app/Domain/Commerce/Actions/StartCheckout.php#L71), [attempt lookup](../../app/Domain/Commerce/Actions/ProcessPaymentWebhook.php#L86), and [fulfillment](../../app/Domain/Commerce/Actions/ProcessPaymentWebhook.php#L153).
- **Impact:** A signature-valid transaction can be associated with the wrong local order. The financial and entitlement records can then reflect an unrelated payment. The live adapter remains gated. Real provider semantics and reachability remain unverified.
- **Remediation:** Resolve or verify the matching attempt through the immutable signed order identity and the trusted checkout record. Fail closed and reconcile missing or inconsistent bindings before granting access. Confirm the creation and callback fields with a sandbox transaction before activation. Related tasks T-P8-02, T-P8-03 and T-P11-01.

### Medium: a support view-as session retains access after eligibility changes

- **Evidence:** Session creation checks permissions and learner eligibility, including deletion and safeguarding status. The view-as routes reach `ViewAsState::current()`, which checks account ownership, expiry and ended status. It does not recheck permissions or whether the learner can still be viewed. The next controller response reads the learner's personalized course data. See [eligibility](../../app/Domain/Support/ViewAsEligibility.php#L30), [session lookup](../../app/Domain/Support/ViewAsState.php#L20), [routes](../../routes/pages.php#L38), and [view-as controller](../../app/Http/Controllers/SupportViewAsController.php#L30).
- **Impact:** An otherwise authenticated staff member can retain read access until session expiry after loss of the specific view-as permission or when a learner becomes restricted.
- **Remediation:** Share the permission and learner eligibility checks between session creation and redemption, keeping the existing-session exclusion specific to creation. End and audit sessions when eligibility is lost. Related tasks T-P9-10 and T-P11-01.

### Medium: AI budget admission omits the cost of the proposed reservation

- **Evidence:** The budget action locks each configured scope and counts active reservations. It admits when current use is below the ceiling, then calculates and persists the cost of the new reservation without comparing it with the amount remaining. The assistant starts the provider path after this admission. See [budget calculation and admission](../../app/Domain/AI/Quota/BudgetGate.php#L85), [reservation sizing](../../app/Domain/AI/Quota/BudgetGate.php#L192), and [assistant caller](../../app/Domain/AI/Actions/AskLessonAssistant.php#L109).
- **Impact:** An admitted request can exceed a configured hard ceiling even if all reservation writes are serialized. Actual overspend depends on provider costs and usage. No real provider was used in this review.
- **Remediation:** Compute the amount before admission and reject an amount above the remaining global or course budget under the existing locks. Size the estimate against the maximum billable work allowed by a request, including configured provider retries. Related tasks T-P7-02 and T-P11-01.

### Medium: default exception telemetry can retain sensitive request details

- **Evidence:** Telescope is a normal Composer dependency and its enabled switch defaults to true. The nonlocal filter retains failed request records. The request watcher records the request URL with query string, payload and session. The app redacts the CSRF field and selected headers and excludes specific paths, while the callback and manual proof routes remain included. The installed watcher's built-in masks cover ordinary password fields and the authorization header. See [dependency](../../composer.json#L36), [Telescope config](../../config/telescope.php#L23), [nonlocal filter and masks](../../app/Providers/TelescopeServiceProvider.php#L20), [payment callback](../../app/Http/Controllers/PaymentWebhookController.php#L28), and [manual payment proof](../../app/Http/Controllers/ManualPaymentProofController.php#L46).
- **Impact:** Under default settings, a server error can retain provider callback metadata or private payer details beyond the application's scrubbed business and audit records. The Telescope access gate limits dashboard access to local super admins. Database retention and exports remain separate exposures.
- **Remediation:** Default Telescope to disabled outside local development. If operational monitoring is needed, explicitly filter sensitive paths, structured fields and URI queries before persistence, and verify this with a redaction regression check. Related tasks T-P1-11, T-P9-08 and T-P11-01.

## Other reviewed controls

| Area | Source evidence and scope |
| --- | --- |
| Payments | Webhook signature is checked before state changes. Payment-event replay handling and locked order transitions are present. Coupon reservation locks the order and guards the counter increment. Refund request locks the order and counts reserved exposure. Proof submission uses payer identity, verified file content, and private storage. Proof download checks payer and staff entitlement. |
| Learner access and media | Signed file and protected playback routes enforce authenticated, account-bound signatures and recheck private file access. Learner links are reevaluated through `ActingLearner`. Full refund entitlement revocation uses domain actions, with cohort release only when no alternate active grant remains. Source samples do not establish the full I01 or media matrix. |
| Identity | The staff panel registers persistent active-account, session and 2FA middleware. Session/device controls cover global web requests. Reviewed the normal parent context and the support view-as boundary. |
| Privacy and reporting | Archive download checks active requester and expiry and records access. Account anonymization and retention preserve protected financial records. Reviewed role-based report filter scoping and CSV cell handling. Database and storage deletion were not exercised. |
| AI | Reviewed entitlement and approved document checks, an adult age floor, guardian gating, source tag handling and cache eligibility. Provider output safety and real usage accounting require separate evidence. |
| Staff resources and HTTP hygiene | Reviewed order/refund guards and sampled course, library, help-request and CSV scope checks. Sampled bounded query inputs, escaping in raw Blade rendering and rate limits. Full resource/action authorization and deployed header settings remain open. |

## Executed checks and remaining evidence

| Command | Outcome |
| --- | --- |
| `php .tools/composer.phar audit --locked` | Exit 0. No security vulnerability advisories found. |
| `npm audit --omit=dev` | Exit 0. Zero vulnerabilities reported. |

The report records source-confirmed behavior and dependency results. Browser and route-role regression, MySQL concurrency, staging configuration and headers, real Paymob provider binding, and real-provider AI evaluation remain unverified in this review. Keep T-P11-01 open pending high findings, independent regression evidence and launch-gate mapping.
