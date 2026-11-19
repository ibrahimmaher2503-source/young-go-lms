# YOUNG GO LMS --- DECISION REGISTER

**Version:** 3.2 · **Updated:** 16 November 2026\
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
  D-026             2026-11-17        Approve the calm light        Shared Filament table shell;
                                      slate/white/teal direction    native tables and permission
                                      and reusable composition     boundaries stay. The mockup
                                      for all current/future pages  does not approve logo/data;
                                                                    O-027 later decided 2026-11-18.
                                      Full visual matching is
                                      mandatory before acceptance.
  -----------------------------------------------------------------------------------------------

**D-027 · 2026-11-18 · Public-site brand kit.** The owner approved the current Young Go wordmark, semantic palette and bundled typography for T-P2-03. Legacy artwork and hostname remain unverified. This approval supplies the public implementation input under O-027.

**D-028 · 2026-11-19 · Defer visual acceptance for dependency work.** The owner authorizes downstream implementation once the prerequisite task meets its functional acceptance criteria and required nonvisual verification. Record the explicit marker `G7 VISUAL DEFERRED` in its `agents/PROGRESS.md` row, with the checks, reviewed revision and outstanding visual work. Visual fidelity and the UX score remain open. Functional browser journeys, accessibility, screenshots required by the task, security review and other applicable checks still run. A functional defect, missing or failing task test, or unresolved prerequisite prevents the marker. Deferred visual acceptance stays open at milestone and release review unless the owner explicitly accepts that scope.

## B. Technical recommendations (not owner-locked)

**O-015 · Educational catalog defaults (2026-11-18).** The owner asked for research-based defaults. Seed 0–9, 10–17 and 18+ as editable educational bands for Egypt. WHO defines adolescence as 10–19. CRC Article 1 defines childhood as under 18, subject to its stated exception. The LMS split is an educational grouping. Guardian verification, privacy, consent and AI eligibility still require the applicable reviewed policy. [WHO adolescent health](https://www.who.int/health-topics/adolescent-health) · [CRC Article 1](https://www.unicef.org/child-rights-convention/convention-text)

  ---------------------------------------------------------------------------------------------
  ID                      Recommendation                  Notes
  ----------------------- ------------------------------- -------------------------------------
  R-001                   Laravel 13 / PHP 8.4+           ACCEPTED in ADR-001 (T-P0-02):
                                                          laravel/framework ^13.42, PHP ^8.4
                                                          (Pest 5 + Spatie majors need 8.4);
                                                          host runtime still UNKNOWN (B-05)

  R-002                   Filament 5 customized to SKIT   UI toolkit only, not LMS core.
                                                          ACCEPTED in ADR-001: filament ^5.11
                                                          + livewire ^4.4

  R-003                   Modular monolith                Avoid premature microservices

  R-004                   PostgreSQL or MySQL             PROVISIONAL in ADR-001: MySQL 8.4
                                                          LTS primary, PostgreSQL 16+ kept
                                                          portable; revisit when host/legacy
                                                          DB known (B-05, B-06)

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

### B.2 Recommended defaults added in v3.1 (implement now, reversible)

| ID | Default | Closes gap | Owner can change |
|---|---|---|---|
| R-011 | Parent-created child accounts with username/password, optional email; link codes for existing accounts; birth year/month only | Child login undefined | Yes, before minor launch |
| R-012 | `name_ar` / `name_en` / certificate names | Single name field | Yes |
| R-013 | Cohorts with capacity and waitlist for live/hybrid courses | Cohort entity missing | Yes |
| R-014 | Media protection tiers T1/T2/T3; paid lessons require T1/T2 unless documented exception | Video protection undefined | Yes |
| R-015 | Session limit 2, device limit 3, dynamic watermark | Account sharing | Values configurable |
| R-016 | Revisions for independent-instructor edits after publication | Post-publish edits | Yes |
| R-017 | Manual subscription renewal + reminders + 3-day grace; no proration | Renewal undefined | Yes (O-011, O-024) |
| R-018 | Instructor application, versioned agreement, encrypted payout profile | Onboarding missing | Yes |
| R-019 | Wishlist + moderated reviews carried; open student discussions not built | June features silent in v3.0 | Yes |
| R-020 | Arabic search normalization | Search quality | — |
| R-021 | GA4/Meta Pixel only after consent, never in learning/child/AI screens | Analytics dropped | Yes |
| R-022 | WhatsApp/SMS adapter in P9, first for live reminders | Channel gap | Provider O-021 |
| R-023 | Data export/deletion workflow and retention defaults | Privacy rights | Values O-025 |
| R-024 | Automated invariant test suite = D-024 focused validation; Larastan + Pint + arch tests in CI | Testing risk | — |
| R-025 | WCAG 2.1 AA target; RPO ≤ 24 h, RTO ≤ 4 h | NFR numbers | SLA owner |
| R-026 | Student/public UI: Blade + Livewire + Alpine + Tailwind (shares Livewire with Filament) | Frontend open | Yes, before P2 |
| R-027 | Token-based design system (`11_DESIGN_SYSTEM.md`) and code conventions (`12_...`) | UI/code consistency | — |
| R-028 | AI assistant v3.0 design: full-PDF context, quote verification, quiz guard, eval gate | AI quality | Per O-014 |
| R-029 | Agent execution loop with orchestrator + lighter-model subagents + review (`agents/`) | Delivery method | — |
| R-030 | Certificates/PDFs rendered with a headless-Chromium HTML-to-PDF renderer for correct Arabic shaping | Arabic PDF quality | Yes |

### B.3 Engineering decisions (v3.2)

ED-001..ED-032 in `13_ENGINEERING_DECISIONS.md` are RECOMMENDED-DEFAULT technical decisions (reversible, binding for agents). Notable: ED-002 single `curriculum_items` ordering; ED-005 layered access with reason codes; ED-006 JSON translations (supersedes `*_translations` tables); ED-016 staff 2FA; ED-020 acceptance-tests-first; ED-028 requirement traceability (G10); ED-029 verification subagents. LearnHouse is a design reference only (no code reuse; AGPL) — see `14_LEARNHOUSE_REFERENCE_STUDY.md`.

## C. Open decisions --- do not guess

  ----------------------------------------------------------------------------------------------------------------------------------
  ID                Open item                                                   Why it matters           Interim treatment
  ----------------- ----------------------------------------------------------- ------------------------ ---------------------------
  O-001             Exact live hostname/DNS and hosting access                  Domain cutover           Owner-stated
                                                                                                         `youngschool.com`; verify
                                                                                                         before cutover

  O-002             New repository/branch/SSH/server                            Implementation access    UNKNOWN until
                                                                                                         supplied/observed
                  DECIDED 2026-11-18, see D

  O-003             Legacy LMS source code, DB schema, file storage and         Migration/behavior audit Inspect before import
                    provider access                                                                      

  O-004             Framework/runtime and database                              Compatibility            Verify environment before
                                                                                                         lock
                  DECIDED 2026-11-18, see D

  O-005             Video provider and storage capacity                         Streaming                Adapter first; provider
                                                                                performance/cost         later
                  DECIDED 2026-11-18, see D

  O-006             ~~Payment gateway~~ (closed by D-025: Paymob). Remaining:   Checkout activation      Paymob adapter disabled until
                    Paymob account credentials, enabled methods, exact                                   credentials; manual review
                    Instapay workflow                                                                    workflow; no fake success
                  DECIDED 2026-11-18, see D (methods); credentials still OPEN

  O-007             Egyptian legal seller name, tax registration, VAT and       Legal checkout           Obtain from
                    invoice fields                                                                       owner/accountant

  O-008             Individual course commission percentages                    Instructor settlement    Configurable; no invented
                                                                                                         rates
                  DECIDED 2026-11-18, see D

  O-009             Bundle allocation rule                                      Fair ledger distribution Admin allocation per
                                                                                                         bundle/course; final
                                                                                                         formula
                                                                                                         owner/accountant-approved
                  DECIDED 2026-11-18, see D

  O-010             Fixed subscription amount per student/course/period         Subscription ledger      Configurable; values open
                  DECIDED 2026-11-18, see D

  O-011             Commission base: discount/tax/gateway fees/refunds          Correct financial        Store components
                                                                                calculation              separately; accountant
                                                                                                         approves base
                  DECIDED 2026-11-18, see D

  O-012             Settlement frequency and payout method                      Finance operations       Manual settlement ledger
                                                                                                         first
                  DECIDED 2026-11-18, see D

  O-013             Saudi entity, gateway, bank and tax/invoice rules           Saudi activation         Disabled market profile
                  DECIDED 2026-11-18, see D (gateway); legal entity/tax facts still OPEN

  O-014             AI provider/model, data region, terms, retention and budget Privacy/cost             Adapter only; no real minor
                                                                                                         data until approval
                  DECIDED 2026-11-18, see D

  O-015             Child age bands and guardian verification process           Minor safety             Configurable; legal/policy
                                                                                                         review required
                  DECIDED 2026-11-18, see D

  O-016             Parent access to child's full AI history                    Child privacy            No full chat access by
                                                                                                         default; help status can be
                                                                                                         exposed per policy
                  DECIDED 2026-11-18, see D

  O-017             Family subscription/shared household plan                   Commercial model         Not assumed
                  DECIDED 2026-11-18, see D

  O-018             Exact migration scope for                                   Continuity               Inventory and owner
                    users/enrollments/progress/payments/attempts/certificates                            sign-off; courses/content
                                                                                                         mandatory
                  DECIDED 2026-11-18, see D

  O-019             Certificate design/legal issuer wording                     Output validity          Use current approved assets
                                                                                                         when supplied

  O-020             Course completion defaults                                  Certification/progress   Configurable per course
                  DECIDED 2026-11-18, see D

  O-021             Email provider, Zoom/Meet/BigBlueButton account and API     Integrations             Manual/standard adapters
                    credentials                                                                          until supplied
                  DECIDED 2026-11-18, see D

  O-022             Dates, effort estimates, staffing and budget                Project planning         Not specified in this pack
  ----------------------------------------------------------------------------------------------------------------------------------

### C.2 Open items added in v3.1

| ID | Open item | Why it matters | Interim treatment |
|---|---|---|---|
| O-023 | DECIDED 2026-11-18, see D. Is Egyptian ETA e-receipt/e-invoice submission mandatory for Young Go, and does Paymob (or another provider) perform it? | Legal checkout | `EReceiptProvider` adapter disabled; ETA-ready fields stored; ask accountant + Paymob |
| O-024 | DECIDED 2026-11-18, see D. Auto-renew with saved cards (Paymob support + owner approval) | Subscription UX | Manual renewal (R-017) |
| O-025 | DECIDED 2026-11-18, see D. Final retention periods per data class | Privacy | Defaults in PRD YG-GAP-PRV-002 |
| O-026 | DECIDED 2026-11-18 (PO): SKIT UI kit is approximated by the current design tokens; accepted as final for now. Changeable at any time from the token layer (`resources/css/tokens.css`, see `docs/brand/CHANGING_THE_BRAND.md`) | Admin visuals | Filament theme from design tokens |
| O-027 | DECIDED 2026-11-18 (PO): the current brand (BRAND_SHEET, tokens, wordmark, Noto Sans Arabic) is accepted as final for now with no change; colors and fonts are global and changeable from one place (`resources/css/tokens.css`, mirrored by `config/brand.php`, guarded by `app:check-brand-tokens`) | Visual identity | Current tokens; no extraction needed |
| O-029 | DECIDED 2026-11-18, see D. Error-tracking provider (Sentry-compatible or other) | Operations | Reporter adapter logs locally until chosen (ED-021) |
| O-030 | DECIDED 2026-11-18, see D. Expected launch traffic (concurrent learners, peak live sessions) | Capacity sizing | Documented assumption in T-P11-02 |
| O-028 | DECIDED 2026-11-18, see D. Safeguarding message wording and designated recipients for minors | Child safety | AI off for minors |

## D. Decisions approved 2026-11-18 (PO)

The PO approved these on 2026-11-18. The PRD left them OPEN; they are now decided. Items marked "verify" still need legal or
accounting confirmation before go-live. Rows in section C stay for traceability and point here.

| ID | Decision | Basis / market note | Verify with |
|---|---|---|---|
| O-008 | Commission 70% instructor / 30% platform default for independent-instructor courses; per-instructor and per-course rules override; academy-owned courses earn nothing for instructors | `config/commissions.php`; rule precedence unchanged | Accountant |
| O-009 | Bundle earnings allocated proportionally to the list price of the member courses | Current behaviour (`AllocateBundlePrice`) | Accountant |
| O-010 | Subscription: instructor earns 70% of the period price, split equally across the plan's courses, per student-course-period | `RecordOrderEarnings` plan lines; no engagement metric (D-013) | Accountant |
| O-011 | Commission base is net of discount, excluding VAT; platform bears gateway fees; refunds reverse proportionally | Stored components kept separately | Accountant (VAT treatment) |
| O-012 | Settlement monthly; payout by bank transfer (EG also wallet/Instapay; KSA bank transfer); minimum payout threshold configurable (default 500 EGP / 100 SAR); four-eyes: the creator of a batch cannot approve it unless super_admin | `config/commissions.php` `settlement`; `ApproveSettlement` | Finance, accountant |
| O-024 | No auto-renew at launch; manual renewal | R-017 stays | Paymob, later |
| O-017 | No family plan at launch | Not built | Owner, later |
| O-016 | Parent sees the child's help status only, not AI chat history | Privacy default | Legal |
| O-020 | Completion default: all required items done and quiz pass mark 60% (configurable per course) | Per-course setting | Owner |
| O-025 | Keep code defaults; financial records minimum 7 years (EG 5, KSA 6); learning records while the account is active plus 2 years after closure | Longest applicable period wins | Legal, accountant |
| O-015 | Keep current age bands; AI disabled for anyone under 18 until legal review; guardian verification by parent-account link plus OTP | Safe default | Legal (verify) |
| O-028 | Safeguarding messages route to a designated safeguarding role (admin + support); wording is a draft pending legal review | Child safety | Legal (verify) |
| O-018 | Migration scope: courses + content mandatory; users, enrollments, progress, certificates, quiz results, order history yes; AI chat history no | Legacy source still needed (O-003 OPEN) | Owner |
| O-006 | Methods: cards and mobile wallets via Paymob EG; Instapay by manual proof. Credentials still to be supplied | Adapter inert without credentials | Owner (credentials) |
| O-013 | Saudi gateway is Paymob KSA; market stays disabled; entity/tax facts to be supplied | `Market::isActive` unchanged | Legal, accountant (verify) |
| O-014 | AI provider is OpenAI (model configurable); adapter inert without a key; no minor data until legal approval; data region and terms still to confirm | `app/Domain/AI/Providers/OpenAiProvider.php` | Legal (verify), owner |
| O-005 | Video provider: Bunny Stream | `MediaProviders` `bunny` | Owner (account) |
| O-021 | Email: Amazon SES; live sessions: Google Meet | `ses` mailer inert without keys; meet.google.com links accepted | Owner (credentials) |
| O-029 | Error tracking: Sentry SaaS | ED-021 reporter `sentry`, inert without DSN | Owner (DSN) |
| O-030 | Sizing assumption: 2000 registered learners, 300 concurrent peak, 150 concurrent in a live session | Input for T-P11-02 | Owner |
| O-002 | Repository ibrahimmaher2503-source/young-go-lms, branch feat/lms-build | Recorded | None |
| O-004 | Production DB MySQL 8 (SQLite for local/tests), PHP 8.3 | Recorded; note composer platform requirement may differ (R-001 says PHP 8.4) | Owner (hosting) |
| O-023 | ETA e-receipt adapter stays disabled with ETA-ready fields | Obligation unconfirmed | Accountant (verify) |

**Still OPEN (facts needed from the owner):** O-001 DNS/hosting access; O-003 legacy source/DB; O-007 legal seller name, tax id, VAT;
O-019 certificate legal issuer wording; O-022 dates/budget; O-006 Paymob credentials;
O-013 Saudi legal entity.

## E. Decision change protocol

When an open item is resolved, update this file with date, exact owner
decision, impact and affected PRD/DMC/architecture sections. Do not
silently convert a recommendation into an approved decision. Technical
low-risk defaults may be implemented when documented and reversible;
legal, financial, privacy and irreversible choices require owner
approval.
