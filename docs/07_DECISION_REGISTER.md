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

**D-029 · 2026-11-19 · One local workspace.** The owner requested that all project work use one checkout. The canonical path and branch are recorded in [AGENTS.md](../AGENTS.md#local-workspace). This applies to local development, delegated work and reviews.

**D-030 · 2026-11-19 · Learning reminders.** The owner approved notifications 7, 3 and 1 days before access expires or an assignment is due. Existing recipient scope, mandatory access notices and optional assignment reminder preferences apply.

**D-031 · 2026-11-19 · Bundle cohort selection.** The owner approved buyers choosing a cohort for each live course in a bundle. Apply the same rule to the live component of hybrid courses with open cohorts. Selection uses the intended learner's eligibility. Individual course checkout keeps its existing automatic cohort selection.

**D-032 · 2026-11-21 · Accept deferred visual scope.** The owner explicitly accepts the current visual design of every task marked `G7 VISUAL DEFERRED` under D-028 (P0–P7), so visual fidelity and the UX score no longer block `done` or milestone closure for those tasks. Every other applicable gate (G1–G6, G8–G11), the functional browser journeys, accessibility checks, required screenshots and the verification-wave verdicts still apply. A functional defect still blocks closure. Visual polish may be scheduled later as separate work. Later the same day the owner also accepted the current visual design of T-P2-08 (contact enquiries and newsletter), which had not carried the D-028 marker.

**D-033 · 2026-11-21 · Public homepage reference.** Use the owner's [Arabic homepage screenshot](design-system/references/public-home-owner-2026-11-21.png) for the composition and artwork of the public homepage. Keep D-027 branding, bilingual CMS and catalog content, real public data, and the existing signup consent rules. This request applies to the homepage.

**D-034 · 2026-11-21 · Refined public homepage.** The owner supplied a refinement brief superseding D-033's literal composition. Use the same screenshot for content and branding, with a 1200–1280 px RTL desktop grid, restrained surfaces, one hero photograph, equal featured cards, centered instructor profiles, a single free-course photograph and a compact newsletter form. Verify the 1440 px desktop mockup against the brief. D-027 and the CMS and data boundaries still apply.

**D-035 · 2026-11-21 · Homepage scale and public release preparation.** The owner's latest brief sets a 1160 px homepage grid with 32 px desktop gutters and a 460–520 px desktop hero. Use the existing creative studio image with a compact link to a featured course, show available course formats without repeating the statistics, and surface the existing course level and published instructor data when available. Preserve localized navigation, CMS controls, the free-course feature and newsletter consent. Prepare bilingual editorial content for the remaining public pages. Real course and instructor records, official policies and provider activation retain their existing approval and launch gates.

**D-036 · 2026-11-21 · About, Privacy and Contact references.** The owner supplied three Arabic visual references for T-P2-02 and T-P2-08. Match the photo-led layout, section order, Privacy contents and cards, Contact form and channels, and lower banner in the shared public shell. Treat embedded contact details, operating hours, legal copy and form fields as visual examples. Use published CMS copy and configured contact data, and keep the existing inquiry fields, draft gates and newsletter consent. Local source captures are preserved under `storage/app/private/public-info-reference/references`.

**D-037 · 2026-11-21 · Public page vertical spacing.** The owner requested more vertical space between elements following the public-page review. Use shared responsive spacing between page sections, card rows and form groups while preserving the horizontal grids and functional behavior.

**D-038 · 2026-11-21 · FAQ and blog reference layout.** Reuse the Privacy page's photo, hero grid, mask and type scale on the FAQ and blog index. Use the same article hero frame with the saved cover when present. Reuse the contents rail and section cards for CMS questions and the public info bands for blog search and calls to action. Preserve CMS and blog publishing, search and localized URLs.

**D-039 · 2026-11-21 · Homepage hero frame.** Reuse the existing public information pages' photo mask, tinted backdrop and title accent on the homepage hero. Keep the homepage studio photo, responsive composition, desktop height range, CMS copy and existing course, catalog and learning-steps actions.

**D-040 · 2026-11-21 · Commerce and privacy launch defaults.** The owner accepts the defaults the code uses until changed: commission rates of zero (O-008..O-011), settlement approval without a four-eyes rule (O-012), the configured retention periods (O-025) and the refund revocation policy in `config/refunds.php`. These no longer block T-P8-MG and T-P9-MG. Live Paymob credentials and seller details (B-07, O-006, O-007, O-023), legal wording and real mail delivery remain release inputs for P11.

**D-041 · 2026-11-21 · Homepage content below the hero.** The owner supplied a new visual reference for the statistics, disciplines, featured courses, learning steps, free course, instructors, newsletter and footer. Use the reference's six-discipline row, course grid with an adjacent catalog link, single learning-steps band, outlined photo cards and contrasting newsletter at its layout width. Keep the D-039 hero, the 1160 px maximum homepage grid, mobile reflow, published data, configured social links and explicit newsletter consent. Preserve the source image under `storage/app/private/public-home-content-reference/owner-reference.png`. Example profile photos and ratings do not authorize demo values in public data.

**D-042 · 2026-11-21 · Instructor presentation and shared public navigation.** Use each approved public profile's portrait, localized name and role in the homepage cards, course detail instructor section and profile page. Use generated portraits and bilingual specialties and bios for four demo profiles in the guarded demo dataset, including two additional instructors. Keep uploaded portraits and editor-supplied copy. Expose the instructor section from the shared menu and footer when the homepage can show it. The footer links published policy pages and configured social accounts. Use real profiles and approved portraits in production.

**D-043 · 2026-11-21 · Simplified public navigation.** The owner requested fewer links and removal of repeats across the menu and footer. The brand links to Home. Desktop and mobile menus share Courses and the available homepage Disciplines and Instructors sections. The mobile dialog fits its contents. The footer groups About, Contact and the live Blog under Platform, with FAQ, published policies and cookie settings under Help. Existing public URLs stay available.

**D-044 · 2026-11-21 · Owner-specified public menu order.** Show Courses, Instructors, About us, and Contact us in that order in both desktop and mobile navigation. Use the requested Arabic labels: الدورات، المدربون، من نحن، تواصل معنا. Keep the instructor section and About publication checks. The footer links FAQ, published policies, cookie settings and the live Blog.

**D-045 · 2026-11-21 · Disciplines and restored footer links.** Replace Instructors with Disciplines in the desktop and mobile menus. Disciplines links to the visible homepage section. Restore course and discipline links under Explore, About and Contact under Platform, and published FAQs and policies under Help. Show Blog under Explore when a live article exists. Remove the footer cookie settings link. The consent interface retains its settings access when active.

**D-046 · 2026-11-21 · Remove Disciplines from the menu.** The desktop and mobile menus list Courses, published About us, and Contact us. The footer retains Disciplines under Explore when the homepage section is available.

**D-047 · 2026-11-21 · Full-width public bands and three-column footer.** Public information heroes, promo banners and catalog headers can span the viewport with their contents aligned to the public grid. The shared footer uses three desktop columns: brand and configured social links, Explore, and Platform with the published FAQ. Keep published policies in the bottom row. Remove newsletter signup from the footer only.

**D-048 · 2026-11-21 · Footer destinations from the owner reference.** Keep the D-047 layout. Explore links Courses, available homepage disciplines and instructors, and the public Blog. Platform links published About, its features section, sellable plans, Contact, published FAQ and the support section on Contact. The bottom row links published policies and restores the Cookie Preferences page shown in the owner's reference. Publication and commerce eligibility rules remain in effect.

**D-049 · 2026-11-21 · Shorter catalog filter panels.** Show category, age band, level, course format and access as the primary filters. Group language, instructor, availability, price range and exact age under More filters on desktop and mobile. Expand the group when an additional filter is active. Keep the existing filter parameters and behavior.

**D-050 · 2026-11-21 · Center the public footer.** Align the whole footer surface with the shared site container, including its background and decoration. Keep the D-047 columns, D-048 destinations and space for the sticky mobile purchase action.

**D-051 · 2026-11-21 · Official logo and public palette.** The owner directed use of the logo from [youngoschool.com](https://youngoschool.com/). Bundle the official logo and favicon locally. Use the mark's violet and magenta for the public and entry-screen palette in both themes. Preserve operational workspaces, standalone documents and functional status colors.

**D-052 · 2026-11-21 · Configurable email verification after signup.** Staff with the existing site-settings permission can turn off the automatic verification email and the post-signup verification prompt. The default is on. The change is audited. Existing signed verification and resend routes, actual verification timestamps, guardian and instructor requirements, staff two-factor authentication, and consent versions retain their behavior.

**D-053 · 2026-11-22 · Add User in the staff users header.** Replace the Users list CSV action with Add User. Use the existing user-management permission and audit account creation. The new account starts without roles or a verification or consent record. Its owner sets the password through the existing recovery flow. Keep existing role-assignment permissions and the shared CSV action for other workspaces.

**D-054 · 2026-11-22 · Remove the standalone Learner Devices page.** Remove the route and navigation entry. Move the confirmed, audited device reset to the Users row actions for eligible learner accounts, using the existing user-management permission. Keep device and session limits and per-learner device details in Learner 360.

**D-055 · 2026-11-22 · Add notification template from the list header.** Add Template creates a wording override for an editable matrix event that has no saved custom wording. Use the existing permissions, bilingual fields, validation and audit. Saved overrides remain editable from their row. Event definitions and delivery behavior stay in the existing matrix.

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
| O-014 | AI provider is OpenAI (model configurable); adapter inert without a key; no minor data until legal approval; data region and terms still to confirm | `app/Support/AI/OpenAiProvider.php` | Legal (verify), owner |
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

**2026-11-19 owner follow-up.** The owner said they cannot provide the requested
inputs at present. The legacy hostname/DNS and authoritative read-only source,
database and storage (O-001/O-003), Paymob credentials and Egyptian seller/tax/VAT
details (O-006/O-007), Saudi entity (O-013), OpenAI access, region and terms
(O-014), certificate issuer wording (O-019), dates and budget (O-022), and the
accountant's ETA determination (O-023) remain unavailable. Keep the affected
activation and migration work pending. The approved ETA-off default and the
O-025 retention policy above still apply.

## E. Decision change protocol

When an open item is resolved, update this file with date, exact owner
decision, impact and affected PRD/DMC/architecture sections. Do not
silently convert a recommendation into an approved decision. Technical
low-risk defaults may be implemented when documented and reversible;
legal, financial, privacy and irreversible choices require owner
approval.
