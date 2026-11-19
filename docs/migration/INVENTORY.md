# Legacy source inventory

T-P10-01 · 2026-11-19 · Local source preparation

## Source and scope

Candidate checkout `C:/projects/yango/YounGo_School`, committed revision `54925dc1943e0ccce1ba658d87eaea96b09643ea`. Evidence comes from table and key definitions in `uploads/install.sql` and the application code at that revision. The installer is a schema reference. The source revision deployed in production, the authorized database snapshot or replica, its capture date, enabled add-ons, and the corresponding storage manifest are all **UNKNOWN**. The working tree has local edits to `Crud_model.php`. The observations below use its committed version. No database connection, source records, storage inspection, or import was needed for this preparation.

[PRD §25](../01_MASTER_PRD_YOUNG_GO_LMS.md#25-migration-and-cutover-requirements), [Migration Plan §4](../08_LEGACY_MIGRATION_PLAN.md#4-migration-method), and [D-004, O-003 and O-018](../07_DECISION_REGISTER.md) govern this inventory. All legacy courses and their content are mandatory. O-018 includes users, enrollments, progress, certificates, quiz results and order history and excludes AI chat history. Field-level learner and financial history mapping still depends on source inspection and owner sign-off.

## Candidate counts and source IDs

Every result is **UNKNOWN**. The following statements are proposed for a verified, read-only MySQL-compatible snapshot after comparing its table and column metadata against the installer. Use the source table and its key together to identify a record. The cited primary keys are declared by the installer and still need confirmation on the snapshot.

| Entity | Candidate source ID and evidence | Read-only count query |
|---|---|---|
| Courses | `course.id` (`uploads/install.sql:261,2002`), including all statuses | `SELECT COUNT(*) FROM course` |
| Categories and subcategories | `category.id`, `parent` links the hierarchy (`uploads/install.sql:160,1977`) | `SELECT parent, COUNT(*) FROM category GROUP BY parent` |
| Ratings and reviews | `rating.id`, linked through `ratable_type` and `ratable_id` (`uploads/install.sql:1649,2141`) | `SELECT ratable_type, COUNT(*) FROM rating GROUP BY ratable_type` |
| Thumbnail references | `course.id` for each populated thumbnail field (`uploads/install.sql:278`); filename generation can also use the course ID, theme and modification time (Crud_model.php:762–770,984–998). Reconcile these paths with storage even if the field is empty | `SELECT COUNT(*) FROM course WHERE NULLIF(TRIM(thumbnail), '') IS NOT NULL` |
| Sections | `section.id`, by `course_id` and `order` (`uploads/install.sql:1705,2159`, `Crud_model.php:1452`) | `SELECT COUNT(*) FROM section` |
| Lessons and quiz rows | `lesson.id`, by `course_id`, `section_id` and `order`; quizzes use `lesson_type = 'quiz'` (`uploads/install.sql:1326,2056`, `Crud_model.php:3497`) | `SELECT lesson_type, COUNT(*) FROM lesson GROUP BY lesson_type` |
| Lesson attachments and PDF candidates | `lesson.id`; `attachment` holds text, iframe input, an uploaded filename or quiz JSON depending on type (`Crud_model.php:1818,3497`) | `SELECT lesson_type, attachment_type, COUNT(*), SUM(LOWER(TRIM(attachment)) LIKE '%.pdf') AS pdf_suffix_rows FROM lesson WHERE NULLIF(TRIM(attachment), '') IS NOT NULL GROUP BY lesson_type, attachment_type` |
| Resource files and PDF candidates | `resource_files.id`, joined to `lesson_id` (`uploads/install.sql:1667,2147`, `Admin.php:3017`) | `SELECT COUNT(*) AS resource_rows, COUNT(CASE WHEN LOWER(TRIM(file_name)) LIKE '%.pdf' THEN 1 END) AS pdf_suffix_rows FROM resource_files` |
| Quiz definitions and questions | `lesson.id` for a quiz and `question.id` joined by `quiz_id` (`Crud_model.php:3497,3539`, `uploads/install.sql:1612,2129`) | `SELECT COUNT(*) FROM lesson WHERE lesson_type = 'quiz'` and `SELECT COUNT(*) FROM question` |
| Quiz result records | `quiz_results.quiz_result_id`; `watch_histories.quiz_result` is a second representation requiring reconciliation (`uploads/install.sql:1630,2135`, `Home.php:1395,1656`) | `SELECT COUNT(*) FROM quiz_results` and `SELECT COUNT(*) FROM watch_histories WHERE NULLIF(TRIM(quiz_result), '') IS NOT NULL AND TRIM(quiz_result) NOT IN ('[]', '{}', 'null')` |
| Assignments and submissions | **UNKNOWN**. No confirmed table, key, or lesson encoding in the reviewed candidate source. | Await snapshot metadata and matching add-on code. |
| BigBlueButton meeting records | `bbb_meetings.id`; the code reads and updates by `course_id` (`uploads/install.sql:86,1953`, `Admin.php:3233`) | `SELECT COUNT(*) FROM bbb_meetings` |
| Zoom live-class records | `live_class` is used by code with a `course_id` lookup; table definition and stable key **UNKNOWN** (`Api_instructor_model.php:1353`) | `SELECT COUNT(*) FROM live_class` if present. Confirm whether separate records exist for scheduled sessions, attendance and replays. |
| Users | `users.id` (`uploads/install.sql:1867,2183`) | `SELECT COUNT(*) FROM users` |
| Enrollments | `enrol.id` with `course_id`, `user_id`, `expiry_date` and `gifted_by` (`uploads/install.sql:529,2026`) | `SELECT COUNT(*) FROM enrol` |
| Manual access grants | The manual path writes to `enrol`, as do purchases and free enrollment. `gifted_by = 0` is shared with ordinary purchase. No independent key or reliable origin predicate evidenced (`Crud_model.php:2677–2797`). | **UNKNOWN**. Reconcile any available grant audit or export before counting manual rows. |
| Progress and playback position | `watch_histories.watch_history_id` and `watched_duration.watched_id` (`uploads/install.sql:1915,2195 and 1899,2189 respectively`) | `SELECT COUNT(*) FROM watch_histories` and `SELECT COUNT(*) FROM watched_duration` |
| Payments and order history | `payment.id`; aggregate by `payment_type` (`uploads/install.sql:1515,2104`, `Crud_model.php:356`). Offline payment add-on call is present (`Home.php:446`). Its table/key, and any separate orders, refund or manual approval records, are **UNKNOWN**. | `SELECT payment_type, COUNT(*) FROM payment GROUP BY payment_type` |
| Certificates | `certificates` is used by code with a course/student lookup and a `shareable_url`; table definition and stable key **UNKNOWN** (`Api_model.php:977–1006`). | `SELECT COUNT(*) FROM certificates` if present. Confirm template and verification sources separately. |

Source code paths above are under `application/models` or `application/controllers`. PDF suffix counts are leads for reconciling with storage metadata. Dedicated course-level curriculum PDFs and materials folders remain **UNKNOWN**. A meeting record can hold a provider configuration without proving that a session ran. Parent links and any reusable access or payment audit references also need snapshot confirmation.

## Media provider counts

The installer defines the fields below in `course` and `lesson`. `Crud_model.php:709–719,1489–1841` shows providers and storage choices such as YouTube, Vimeo, Academy Cloud, HTML5, Google Drive, Amazon, system uploads, Wasabi S3 and generic URLs. The Wasabi video path stores a generic `video_type` of `url` alongside `lesson_type = wasabi`; use both fields to preserve that attribution. Count each source field separately so a mobile alternate cannot inflate lesson counts. Missing providers go into an explicit `unknown` group. Report the groups by source channel and reconcile references with approved storage metadata before reporting unique assets or playback availability.

```sql
SELECT 'lesson_web' AS source,
       CASE WHEN lesson_type = 'wasabi' THEN 'wasabi_s3'
            ELSE COALESCE(NULLIF(LOWER(TRIM(video_type)), ''), 'unknown') END AS provider,
       COUNT(*) AS reference_rows
FROM lesson
WHERE (LOWER(TRIM(video_type)) = 'academy_cloud' AND cloud_video_id > 0)
   OR (COALESCE(LOWER(TRIM(video_type)), '') <> 'academy_cloud' AND NULLIF(TRIM(video_url), '') IS NOT NULL)
GROUP BY provider
UNION ALL
SELECT 'lesson_mobile',
       CASE WHEN TRIM(video_url_for_mobile_application) = 'https://www.html5rocks.com/en/tutorials/video/basics/devstories.webm' THEN 'demo_placeholder'
            WHEN LOWER(TRIM(video_type)) = 'amazon' AND TRIM(video_url_for_mobile_application) = TRIM(video_url) THEN 'amazon'
            ELSE COALESCE(NULLIF(LOWER(TRIM(video_type_for_mobile_application)), ''), 'unknown') END, COUNT(*)
FROM lesson
WHERE NULLIF(TRIM(video_url_for_mobile_application), '') IS NOT NULL
GROUP BY 2
UNION ALL
SELECT 'lesson_audio',
       CASE WHEN audio_url LIKE '%/uploads/lesson_files/audios/%' THEN 'system_path_candidate' ELSE 'unknown' END, COUNT(*)
FROM lesson
WHERE NULLIF(TRIM(audio_url), '') IS NOT NULL
GROUP BY 2
UNION ALL
SELECT 'course_overview', COALESCE(NULLIF(LOWER(TRIM(course_overview_provider)), ''), 'unknown'), COUNT(*)
FROM course
WHERE NULLIF(TRIM(video_url), '') IS NOT NULL
GROUP BY 2;
```

The mobile video default is a bundled demo URL (Crud_model.php:1552–1565). Exclude its bucket from content totals. The Amazon upload path writes the same object URL for web and mobile under different player labels (Crud_model.php:1611–1624). Other mobile groups represent the stored player label until the storage provider is verified. Audio upload paths are candidates for local storage; confirm the source host and file manifest. The edit path also writes `audio_url_for_mobile_application` (Crud_model.php:2172–2179), a field absent from the installer. Check the snapshot schema before counting it. Reconcile stale or inconsistent cloud IDs and URLs separately.

## Inputs still required

Confirm O-003 with an authoritative source revision, a read-only snapshot tied to its capture date, and a matching storage/provider manifest. Verify the primary keys, add-on schemas and the expected file locations before running the aggregate queries. Record the exact executed statements and counts from the same snapshot, including unsupported or missing items. Source permissions, installed add-ons, stored file existence and provider availability remain **UNKNOWN**. T-P3-MG is still open. T-P10-01 A1 requires the actual counted inventory.
