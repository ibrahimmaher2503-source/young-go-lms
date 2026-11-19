# Legacy Source-to-Target Mapping Draft

**Status:** Pre-work draft (`T-P10-02` remains `todo`, blocked on `O-003`) · **Date:** 2026-11-19  
**Governing sources:** `docs/migration/INVENTORY.md`, `docs/08_LEGACY_MIGRATION_PLAN.md`, `docs/LEGACY_PARITY.md`, `docs/07_DECISION_REGISTER.md` (`D-004`, `O-003`, `O-018`), `docs/05_ARCHITECTURE_DATA_API.md`  
**Candidate schema reference:** `C:/projects/yango/YounGo_School/uploads/install.sql` at revision `54925dc1943e0ccce1ba658d87eaea96b09643ea`

> [!IMPORTANT]
> Every source table, column, data type, encoding, and behavior below is marked **`ASSUMPTION`** because it is inferred from the candidate installer (`install.sql`) and application code, not from the live production database or storage manifest. Every unverified live fact (add-on tables, actual data formats, stored file paths, live hostname) is marked **`UNKNOWN`**.

---

## 1. Scope & Topological Execution Order

Per **D-004** (all legacy courses and content mandatory) and **O-018** (2026-11-18 PO decision: migrate users, enrollments, progress, certificates, quiz results, and order history; exclude AI chat history):

| Order | Entity key | Source table (`ASSUMPTION`) | Target model(s) | Scope status (O-018 / D-004) |
|---:|---|---|---|---|
| 1 | `users` | `users`, `role` | `App\Models\User`, Spatie `roles`, `App\Models\InstructorProfile` | In scope |
| 2 | `categories` | `category` | `App\Models\Category` | In scope |
| 3 | `courses` | `course` | `App\Models\Course`, `App\Domain\Commerce\CoursePrice`, `course_instructors` | In scope |
| 4 | `sections` | `section` | `App\Models\CourseSection` | In scope |
| 5 | `lessons` | `lesson` (`lesson_type != 'quiz'`) | `App\Models\Lesson`, `App\Models\CurriculumItem`, `App\Models\MediaAsset` | In scope |
| 6 | `resources` | `resource_files` | `App\Models\LessonResource` | In scope |
| 7 | `quizzes` | `lesson` (`lesson_type = 'quiz'`), `question` | `App\Models\Quiz`, `App\Models\CurriculumItem`, `App\Models\Question`, `App\Models\QuestionOption` | In scope |
| 8 | `live_sessions` | `bbb_meetings`, `live_class` (`UNKNOWN` schema) | `App\Models\LiveSession`, `App\Models\CurriculumItem` | In scope |
| 9 | `enrollments` | `enrol` | `App\Domain\Entitlements\Actions\GrantEntitlement` (`EntitlementSource::LegacyMigration`) | In scope (I01) |
| 10 | `progress` | `watch_histories`, `watched_duration` | `App\Domain\Progress\Actions\ImportLegacyProgress` → `LessonProgress`, `CourseProgress` | In scope |
| 11 | `quiz_results` | `quiz_results` | `App\Models\QuizAttempt` | In scope |
| 12 | `orders` | `payment` | `App\Domain\Commerce\Order`, `App\Domain\Commerce\OrderItem` (read-only history) | In scope |
| 13 | `reviews` | `rating` | `App\Domain\Engagement\CourseReview` | In scope |
| 14 | `certificates` | `certificates` (`UNKNOWN` schema) | `App\Domain\Completion\CourseCompletion`, `App\Domain\Certificates\Certificate` | In scope (optional table) |
| 15 | `redirects` | Derived from mapped entities + static legacy routes | `App\Models\SeoRedirect` | In scope |
| — | Assignments & submissions | `UNKNOWN` | `App\Models\Assignment`, `AssignmentSubmission` | **Deferred / UNKNOWN (`O-003`)** |
| — | AI chat history | N/A | N/A | **Excluded (O-018)** |

---

## 2. Table-by-Table & Column-by-Column Mapping (`ASSUMPTION`)

### 2.1 `users` (+ `role`) → `App\Models\User` & `App\Models\InstructorProfile`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `users.id` | Preserved in `migration_records` (`entity = users`). | Confirm no negative/non-numeric IDs. |
| `first_name`, `last_name` | `varchar(255)` | `User::$name`, `name_ar`, `name_en`, `display_name` | Concatenate `trim("$first_name $last_name")`. Per Migration Plan §5A: if Arabic letters (`\p{Arabic}`) present, populate `name_ar` and leave `name_en = null`; otherwise populate `name_en` and leave `name_ar = null`. `ASSUMPTION`: single-script names in legacy. | Does production have custom `name_ar`/`name_en` or birth-date columns added after `install.sql`? |
| `email` | `varchar(50)` | `User::$email` | Lowercase + trim; reject empty/invalid format (`invalid_email`) or duplicate email mapped to a different source ID (`duplicate_email`). | Are any active accounts (e.g. child accounts) stored without email or with shared parent emails? |
| `phone` | `varchar(255)` | `User::$phone` | Normalize to E.164 via `PhoneNumbers::normalize($phone, 'EG')` (`ASSUMPTION`: default region `EG`). On parse failure, keep `null` and log `warning` exception `unparsed_phone`. | Are Saudi (`SA`) or other international numbers present? |
| `password` | `varchar(255)` | `User::$password` | `ASSUMPTION`: legacy hash algorithm (`sha1`/`md5`/bcrypt) is unverified; set random unusable bcrypt password on initial import (`T-P10-07` password-reset invitation flow). Never update password on reruns. | Should legacy bcrypt hashes (`$2y$`) be verified and preserved if compatible? |
| `role_id`, `is_instructor` | `int(11)` | Spatie `roles` (`admin`, `instructor_independent`, `student`) | `ASSUMPTION`: `role_id = 1` → `admin`; `is_instructor = 1` → `instructor_independent`; else `student`. | Confirm custom roles in legacy `role` table (`role_id` values in production). |
| `status` | `int(11)` | `User::$status` | `ASSUMPTION`: `1` → `'active'`, `0` or other → `'suspended'`. | Confirm whether `status = 0` means unverified email vs disabled/banned. |
| `verification_code` | `longtext` | `User::$email_verified_at` | `ASSUMPTION`: if `status = 1` and (`verification_code` empty or verified), set `email_verified_at = date_added` (else `null`). | Confirm how verified accounts are marked in production. |
| `biography`, `title`, `skills`, `social_links`, `image` | `longtext` / `varchar` | `InstructorProfile` (`bio`, `headline`, `avatar`, `slug_ar`/`slug_en`, `status`) | When `is_instructor = 1`, create/update `InstructorProfile` (`status = 'approved'` if active instructor, else `'draft'`). `ASSUMPTION`: `title` → `headline`, `biography` → `bio`. | Confirm whether legacy `image` filenames live in `uploads/user_image/{id}.jpg`. |
| `address` | `varchar(255)` | Metadata / unmapped | Not mapped to a public user column; optionally preserve in `migration_records.metadata` only if required. | Confirm if any PII retention rule requires dropping legacy `address`. |
| `wishlist` | `longtext` | `wishlists` (deferred/optional) | `ASSUMPTION`: JSON array of course IDs; map after courses if retained. | Confirm whether legacy `wishlist` JSON is clean integers. |
| `payment_keys` | `longtext` | Sensitive — DO NOT IMPORT blindly | Contains legacy payout keys; inspect during O-003 before deciding encrypted `instructor_payout_profiles` migration. | What payout data is stored in `payment_keys`? |
| `sessions`, `temp` | `longtext` | **Excluded** | Active sessions are invalidated; users sign in cleanly on the new platform. | None. |
| `date_added`, `last_modified` | `int(11)` | `User::$created_at`, `updated_at` | Convert positive Unix seconds to UTC `CarbonImmutable`. | Confirm server timezone of legacy timestamps (assumed UTC Unix epoch). |
| *(Absent in `install.sql`)* | `UNKNOWN` | `User::$birth_year`, `birth_month` | Per §5A: if a birth-date column exists in production, reduce to `(year, month)` only. | Does production `users` or an add-on store birth dates or parent/guardian links? |

---

### 2.2 `category` → `App\Models\Category`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `categories.id` | Parents (`parent = 0` or `null`) must import before children. | Confirm no orphaned/circular `parent` chains. |
| `name` | `varchar(255)` | `Category::$name` (`json`), `search_text` | Reject empty (`empty_name`). Detect script (Arabic vs Latin) to fill `{ar}` or `{en}`; `HasSearchText` auto-fills `search_text`. | Are bilingual names stored in one string (e.g. `"AR \| EN"`) or a single language? |
| `parent` | `int(11)` | `Category::$parent_id` | `0` or `null` → `null`. Otherwise resolve via `categories` ID map (`missing_parent_category` if absent). Reject depth > 2 (`category_depth_exceeded`). | Do any categories exceed 2 levels in production? |
| `slug` | `varchar(255)` | `Category::$slug_ar`, `slug_en` | Normalize via `ScriptNameMapper::slugify()` + `uniqueSlug()`; preserve legacy `slug` in `migration_records.metadata.legacy_slug` for `/category/{slug}` 301 redirect. | Confirm whether `category.slug` in production is used verbatim in URLs. |
| `font_awesome_class` | `varchar(255)` | `Category::$icon` | Trim; nullable. | Map FontAwesome classes to UI icons or keep raw string? |
| `thumbnail`, `sub_category_thumbnail` | `varchar(255)` | `Category::$image` | `ASSUMPTION`: use `thumbnail` (fallback `sub_category_thumbnail`); reconcile with `uploads/thumbnails/category_thumbnails/` during O-003. | Which directory holds actual category images? |
| `code` | `varchar(255)` | `migration_records.metadata` | Store in migration metadata (no `code` column on target `categories`). | Is `code` referenced anywhere externally? |
| `date_added`, `last_modified` | `int(11)` | `created_at`, `updated_at` | Unix timestamp → UTC. | None. |

---

### 2.3 `course` → `App\Models\Course`, `App\Domain\Commerce\CoursePrice`, `course_instructors`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `courses.id` | Also generates `legacy_slug` for URL redirects. | Confirm whether any course URLs use custom slugs outside `course.title`. |
| `title` | `varchar(255)` | `Course::$title` (`json`), `slug_ar`, `slug_en` | Reject empty (`empty_title`). Script-detect into `{ar|en}`; generate unique `slug_ar` and `slug_en`. | Are bilingual titles stored separately in a translation table/add-on? |
| `short_description` | `longtext` | `Course::$short_description` (`json`) | Strip HTML / trim, store under detected locale. | Confirm max length and HTML usage in legacy `short_description`. |
| `description` | `longtext` | `Course::$description` (`json`) | Sanitize with `App\Domain\Catalog\CourseDescription::clean()` and store under detected locale. | Are embedded images or shortcodes inside legacy HTML descriptions? |
| `outcomes` | `longtext` | `Course::$outcomes` (`json`) | `ASSUMPTION`: JSON array of strings (or newline-separated) → `[['text' => [$locale => $line]], ...]`. | Confirm exact JSON/delimited format in live DB. |
| `requirements` | `longtext` | `Course::$requirements` (`json`) | `ASSUMPTION`: JSON array of strings → `[['text' => [$locale => $line]], ...]`. | Same as above. |
| `faqs` | `text` | `Course::$faqs` (`json`) | `ASSUMPTION`: JSON object `{question: answer}` or list `[{faq_question, faq_answer}]` → `[['question' => [$locale => $q], 'answer' => [$locale => $a]], ...]`. | Confirm exact JSON key structure in `course.faqs`! |
| `language` | `varchar(255)` | `Course::$content_language` | `ASSUMPTION`: `'arabic'`/`'ar'` → `'ar'`, `'english'`/`'en'` → `'en'`, `'both'` → `'both'`, else `'ar'`. | List distinct values of `course.language` in production. |
| `category_id`, `sub_category_id` | `int(11)` | `Course::$category_id` | Use mapped `sub_category_id` if > 0 else mapped `category_id`. Reject (`missing_category`) if unmapped. | Are courses ever linked only to a deleted category? |
| `level` | `varchar(50)` | `Course::$level` | Map to `['beginner', 'intermediate', 'advanced', 'all']` (`ASSUMPTION`: unknown → `'all'`). | List distinct `level` values in production. |
| `user_id`, `creator`, `is_admin`, `multi_instructor` | `varchar(255)` / `int` | `Course::$owner_type`, `owner_instructor_id`, `course_instructors` | `is_admin == 1` → `owner_type = 'academy'`; else `'independent'`. `user_id` may be a single ID or comma-separated IDs (`multi_instructor = 1`, `ASSUMPTION` from `Crud_model.php`); first mapped user → `owner_instructor_id`, all mapped users attached in `course_instructors` (`scope = 'manage'`). | Confirm exact `user_id` format when `multi_instructor = 1`. |
| `price`, `discount_flag`, `discounted_price`, `is_free_course` | `double` / `int` | `Course::$access_mode`, `CoursePrice` (`EG`, `EGP`, `amount_minor`) | `is_free_course == 1` or effective price `<= 0` → `access_mode = 'free'` (no `CoursePrice` row or 0). Paid courses → `access_mode = 'paid'`, effective price `(discount_flag == 1 && discounted_price > 0) ? discounted_price : price` converted to integer piasters (`amount_minor = (int) round($val * 100)`). Negative price rejected (`invalid_price`). | What currency is stored in legacy `course.price` (EGP vs USD)? Are any courses subscription-only? |
| `thumbnail`, `upcoming_image_thumbnail` | `varchar(255)` | `Course::$thumbnail_path` | Map path candidate (`thumbnail ?: upcoming_image_thumbnail`); reconcile file existence per `INVENTORY.md` note on dynamic filename generation. | Are course thumbnails stored by filename in DB or computed by `{id}.jpg`? |
| `video_url`, `course_overview_provider` | `varchar(255)` | `Course::$overview_video_provider`, `overview_video_url` | Normalize provider (`youtube`/`vimeo`/`html5` → `external_url`). | Confirm playback validity of legacy promo videos. |
| `is_top_course` | `int(11)` | `Course::$featured` | `(int) is_top_course === 1`. | None. |
| `status` | `varchar(255)` | `Course::$status` (`CourseStatus`) | `active`→`published`, `pending`→`pending_review`, `private`→`private`, `upcoming`→`upcoming`, `inactive`/`draft`→`draft` (`ASSUMPTION`). | List distinct `course.status` values in production. |
| `meta_keywords`, `meta_description` | `longtext` | `Course::$seo_keywords`, `seo_description` | Store in detected locale JSON. Also merge `seo_fields` add-on table if populated. | Does production use `course.meta_*` or `seo_fields`? |
| `enable_drip_content` | `int(11)` | `Course::$sequence_mode` | `ASSUMPTION`: `1` → `'sequential'`, `0` → `'free'`. | Confirm whether legacy drip means sequential lesson completion vs date-based section unlock. |
| `expiry_period` | `int(11)` | `Course::$access_duration_days` | `ASSUMPTION`: Academy LMS stores `expiry_period` in months (`> 0 ? $months * 30 : null`). | Confirm unit of `expiry_period` (months vs days vs lifetime null). |
| `course_type` | `varchar(255)` | `Course::$format` | `ASSUMPTION`: `'general'`/`'recorded'` → `'recorded'`, `'live'` → `'live'`, `'scorm'` → flag exception (`scorm_unsupported`). | List distinct `course.course_type` values in production. |
| `section`, `publish_date`, `date_added`, `last_modified` | `longtext` / `varchar` / `int` | `Course::$created_at`, `updated_at`; `section` JSON ignored in favor of `section.course_id` + `order` | Ignore `course.section` JSON ordering column when `section` table exists (reconcile any drift as warning). | Does `course.section` JSON differ from `section.order`? |

---

### 2.4 `section` → `App\Models\CourseSection`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11)` | `migration_records.source_id` → `course_sections.id` | Primary key. | None. |
| `course_id` | `int(11)` | `CourseSection::$course_id` | Resolve via `courses` ID map; reject `missing_course` if unmapped. | None. |
| `title` | `varchar(255)` | `CourseSection::$title` (`json`) | Reject empty (`empty_title`); script-detect `{ar\|en}`. | None. |
| `order` | `int(11)` | `CourseSection::$sort` | Integer cast `max(0, (int) $order)`. | Compare against `course.section` JSON array order during O-003. |
| `start_date`, `end_date`, `restricted_by` | `varchar(255)` | Warning exception / future item `unlock_rule` | Target `course_sections` has no drip columns (drip lives on `curriculum_items`). Record `warning` exception `section_drip_metadata_unmapped` if non-empty. | Are section-level `start_date`/`restricted_by` drip rules used in production? |

---

### 2.5 `lesson` (non-quiz) → `App\Models\Lesson`, `App\Models\CurriculumItem`, `App\Models\MediaAsset`

Rows where `COALESCE(LOWER(TRIM(lesson_type)), '') <> 'quiz'`:

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `lessons.id` | Store `curriculum_item_id` and `media_asset_id` in `migration_records.metadata`. | None. |
| `course_id`, `section_id` | `int(11)` | `CurriculumItem::$course_id`, `section_id` | Reject `missing_course` or `missing_section` if unmapped. | Are any lessons orphaned from sections? |
| `title` | `varchar(255)` | `Lesson::$title` (`json`) | Reject empty (`empty_title`); script-detect `{ar\|en}`. | None. |
| `summary` | `longtext` | `Lesson::$description` (`json`) | Clean/trim text, store in detected locale JSON. | Does `summary` contain HTML? |
| `duration` | `varchar(255)` | `Lesson::$duration_seconds`, `MediaAsset::$duration_seconds` | Parse `HH:MM:SS` / `MM:SS` / integer seconds into `int >= 0`. | Confirm format in production. |
| `is_free` | `int(11)` | `Lesson::$is_preview` | `(int) is_free === 1`. | None. |
| `order` | `int(11)` | `CurriculumItem::$sort` | **ED-002:** ordering lives ONLY on `curriculum_items.sort`. | Verify `order` matches intended display order. |
| `lesson_type`, `video_type`, `cloud_video_id`, `video_url`, `audio_url`, `attachment`, `attachment_type`, `caption` | `varchar` / `longtext` / `int` | `Lesson::$type`, `Lesson::$content`, `MediaAsset` (`provider`, `kind`, `external_id`, `url`, `path`, `protection_tier`, `exception_reason`, `metadata`) | Classified by `MediaTierClassifier` (see §3 below). Text lessons wrap sanitized text in Tiptap JSON via `RichContent::clean()`. Captions (`caption`) queued for `SubtitleTrack` inspection during O-003. | Which cloud provider powers `academy_cloud` (`cloud_video_id`)? Are Wasabi objects private or public? Where are `caption` VTT/SRT files stored? |
| `video_type_for_mobile_application`, `video_url_for_mobile_application`, `duration_for_mobile_application` | `varchar(255)` | `MediaAsset::$metadata` (or excluded placeholder) | Per `INVENTORY.md`: if `video_url_for_mobile_application` equals `https://www.html5rocks.com/en/tutorials/video/basics/devstories.webm`, exclude from asset count and emit `warning` exception `demo_mobile_placeholder`. | Does production `lesson` have `audio_url_for_mobile_application` (absent from `install.sql`)? |

---

### 2.6 `resource_files` → `App\Models\LessonResource`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11)` | `migration_records.source_id` → `lesson_resources.id` | Primary key. | Confirm whether legacy `lesson.attachment` (when `lesson_type` is `other` / `pdf` / `document`) also holds downloadable files needing `LessonResource` rows. |
| `lesson_id` | `int(20)` | `LessonResource::$lesson_id` | Resolve via `lessons` ID map; reject `missing_lesson` if absent. | None. |
| `title` | `varchar(255)` | `LessonResource::$title` (`json`) | Fallback to `file_name` if empty; script-detect `{ar\|en}`. | None. |
| `file_name` | `varchar(255)` | `LessonResource::$path`, `type`, `mime`, `size`, `sha256` | Reject empty (`empty_file_name`). Extension `jpg/jpeg/png/webp/gif` → `type = 'image'`, else `'file'`. If `storage_root` configured and file exists, compute `sha256` + `size`; otherwise record `warning` exception `missing_resource_file`. **PDF hook note:** `.pdf` files are flagged with `warning` exception `pdf_curriculum_candidate` (`T-P10-05` reviews them later for `lesson_curriculum_documents`; **no AI action taken here**). | Confirm exact storage directory (`uploads/lesson_files/` vs `uploads/resource_files/`) and obtain storage manifest. |
| `created_at`, `updated_at` | `varchar(255)` | `created_at`, `updated_at` | Parse timestamp string or fallback `now()`. | None. |

---

### 2.7 `lesson` (`lesson_type = 'quiz'`) + `question` → `App\Models\Quiz`, `CurriculumItem`, `Question`, `QuestionOption`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `lesson.id` | `int(11) UNSIGNED` | `migration_records.source_id` → `quizzes.id` | Where `LOWER(TRIM(lesson_type)) = 'quiz'`. Store `curriculum_item_id` in `migration_records.metadata`. | Are there standalone quizzes outside `lesson`? |
| `lesson.course_id`, `section_id`, `order` | `int(11)` | `CurriculumItem` (`itemable_type = 'quiz'`, `sort = order`) | Reject `missing_course` / `missing_section`. | None. |
| `lesson.title`, `summary`, `quiz_attempt`, `attachment` | `varchar` / `longtext` | `Quiz` (`title`, `description`, `max_attempts`, `time_limit_minutes`, `pass_mark_percent`) | `quiz_attempt > 0 ? quiz_attempt : null` → `max_attempts`. Parse `attachment` JSON (`duration` → `time_limit_minutes`, `pass_mark` → `pass_mark_percent`); log `warning` exception `invalid_quiz_metadata_json` if malformed. | Confirm exact JSON keys in `lesson.attachment` for quizzes in production. |
| `question.id`, `quiz_id`, `title`, `type`, `options`, `correct_answers`, `order` | `int` / `longtext` / `varchar` | `Question`, `QuestionOption` | Map `mcq`/`multiple_choice` → `single` (or `multiple` if >1 correct indices), `fill_in_the_blank` → `short_answer`, `true_false` → `true_false`, else `essay`. `ASSUMPTION`: `correct_answers` holds JSON 1-based option indices (`["1"]`). | Confirm 1-based vs 0-based `correct_answers` indexing and HTML inside `question.title` / `options`. |

---

### 2.8 `bbb_meetings` (+ optional `live_class`) → `App\Models\LiveSession` & `CurriculumItem`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `bbb_meetings.id` | `int(20)` | `migration_records.source_id` → `live_sessions.id` | Source ID `bbb:{id}` (or `{id}` when single-table). | Confirm if `live_class` table exists in production and obtain its schema (`O-003`). |
| `course_id` | `int(11)` | `LiveSession::$course_id`, `CurriculumItem::$course_id` | Reject `missing_course` if unmapped; attach to first mapped section of the course (`missing_course_section` if course has none). | Can live sessions belong to a specific legacy batch/cohort (Migration Plan §5A)? |
| `meeting_id`, `instructions` | `varchar` / `longtext` | `LiveSession::$title`, `description`, `meeting_url`, `provider` | Set `provider = 'bbb'` (`manual_zoom` for `live_class`), `meeting_url` encrypted by model cast. Note: `moderator_pw`/`viewer_pw` are **not** stored in public fields. | Are replay URLs or attendance logs stored in any add-on table? |
| `created_at`, `updated_at` | `varchar(100)` | `LiveSession::$starts_at`, `ends_at`, `timezone` | `bbb_meetings` lacks explicit schedule columns; default `starts_at = created_at ?? now()`, `ends_at = starts_at + 1h` and record `warning` exception `live_schedule_missing`. | Confirm actual Zoom/Live class date/time columns in production. |

---

### 2.9 `enrol` → `App\Domain\Entitlements\Actions\GrantEntitlement` (I01)

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `course_access` (`target_id`) | **MUST** write ONLY through `GrantEntitlement` with `EntitlementSource::LegacyMigration` and `source_ref = "legacy:enrol:{id}"`. Suppress `AccessNotice` for `LegacyMigration`. | Confirm `enrol` has no duplicate `(user_id, course_id)` conflict semantics beyond `source_ref`. |
| `user_id` | `int(11)` | `GrantEntitlement` `$learner` | Resolve via `users` ID map; reject `missing_user` if absent. | None. |
| `course_id` | `int(11)` | `GrantEntitlement` `$course` | Resolve via `courses` ID map; reject `missing_course` if absent. | None. |
| `date_added` | `int(11)` | `$startsAt` | Parse Unix UTC timestamp (fallback `now()`). | None. |
| `expiry_date` | `varchar(255)` | `$endsAt` | Null/empty/`'0'` → `null` (lifetime). Positive timestamp/date string → UTC `CarbonImmutable`. Reject if `endsAt <= startsAt` (`expiry_before_start`). | Confirm format of `expiry_date` (Unix int string vs `Y-m-d` vs empty) in production. |
| `gifted_by` | `int(11)` | `$reason` metadata | Record `legacy:enrol:{id}` + `gifted_by:{id}` provenance in `reason`. Per `INVENTORY.md`: `gifted_by = 0` cannot distinguish paid purchase from admin manual grant without an audit export. | Is there a separate manual-grant log or offline-payment approval table in production? |

---

### 2.10 `watch_histories` + `watched_duration` → `LessonProgress` & `CourseProgress`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `watch_histories.watch_history_id` | `int(11)` | `migration_records.source_id` → `course_progress.id` | Primary key for progress batching. | Confirm 1 row per `(student_id, course_id)` in `watch_histories`. |
| `student_id`, `course_id` | `int(11)` | `learner_id`, `course_id` | Reject `missing_user` or `missing_course`. | None. |
| `completed_lesson` | `longtext` | `LessonProgress` (`status = completed`, `completion_source = 'legacy'`) | Parse JSON array of legacy lesson IDs (`[10, 11]`); map each to `CurriculumItem` and call `ImportLegacyProgress`. Unmapped lesson IDs emit `warning` exception `unmapped_progress_lesson`. | Does `completed_lesson` ever include quiz IDs? |
| `watched_duration.current_duration` | `int(20)` | `LessonProgress::$position_seconds`, `furthest_seconds` | Lookup `watched_duration` by `(watched_student_id, watched_course_id, watched_lesson_id)`. | Confirm unit is seconds. |
| `quiz_result` | `longtext` | Warning exception `watch_history_quiz_mismatch` | Per `INVENTORY.md`: flag non-empty `quiz_result` values for reconciliation against `quiz_results`. | Which table (`quiz_results` vs `watch_histories.quiz_result`) is authoritative in production? |
| `watching_lesson_id`, `course_progress`, `completed_date` | `int` / `varchar` | `CourseProgress` (via `RecalculateCourseProgress`) | `RecalculateCourseProgress` recalculates percentage from `curriculum_items`. | None. |

---

### 2.11 `quiz_results` → `App\Models\QuizAttempt`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `quiz_result_id` | `int(11)` | `migration_records.source_id` → `quiz_attempts.id` | Primary key. | Confirm whether multiple rows exist per `(quiz_id, user_id)` (multiple attempts). |
| `quiz_id`, `user_id` | `int(11)` | `QuizAttempt::$quiz_id`, `learner_id` | Reject `missing_quiz` or `missing_user`. | None. |
| `is_submitted` | `int(11)` | Skip / status filter | Skip (`quiz_attempt_not_submitted`) if `is_submitted != 1` (or import only submitted results). | Should in-progress legacy attempts be skipped or closed? |
| `total_obtained_marks` | `double` | `QuizAttempt::$score`, `total`, `percent`, `passed` | `total = max($quiz->totalMarks(), $score)`, `percent = total > 0 ? round($score / $total * 100, 2) : 0`, `passed = $percent >= QuizPassMark::effective($quiz->pass_mark_percent)`. | Confirm whether `total_obtained_marks` is raw points vs percentage. |
| `user_answers`, `correct_answers` | `longtext` | `QuizAttempt::$snapshot`, `settings` | Store sanitized JSON snapshot `{legacy_user_answers, legacy_correct_answers}` on `QuizAttempt`. | Confirm exact JSON schema of `user_answers` and `correct_answers`. |
| `date_added`, `date_updated` | `varchar(100)` | `started_at`, `submitted_at` | Parse to UTC (`submitted_by = 'legacy'`). | None. |

---

### 2.12 `payment` → `App\Domain\Commerce\Order` & `App\Domain\Commerce\OrderItem`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `orders.id` | Reference `'LEG-' . str_pad($id, 10, '0', STR_PAD_LEFT)`, `idempotency_key = "legacy:payment:{$id}"`. **Read-only history:** do NOT fire `PaymentVerified` or `ManualPaymentApproved`! | Does production have separate offline/Instapay, bundle, subscription, or refund tables? |
| `user_id` | `int(11)` | `Order::$payer_id`, `OrderItem::$learner_id` | Reject `missing_user`. | Can a legacy payer have paid for another learner? |
| `course_id` | `int(11)` | `OrderItem::$product_type = 'course'`, `product_id` | Reject `missing_course`. | Can a `payment` row cover multiple courses/bundles? |
| `amount` | `double` | `Order::$subtotal_minor`, `total_minor`, `OrderItem::$unit_price_minor` | Reject negative/null (`invalid_amount`). Convert `(int) round((float) $amount * 100)`, `market = 'EG'`, `currency = 'EGP'`. | Confirm legacy `payment.amount` currency (EGP vs USD) and whether `tax` is inclusive or exclusive. |
| `date_added` | `int(11)` | `Order::$paid_at`, `created_at` | Unix UTC timestamp (`status = 'paid'`). | None. |
| `admin_revenue`, `instructor_revenue`, `instructor_payment_status`, `payment_type`, `transaction_id`, `coupon`, `tax` | `varchar` / `double` / `int` | `migration_records.metadata` | Store provenance snapshot in `migration_records.metadata`. Never mutate frozen totals on paid `Order` reruns (log `paid_order_changed_in_source`). | Should historical instructor payouts (`payout` table) be imported as settled `instructor_ledger_entries` or kept read-only? |

---

### 2.13 `rating` → `App\Domain\Engagement\CourseReview`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int(11) UNSIGNED` | `migration_records.source_id` → `course_reviews.id` | Primary key. | Are duplicate ratings per `(user_id, course_id)` possible in legacy? |
| `ratable_type`, `ratable_id` | `varchar(50)`, `int` | `CourseReview::$course_id` | Reject if `LOWER(TRIM(ratable_type)) !== 'course'` (`unsupported_ratable_type`) or `course_id` unmapped (`missing_course`). | What other `ratable_type` values exist in production? |
| `user_id` | `int(11)` | `CourseReview::$learner_id`, `author_name` | Reject `missing_user`. Derive `author_name` via `App\Domain\Engagement\ReviewerName::for($learner)` (no PII leak). | None. |
| `rating` | `double` | `CourseReview::$rating` | Integer 1..5; reject outside range (`invalid_rating`). | Are fractional ratings (e.g. 4.5) stored? |
| `review` | `longtext` | `CourseReview::$body`, `status` | Trim text; set `status = 'published'`. | Should empty-text star ratings migrate? |

---

### 2.14 `certificates` (Optional / `UNKNOWN` schema) → `CourseCompletion` & `Certificate`

| Source column | Source type (`ASSUMPTION`) | Target model & field | Transformation / rule | Open questions |
|---|---|---|---|---|
| `id` | `int` (`UNKNOWN`) | `migration_records.source_id` → `certificates.id` | Table is marked `tableIsOptional() = true` until O-003 confirms add-on schema. | Confirm exact table name (`certificates` vs `certificate`) and columns (`Api_model.php:977`). |
| `student_id` (`user_id`), `course_id` | `int` | `CourseCompletion`, `Certificate::$learner_id`, `course_id` | Find/create `CourseCompletion` (`source = 'manual'`, `reason = 'legacy_migration'`), reject missing user/course. | Confirm foreign-key column names (`student_id` vs `user_id`). |
| `shareable_url` | `varchar` (`UNKNOWN`) | `Certificate::$token`, `number` | Extract token from `shareable_url`; reject empty (`missing_certificate_token`). Set `number = 'YG-LEG-' . $id`, `active = 1`, snapshot `learner_name` / `course_title_ar` / `course_title_en`, and set `users.certificate_names_locked_at`. | Confirm format of `shareable_url` (token vs full URL) and legacy issue timestamps. |

---

### 2.15 Supporting Legacy Tables (`docs/LEGACY_PARITY.md`)

| Legacy table (`install.sql`) | Target entity / module | Pre-work disposition |
|---|---|---|
| `coupons` | `App\Domain\Commerce\Coupon` | Inspect active promo codes at O-003; out of core P10-04..06 path. |
| `custom_page`, `blogs`, `blog_category`, `tag` | `CmsPage`, `BlogPost`, `BlogCategory`, `Tag` | Optional content import during O-003; `SeoRedirect` covers `/blogs`, `/page/*`. |
| `contact`, `newsletter_subscriber` | `ContactEnquiry`, `NewsletterSubscriber` | Confirm consent/legal migration eligibility with owner (O-025). |
| `applications`, `custom_fields`, `seo_fields` | `InstructorApplication`, `CourseCustomField`, `Course::seo_*` | Inspect row counts in O-003. |
| `settings`, `frontend_settings`, `home_pages` | Settings / CMS (`LEGACY_PARITY.md`) | Handled manually via Settings UI; do not bulk-overwrite new settings. |
| `ci_sessions`, `log`, `comment`, `blog_comments`, `message`, `message_thread`, `notifications`, `language`, `currency`, `payment_gateways`, `addons`, `badges`, `instructor_followings` | **Excluded** | Runtime/legacy-UI artifacts or superseded modules. |

---

## 3. Media Provider Attribution & Protection Tier Matrix (Migration Plan §5A)

| Legacy `lesson_type` / `video_type` | Target `MediaAsset::$provider` | `kind` | Protection Tier | Paid-Course Action (Migration Plan §5A) |
|---|---|---|---|---|
| `system` / uploaded video (`mp4`, `mov`, `webm`) | `signed_storage` | `video` | `T1` | Copy file to private disk & verify SHA-256 (`T-P10-05`). |
| `audio` / `audio_url` (`mp3`, `m4a`, `ogg`, `wav`) | `audio` | `audio` | `T1` | Copy file to private disk & verify SHA-256. |
| `other` / `document` / `pdf` | `document` | `document` | `T1` | Copy file to private disk; queue PDF candidate for `T-P10-05`. |
| `image` | `image` | `image` | `T1` | Copy file to private disk. |
| `text` | `rich_text` | `text` | `T1` | Sanitize text into Tiptap JSON via `RichContent::clean()`. |
| `academy_cloud` (when Bunny/private confirmed) | `bunny` / `external_url` | `video` | `T3` (until signed token verified) | **Decision required (`paid_t3_media`)** if course is paid. |
| `wasabi` (`video_type = url` + `lesson_type = wasabi`) | `external_url` (or `signed_storage` after S3 copy) | `video` | `T3` (public URL) / `T1` (re-hosted) | Emit `paid_t3_media` (`decision_required`) if paid and un-migrated. |
| `youtube` | `youtube` | `video` | `T3` | Import with `media_status = 'exception'` + `migration_exceptions` (`paid_t3_media`) if course is paid. |
| `vimeo` | `vimeo` | `video` | `T3` | Import with `media_status = 'exception'` + `paid_t3_media` if course is paid. |
| `google_drive` / `html5` / `url` / `amazon` | `external_url` | `video` | `T3` | Verify link access permissions; emit `paid_t3_media` if paid. |
| `iframe` | `iframe` | `embed` | `T3` | Host must pass `config('media.iframe_allowlist')`; emit `paid_t3_media` if paid. |

Every media item records a `media_status` in `migration_records.metadata`: `migrated` | `reused` | `replaced` | `exception`.

---

## 4. URL Redirect Map Plan (`seo_redirects`)

Target routing uses `App\Models\SeoRedirect` + `App\Domain\Seo\Queries\ResolveRedirect` + `App\Http\Controllers\LegacyRedirectController` (301 permanent, relative same-site paths only).

| Legacy Path Pattern (`ASSUMPTION` from CodeIgniter `routes.php` / `Home.php` / `Blog.php`) | Target Path (`PublicUrl`) | Status |
|---|---|---|
| `/home/courses` | `/{default_locale}/courses` | `301` |
| `/home/course/{legacy_slug}/{legacy_id}` | `/{default_locale}/courses/{course.slug}` | `301` |
| `/home/course/{legacy_id}` | `/{default_locale}/courses/{course.slug}` | `301` |
| `/category/{legacy_slug}` | `/{default_locale}/categories/{category.slug}` | `301` |
| `/home/instructor_page/{legacy_id}` | `/{default_locale}/instructors/{profile.slug}` | `301` |
| `/certificate/{shareable_url}` | `/verify/{certificate.token}` | `301` |
| `/blogs` | `/{default_locale}/blog` | `301` |
| `/home/about_us` | `/{default_locale}/pages/about` | `301` |
| `/home/contact_us` | `/contact` | `301` |
| `/home/privacy_policy` | `/{default_locale}/pages/privacy` | `301` |
| `/home/terms_and_condition` | `/{default_locale}/pages/terms` | `301` |

---

## 5. Open Questions Only the Real Legacy Source (`O-003`) Can Answer

1. **Authoritative Host & Revision (`O-001` / `O-003`):** What is the real live Young Go domain/URL (`youngschool.com` mismatch) and exact deployed codebase commit + DB snapshot timestamp?
2. **Installed Add-on Schemas:** Which add-ons exist in production, and what are the exact DDL definitions for `live_class`, `certificates`, `assignments` (if any), `offline_payment`, `course_bundles`, and `subscription` tables?
3. **Media & File Storage Manifest:** Which disk/CDN/S3 buckets hold thumbnails, lesson videos, audio files (`audio_url_for_mobile_application`?), captions (`.vtt`/`.srt`), and `resource_files`, and what is `academy_cloud` (`cloud_video_id`)?
4. **Paid T3 Media Policy:** For paid courses using YouTube/Vimeo/Google Drive/Wasabi URLs, will the owner re-host to private T1 storage, grant an explicit exception, or make the course free?
5. **Bilingual & Script Conventions:** Are Arabic and English titles/descriptions stored in separate columns/add-on tables or mixed inside single columns, and do `course.faqs`/`outcomes`/`requirements` match Academy LMS JSON schemas?
6. **Users, Passwords & Guardian Links:** What password hashing algorithm is used in `users.password`? Are birth dates or parent/child relationships stored in any table? Are phone numbers Egyptian or mixed?
7. **Enrollments, Payments & Manual Grants:** How are manual admin grants distinguished from paid orders when `enrol.gifted_by = 0`? What currency is `course.price` / `payment.amount` stored in? How are Instapay offline approvals recorded?
8. **Quizzes & Progress Reconciliation:** Are quiz `correct_answers` 1-based or 0-based indices? Which source is authoritative when `quiz_results` differs from `watch_histories.quiz_result`?
9. **Live URLs & Slugs:** Do live site URLs match CodeIgniter defaults (`/home/course/{slug}/{id}`, `/blogs`, `/page/{slug}`) or use custom `.htaccess`/router rewrites?
