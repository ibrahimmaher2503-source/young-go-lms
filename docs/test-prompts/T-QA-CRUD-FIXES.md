# Tests prompt — T-QA-CRUD-FIXES (admin CRUD QA, 2026-11-22)

Write Pest tests only. Do NOT change production code. Run with `php -d memory_limit=2G vendor/bin/pest`. SQLite; seed `RolePermissionSeeder`; `$this->withoutVite()`.

## Fix 1 — Course custom fields were unreachable (404)
Changed: `app/Providers/StaffPanelsServiceProvider.php` (admin `->resources()` now includes `CourseCustomFieldResource`), `app/Filament/Shared/StaffNavigation.php` (`courses` group includes it).
- A1 Admin with `course.publish` gets 200 on `/admin/course-custom-fields` and sees the "create" header action.
- A2 Livewire `ListCourseCustomFields`: mount `create` with valid data (field_key `qa_level`, type `text`, visibility `staff`) → row exists; empty `field_key`/`type` → validation errors; invalid key (uppercase/space) → error.
- A3 Livewire edit row action changes `label.en` → persisted.
- A4 Instructor and finance panels still return 404 for the slug (only registered in admin). A user without `course.publish` cannot view it (403/hidden).
- A5 The custom-fields item appears in the admin sidebar "courses" group (see tests/Feature/Settings/TSidebarV2NavigationTest.php for how groups are asserted).

## Fix 2 — Deleting a referenced course returned HTTP 500
Changed: new `app/Domain/Catalog/CourseReferenceHistory.php` (tagged `course.history` in `AppServiceProvider`), reason keys in `lang/{ar,en}/courses.php` → `blocked.{bundles,plans,commission_rules,ledger,live_sessions,help_requests,ai_conversations}`.
- B1 For each referencing table (`bundle_items`, `subscription_plan_courses`, `course_commission_rules`, `instructor_ledger_entries`, `live_sessions`, `help_requests`, `ai_conversations`): a draft course with one such row → `DeleteCourse::blockers()` contains the matching reason; invoking `DeleteCourse` throws `ValidationException` with `courses.delete_blocked`; the course row still exists.
- B2 A draft course with no references is still deleted (regression).
- B3 Filament `CourseResource` row action `delete` on a course in a bundle → danger notification with the blocked message, no exception/500, course remains.
- B4 Translation parity: every new `courses.blocked.*` key exists in ar and en.
