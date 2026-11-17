# YOUNG GO LMS --- LEGACY COURSE MIGRATION PLAN

**Version:** 1.1 · **Date:** 16 November 2026\
**Status:** Migration of all existing courses and related course content
is approved. Field-level migration of all learner/business history
requires source inventory and owner sign-off.

## 1. Objective

Move the existing Young Go course library into the custom LMS while
preserving course structure, media references, learning materials,
metadata and student-facing experience. The legacy platform remains live
until the new platform is accepted and final cutover is approved.

## 2. Mandatory migration scope

For every existing course, inventory and migrate where present: - Course
title and translations (Arabic/English), slug and short URL. - Short
description, rich description, objectives/outcomes and requirements. -
Category/subcategory, level, language, format, age suitability if
available. - Owner/instructor assignment and approval/publishing
state. - Price, free/paid/subscription mode, access duration and
featured flag. - Course overview provider/link, preview video and
thumbnail. - FAQs and Arabic/English course information. - SEO title,
keywords, meta description and URL mapping. - Sections, section order,
lesson order and curriculum item type. - Lesson title, description,
duration, preview/free state, status and media source. -
YouTube/Vimeo/MP4/Wasabi/Google Drive/audio/document/text/image/iframe
references where present. - Lesson resources, materials folders and
dedicated curriculum PDFs. - Quizzes, instructions, duration, marks,
pass score, attempts, questions, options, correct keys and progression
rules. - Assignments, instructions, due dates, submission types and
grading rules. - Live sessions, provider/link, schedule, attendance and
replay references where available. - Certificates/templates and
verification records where included in approved migration scope.

## 3. Related historical data --- inventory and sign-off

Assess and propose migration for: - User accounts and profile data. -
Parent/guardian relationships if any exist in source. -
Active/historical enrollments and manual grants. - Student progress and
last video positions. - Quiz attempts/results and assignment
submissions/feedback. - Orders, payment records, Instapay/manual
approvals and refunds. - Certificates and reviews.

The owner explicitly confirmed migration of existing courses and all old
courses. Exact scope of personal, financial and learner-history records
must be confirmed after source inspection, including data quality,
consent and schema feasibility. Do not silently omit active learner
access or claim historical data migrated without reconciliation.

## 4. Migration method

1.  Read-only discovery of legacy application, database, files and
    provider references.
2.  Build source inventory with stable source IDs and counts.
3.  Create source-to-target mapping for each entity and field.
4.  Identify unsupported media, missing PDFs, broken URLs, duplicate
    records and invalid data.
5.  Build repeatable import commands/scripts with dry-run, logs,
    checkpoints and idempotency.
6.  Import to staging; never first import directly to production.
7.  Reconcile source/target counts and sample course journeys.
8.  Fix mapping issues and rerun safely.
9.  Agree data freeze/cutover window; export final delta.
10. Take verified backup/snapshot of legacy and new systems.
11. Import final delta and reconcile again.
12. Switch existing domain only after owner acceptance.
13. Keep legacy system read-only/available for agreed rollback period.

## 5. URL and media continuity

-   Preserve existing course URLs where feasible.
-   If slugs/routes change, create explicit redirects and retain a
    mapping table.
-   External video links are preserved only if playback and permissions
    remain valid.
-   Private uploaded media is copied to new approved storage and
    validated.
-   Do not assume Google Drive or Vimeo links remain accessible to all
    users; verify permissions.
-   Record every media item as migrated, reused, replaced or exception.

## 5A. v3.1 mapping rules

-   Every migrated media item gets a protection tier (T1/T2/T3). Paid lessons whose legacy source is T3 (YouTube unlisted, Google Drive, public URL) are imported with a `migration_exception` and listed for owner decision: re-host to T1, keep with exception, or make free.
-   Legacy single-name users map to `name_ar` or `name_en` by script detection (Arabic letters → `name_ar`); the other field stays empty for the user to complete.
-   Legacy birth dates, if present, are reduced to year/month on import.
-   Legacy live groups/batches map to cohorts where identifiable.
-   Legacy manual grants map to unified entitlements with source `legacy_migration` + original grant metadata.
-   Searchable fields are re-normalized after import (`search_text`).
-   Import commands follow the agent task specs in `agents/tasks/P10.md`.

## 6. Reconciliation report

For each migration batch, report source/target course counts;
source/target section and lesson counts; media/resource/PDF counts and
failures; quiz/question/assignment counts;
user/enrollment/progress/financial counts if included;
duplicates/skipped rows with reason; broken/unsupported links; sampled
playback/download/assessment results; and owner decisions required.

## 7. Acceptance

Migration is accepted only when every source course has a target course
or documented exception; section/lesson hierarchy and order match;
media/resources are accessible under target permissions; bilingual
metadata and course settings are correct; counts reconcile; existing
active learner access is not silently broken; and a final
backup/rollback reference is recorded.
