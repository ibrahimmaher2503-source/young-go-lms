# YOUNG GO LMS --- DECISION REGISTER

**Version:** 3.3 · **Updated:** 16 November 2026\
**Purpose:** Separate approved owner decisions from technical
recommendations and open dependencies.

## A. Approved owner decisions

  -----------------------------------------------------------------------------------------------
  ID                Date              Decision                      Impact
  ----------------- ----------------- ----------------------------- -----------------------------
  D-001             2026-11-15        Build a fully custom LMS from Own domain model and business
                                      scratch; no Academy           logic
                                      LMS/EduEx/packaged LMS core   

  D-002             2026-11-15        Keep existing Young Go brand, No rebrand; UI modernization
                                      same logo and recognizable    only
                                      identity                      

  D-003             2026-11-15        Continue on current website   Verify exact DNS hostname
                                      domain; owner stated          before cutover
                                      `youngschool.com`             

  D-004             2026-11-15        Migrate existing courses to   All legacy courses and
                                      new platform                  related course content/assets
                                                                    are in scope

  D-005             2026-11-15        Audience is all ages; content Configurable course age
                                      categorized by age            ranges/bands
                                      suitability                   

  D-006             2026-11-15        Parent/guardian account       Guardian-child relationship
                                      linked to child accounts      and parent dashboard

  D-007             2026-11-15        Hybrid instructor model:      Approval workflow and
                                      academy-owned courses plus    instructor
                                      independent instructors       ownership/assignment model

  D-008             2026-11-15        Sales include individual      Commerce supports all four
                                      courses, bundles, monthly and 
                                      annual subscriptions          

  D-009             2026-11-15        Egypt and Saudi are target    Country-aware configuration
                                      markets                       

  D-010             2026-11-15        Egypt active for initial      Saudi checkout disabled until
                                      sales; Saudi technically      entity/legal/payment
                                      prepared but not selling yet  readiness

  D-011             2026-11-15        Arabic and English complete   Full RTL/LTR and locale-ready
                                      from first release;           fields
                                      architecture supports more    
                                      languages                     

  D-012             2026-11-15        Young Go receives all student No direct gateway split
                                      payments centrally, then      payment
                                      settles instructors           

  D-013             2026-11-15        Keep instructor commission    No watch-time or
                                      model simple                  engagement-based settlement

  D-014             2026-11-15        Individual course instructor  Rate per instructor/course;
                                      earnings use configurable     exact rate open
                                      percentage                    

  D-015             2026-11-15        Bundle earnings use           Allocation values/formula
                                      admin-defined allocation      open for approval
                                      across included courses, then 
                                      commission                    

  D-016             2026-11-15        Subscription instructor       Fixed amount per
                                      earnings use fixed amount per student/course/subscription
                                      student enrolled in           period; no engagement metric
                                      instructor's course           

  D-017             2026-11-15        AI assistant is for recorded  Lesson-scoped AI availability
                                      lessons only, not live        
                                      sessions                      

  D-018             2026-11-15        AI uses curriculum PDF        Current lesson PDF is
                                      attached to each              assistant source
                                      lesson/session and provides   
                                      very short simple             
                                      clarifications                

  D-019             2026-11-15        Student can mark "understood" Unresolved state creates
                                      or "still not understood"     human help request

  D-020             2026-11-15        Unresolved AI request         Instructor first recipient;
                                      notifies course instructor    admin receives copy
                                      directly and appears in admin 

  D-021             2026-11-15        Student-instructor follow-up  Request-linked thread; no
                                      takes place inside platform   external-only communication
                                                                    requirement

  D-022             Existing agency   SKIT mandatory for            Shared backoffice UI contract
                    standard          admin/instructor/operations   
                                      panels                        

  D-023             Existing agency   Keep Young Go separate from   No project/data merge
                    standard          Young Film School / YFS       

  D-024             Existing agency   Production-first Codex        Execution discipline
                    standard          workflow; concise English     
                                      commands, one focused         
                                      validation per batch and      
                                      final deep QA/backup stage    

  D-025             2026-11-16        Paymob is the Egypt payment   PaymentGateway adapter;
                                      gateway (confirmed by I.S.    Paymob first; closes O-006
                                      project lead)                 gateway part
  -----------------------------------------------------------------------------------------------

## B. Technical recommendations (not owner-locked)

  ---------------------------------------------------------------------------------------------
  ID                      Recommendation                  Notes
  ----------------------- ------------------------------- -------------------------------------
  R-001                   Laravel 13 / PHP 8.3+           Verify target runtime and package
                                                          compatibility

  R-002                   Filament 5 customized to SKIT   UI toolkit only, not LMS core

  R-003                   Modular monolith                Avoid premature microservices

  R-004                   PostgreSQL or MySQL             Choose after host and legacy DB
                                                          inspection

  R-005                   Redis for cache/queues where    Depends on environment
                          available                       

  R-006                   Provider-neutral                Avoid vendor lock-in
                          video/storage/payment/live/AI   
                          adapters                        

  R-007                   One unified entitlement engine  Consistency across
                                                          purchase/bundle/subscription/manual
                                                          grants

  R-008                   Manual live links first         API automation later if selected

  R-009                   Course builder wizard based on  Preserve capabilities, improve
                          existing screens but redesigned interaction
                          for usability                   

  R-010                   Migration scripts idempotent    Safe reruns required
                          and auditable                   
  ---------------------------------------------------------------------------------------------

### B.2 Recommended defaults added in v3.1 (engineering only; policy gates apply)

| ID | Default | Closes gap | Owner can change |
|---|---|---|---|
| R-011 | Parent-created child accounts with username/password, optional email; link codes for existing accounts; birth year/month only | Child login undefined | Yes, before minor launch |
| R-012 | `name_ar` / `name_en` / certificate names | Single name field | Yes |
| R-013 | Cohorts with capacity and waitlist for live/hybrid courses | Cohort entity missing | Yes |
| R-014 | Media protection tiers T1/T2/T3; paid lessons require T1/T2 unless documented exception | Video protection undefined | Yes |
| R-015 | Session limit 2, device limit 3, dynamic watermark | Account sharing | Values configurable |
| R-016 | Revisions for independent-instructor edits after publication | Post-publish edits | Yes |
| R-017 | Manual renewal + reminders; 3-day grace and no proration are synthetic-test proposals only | Renewal undefined | O-024/O-032 approval before subscription sales |
| R-018 | Instructor application, versioned agreement, encrypted payout profile | Onboarding missing | Yes |
| R-019 | Wishlist + moderated reviews carried; open student discussions not built | June features silent in v3.0 | Yes |
| R-020 | Arabic search normalization | Search quality | — |
| R-021 | GA4/Meta Pixel only after consent, never in learning/child/AI screens | Analytics dropped | Yes |
| R-022 | WhatsApp/SMS adapter in P9, first for live reminders | Channel gap | Provider O-021 |
| R-023 | Scoped export/deletion workflow; retention values remain proposals | Privacy rights | O-025 before production pruning |
| R-024 | Automated invariant test suite = D-024 focused validation; Larastan + Pint + arch tests in CI | Testing risk | — |
| R-025 | WCAG 2.1 AA target; RPO ≤ 24 h, RTO ≤ 4 h | NFR numbers | SLA owner |
| R-026 | Student/public UI: Blade + Livewire + Alpine + Tailwind (shares Livewire with Filament) | Frontend open | Yes, before P2 |
| R-027 | Token-based design system (`11_DESIGN_SYSTEM.md`) and code conventions (`12_...`) | UI/code consistency | — |
| R-028 | AI assistant v3.0 design: full-PDF context, quote verification, quiz guard, eval gate | AI quality | Per O-014 |
| R-029 | Agent execution loop with orchestrator + lighter-model subagents + review (`agents/`) | Delivery method | — |
| R-030 | Certificates/PDFs rendered with a headless-Chromium HTML-to-PDF renderer for correct Arabic shaping | Arabic PDF quality | Yes |

### B.3 Engineering decisions (v3.2)

Engineering decisions (ED-*) in `13_ENGINEERING_DECISIONS.md` are RECOMMENDED-DEFAULT technical decisions (reversible, binding for agents). Notable: ED-002 single `curriculum_items` ordering; ED-005 layered access with reason codes; ED-006 JSON translations (supersedes `*_translations` tables); ED-016 staff 2FA; ED-020 acceptance-tests-first; ED-028 requirement traceability (G10); ED-029 verification subagents. LearnHouse is a design reference only (no code reuse; AGPL) — see `14_LEARNHOUSE_REFERENCE_STUDY.md`.

### B.4 Agent-decided lifecycle safeguards (2026-11-16)

These decisions clarify the existing product scope in PRD §29.15. They are
reversible engineering requirements, not approved D-* decisions and not
permission to activate gated services. Authority follows `AGENTS.md` §2;
this section cannot override approved owner decisions or the PRD.

| Decision | Required engineering behavior | Policy or external dependency left open |
|---|---|---|
| Independent access sources | Preserve each grant; revoke only the affected source; separate learner/public/author/support modes (YG-REV-ACC-001/002). | No new commercial access rights invented |
| Guardian authority | Link provenance, authority and consent are distinct; revoke delegated sessions/recovery/notifications together (YG-REV-FAM-001/002). | O-015/O-016; minor AI also O-014/O-028 |
| Payment lifecycle | Immutable checkout snapshots, durable verified events, atomic idempotent fulfillment and capacity reservations (YG-REV-PAY-001/002/003). | Provider/account evidence and O-006/O-007/O-023/O-031 |
| Refunds and accounting | Append adjustments; retain originals; isolate source-specific access effects and reconcile finance exceptions (YG-REV-PAY-004/FIN-001). | O-008..O-012/O-031; no automatic refund-policy approval |
| Subscription periods | Snapshot inclusion/terms and prevent duplicate renewal/accrual (YG-REV-SUB-001). | O-032; grace/proration/calendar rules remain proposals |
| Learning lifecycle | Version completion basis, preserve attempts/certificates and handle concurrency/timezones (YG-REV-LRN-001/002/003). | Course-specific published rules; no invented certificate eligibility |
| Privacy workflow | Verified scoped requests, holds, dry-run and backup/deletion reconciliation (YG-REV-PRV-001). | O-015/O-016/O-025 before affected production processing |
| Migration and cutover | Stable mapping, immutable manifests, delta reconciliation and post-switch write-safe rollback (YG-REV-MIG-001/002). | O-001/O-003/O-018 and explicit cutover authority |

Synthetic tests may proceed while policy is open. A passing mock or a
populated approval-reference field is not evidence of actual approval.

## C. Open decisions --- do not guess

  ----------------------------------------------------------------------------------------------------------------------------------
  ID                Open item                                                   Why it matters           Interim treatment
  ----------------- ----------------------------------------------------------- ------------------------ ---------------------------
  O-001             Exact live hostname/DNS and hosting access                  Domain cutover           Owner-stated
                                                                                                         `youngschool.com`; verify
                                                                                                         before cutover

  O-002             New repository/branch/SSH/server                            Implementation access    UNKNOWN until
                                                                                                         supplied/observed

  O-003             Legacy LMS source code, DB schema, file storage and         Migration/behavior audit Inspect before import
                    provider access                                                                      

  O-004             Framework/runtime and database                              Compatibility            Verify environment before
                                                                                                         lock

  O-005             Video provider and storage capacity                         Streaming                Adapter first; provider
                                                                                performance/cost         later

  O-006             ~~Payment gateway~~ (closed by D-025: Paymob). Remaining:   Checkout activation      Paymob adapter disabled until
                    Paymob account credentials, enabled methods, exact                                   credentials; manual review
                    Instapay workflow                                                                    workflow; no fake success

  O-007             Egyptian legal seller name, tax registration, VAT and       Legal checkout           Obtain from
                    invoice fields                                                                       owner/accountant

  O-008             Individual course commission percentages                    Instructor settlement    Configurable; no invented
                                                                                                         rates

  O-009             Bundle allocation rule                                      Fair ledger distribution Admin allocation per
                                                                                                         bundle/course; final
                                                                                                         formula
                                                                                                         owner/accountant-approved

  O-010             Fixed subscription amount per student/course/period         Subscription ledger      Configurable; values open

  O-011             Commission base: discount/tax/gateway fees/refunds          Correct financial        Store components
                                                                                calculation              separately; accountant
                                                                                                         approves base

  O-012             Settlement frequency and payout method                      Finance operations       Manual settlement ledger
                                                                                                         first

  O-013             Saudi entity, gateway, bank and tax/invoice rules           Saudi activation         Disabled market profile

  O-014             AI provider/model, data region, terms, retention and budget Privacy/cost             Adapter only; no real minor
                                                                                                         data until approval

  O-015             Child age bands and guardian verification process           Minor safety             Configurable; legal/policy
                                                                                                         review required

  O-016             Parent access to child's full AI history                    Child privacy            No full chat access by
                                                                                                         default; help status can be
                                                                                                         exposed per policy

  O-017             Family subscription/shared household plan                   Commercial model         Not assumed

  O-018             Exact migration scope for                                   Continuity               Inventory and owner
                    users/enrollments/progress/payments/attempts/certificates                            sign-off; courses/content
                                                                                                         mandatory

  O-019             Certificate design/legal issuer wording                     Output validity          Use current approved assets
                                                                                                         when supplied

  O-020             Course completion defaults                                  Certification/progress   Configurable per course

  O-021             Email provider, Zoom/Meet/BigBlueButton account and API     Integrations             Manual/standard adapters
                    credentials                                                                          until supplied

  O-022             Dates, effort estimates, staffing and budget                Project planning         Not specified in this pack
  ----------------------------------------------------------------------------------------------------------------------------------

### C.2 Open items added in v3.1

| ID | Open item | Why it matters | Interim treatment |
|---|---|---|---|
| O-023 | Is Egyptian ETA e-receipt/e-invoice submission mandatory for Young Go, and does Paymob (or another provider) perform it? | Legal checkout | `EReceiptProvider` adapter disabled; ETA-ready fields stored; ask accountant + Paymob |
| O-024 | Auto-renew with saved cards (Paymob support + owner approval) | Subscription UX | Manual renewal (R-017) |
| O-025 | Final retention periods, legal holds, deletion/anonymization and backup expiry per data class | Privacy | PRD values are test proposals; no destructive production pruning before approval |
| O-026 | SKIT UI kit source files/spec location | Admin visuals | Filament theme from design tokens until SKIT supplied |
| O-027 | Brand assets (logo files, exact colors, fonts) extracted from current site | Visual identity | Extract in P0 T-P0-04; placeholders flagged |
| O-029 | Error-tracking provider (Sentry-compatible or other) | Operations | Reporter adapter logs locally until chosen (ED-021) |
| O-030 | Expected launch traffic (concurrent learners, peak live sessions) | Capacity sizing | Documented assumption in T-P11-02 |
| O-028 | Safeguarding message wording and designated recipients for minors | Child safety | AI off for minors |

### C.3 Lifecycle policies still requiring owner approval

| ID | Open item | Why it matters | Interim treatment |
|---|---|---|---|
| O-031 | Refund eligibility/windows, partial-line allocation, access withdrawal, dispute resolution, late paid/unfulfilled capacity orders and cohort cancellation remedies | Customer rights, retained access and financial reconciliation | Build/test states and adjustments with synthetic fixtures; hold affected live collection until owner/accountant-approved policy and provider capabilities are recorded. Never fabricate a refund or erase a valid independent grant. |
| O-032 | Subscription calendar anchoring/month-end/leap-year rules, early/late renewal, grace, cancellation/proration, catalog changes and eligibility/timing of fixed instructor accrual | Published paid-period terms and instructor liability | Manual-renewal implementation may proceed synthetically; 3-day grace/no proration are proposals. Subscription sales require approved terms; auto-renew additionally requires O-024. |

## D. Decision change protocol

When an open item is resolved, update this file with date, exact owner
decision, impact and affected PRD/DMC/architecture sections. Do not
silently convert a recommendation into an approved decision. Technical
low-risk defaults may be implemented when documented and reversible;
legal, financial, privacy and irreversible choices require owner
approval.

## D-026 — owner visual direction and full reference matching (2026-11-17)

Owner approved the calm slate/white/teal card workspace and explicitly requires full visual fidelity on every current/future page, including details, typography and states. Use shared native tables/forms/actions with scoped real data. Follow docs/design-system/VISUAL_ACCEPTANCE.md and TABLE_WORKSPACE.md. The sample does not approve fake records, unsupported controls or official branding; O-027 stays open.
