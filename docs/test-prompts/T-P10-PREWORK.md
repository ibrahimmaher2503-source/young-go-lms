# Tests prompt: T-P10-PREWORK Legacy migration framework and synthetic rehearsal (P10)

You write the tests for a finished pre-work implementation. Do **not** change production code (`app/**`, `database/**`, `routes/**`, `config/**`, `resources/**`, `lang/**`). If a test exposes a bug, write it correctly, mark it `->skip('BUG: ...')` and list it in `docs/reviews/BUGS_FOUND.md`.

Stack: Laravel 13, Pest 5, SQLite in memory for the default target DB + a temporary file SQLite DB built via `LegacyFixtureBuilder` for the `legacy` connection. Follow `AGENTS.md`: header `// T-P10-PREWORK | PRD 25, D-004, O-018, R-010, I01, ED-002, ED-018 | A1 A2 A3`, groups `->group('T-P10-PREWORK', 'A1')`, unique helper names (prefix `mig`). On Windows run Pest with `php -d extension=sockets -d memory_limit=2G -d opcache.enable_cli=0 vendor/bin/pest`. Note: `T-P10-02` and `T-P10-03` remain `todo` awaiting O-003.

## What was built
- **Config & Connection:**
  - `config/database.php`: separate connection named `legacy` (`LEGACY_DB_DRIVER`, `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD`, `LEGACY_DB_PREFIX`). Defaults to `sqlite` pointing to `database/fixtures/legacy/synthetic_legacy.sqlite`; switching to O-003 MySQL takes only `.env`/config values.
  - `config/legacy_migration.php`: `connection`, `source_system`, `default_chunk_size`, `default_market`, `default_currency`, `default_locale`, `default_timezone`, `storage_root`, `fixture_path`, `sample_size`, `enforce_read_only`, `demo_mobile_video_url`.
- **Database Migration (`2026_11_30_100000_create_migration_framework_tables.php`):**
  - `migration_batches` (batch tracking, `mode`, `status`, `resumed_from_batch_id`, `chunk_size`, `last_source_id` checkpoint, count columns, `report` JSON, `error_message`).
  - `migration_records` (`unique(source_system, entity, source_id)` ID map + `target_type`/`target_id`, SHA-256 `checksum`, `status`, `rejection_reason`, `last_batch_id`, `metadata`).
  - `migration_exceptions` (`batch_id`, `entity`, `source_table`, `source_id`, `code`, `severity`, `message`, `context`, `resolved_at`, `resolution`).
- **Domain Hooks:**
  - `App\Domain\Entitlements\Actions\GrantEntitlement`: suppresses `AccessNotice` email/notification dispatch when `EntitlementSource::LegacyMigration` is used.
  - `App\Domain\Entitlements\Queries\LegacyMigrationEntitlementStats`: read-only verification query class inside `Entitlements` (`countBySourceRefPrefix`, `existingIds`, `findSummaryById`) preserving `I01`.
  - `App\Domain\Progress\Actions\ImportLegacyProgress`: `final` Action that records historical `LessonProgress` (`completion_source = 'legacy'`) and recalculates `CourseProgress` without weakening `RecordProgress::heartbeat()`.
- **Migration Domain (`App\Domain\Migration\*`):**
  - Contracts: `Contracts\LegacySourceReader`, `Contracts\EntityImporter`.
  - Support: `Support\ReadOnlyLegacyConnection` (guards SQL via `beforeExecuting()` whitelist `assertReadOnlySql()` + PDO `PRAGMA query_only = ON` / `SET SESSION TRANSACTION READ ONLY`), `Support\Checksum`, `Support\ScriptNameMapper`, `Support\LegacyDateParser`, `Support\MediaTierClassifier`, `Support\LegacyIdMap`, `Support\RedirectMapBuilder`, `Support\ImporterRegistry`, `Support\LegacyFixtureBuilder`.
  - Models: `Models\MigrationBatch`, `Models\MigrationRecord`, `Models\MigrationException`.
  - Enums: `Enums\LegacyEntity` (15 entities in dependency order), `Enums\MigrationBatchStatus`, `Enums\MigrationRecordStatus`, `Enums\MigrationExceptionSeverity`.
  - Exceptions: `Exceptions\LegacyWriteForbiddenException`, `Exceptions\MigrationConfigurationException`, `Exceptions\MigrationResumeException`.
  - Data DTOs (`final readonly`): `ImportOptions`, `ImportContext`, `ImportExceptionData`, `RowImportResult`, `EntityImportResult`, `SampleCheckResult`, `EntityVerificationResult`, `VerificationReport`.
  - 15 idempotent importers (`Importers\*`): `UserImporter`, `CategoryImporter`, `CourseImporter`, `SectionImporter`, `LessonImporter`, `ResourceImporter`, `QuizImporter`, `LiveSessionImporter`, `EnrollmentImporter` (uses `GrantEntitlement` + `EntitlementService` + `LegacyMigrationEntitlementStats` ONLY — I01), `ProgressImporter`, `QuizResultImporter`, `OrderImporter` (read-only history, never fires `PaymentVerified` or alters frozen paid totals), `ReviewImporter`, `CertificateImporter`, `RedirectImporter`.
  - Actions (`final`): `Actions\RunLegacyImport`, `Actions\VerifyLegacyImport`, `Actions\SyncLegacyRedirects`.
- **Fixture & Console Commands:**
  - `database/fixtures/legacy/schema.sql` & `database/fixtures/legacy/synthetic_legacy.sqlite`.
  - `app/Console/Commands/LegacyImportCommand.php` (`legacy:import {entity=all} --dry-run|--apply --chunk=100 --resume= --source= --json`).
  - `app/Console/Commands/LegacyVerifyCommand.php` (`legacy:verify {entity=all} --sample=5 --source= --json`).
  - `app/Console/Commands/LegacyBuildFixtureCommand.php` (`legacy:build-fixture {--path=} {--force}`).
  - Lang files: `lang/en/migration.php` and `lang/ar/migration.php`.

## Setup
- Seed `RolePermissionSeeder` in the test target DB (`:memory:`).
- Build an isolated temporary SQLite fixture file per test using `app(LegacyFixtureBuilder::class)->build($tempPath, true)` and point `config()->set('database.connections.legacy.database', $tempPath)` (clean up in `afterEach`). Never touch `database/database.sqlite` or modify the committed `synthetic_legacy.sqlite`.
- Purge connection `DB::purge('legacy')` after changing `database.connections.legacy.*` config so `ReadOnlyLegacyConnection` connects to the test fixture file.

## Acceptance & Edge Cases to Cover
- **Read-only legacy connection (`ReadOnlyLegacyConnection`):**
  - `ReadOnlyLegacyConnection` rejects configuration matching `database.default` (`MigrationConfigurationException`).
  - Attempting `INSERT`, `UPDATE`, `DELETE`, `DROP`, `CREATE`, `ALTER`, `TRUNCATE`, comment-prefixed `/* comment */ INSERT ...`, or multi-statement `SELECT 1; DELETE FROM users;` on `DB::connection('legacy')` throws `LegacyWriteForbiddenException`.
  - Raw PDO `exec("INSERT ...")` on SQLite legacy connection fails because `PRAGMA query_only = ON` is active.
  - Switching `database.connections.legacy` to another temp SQLite file swaps source cleanly via config alone.
- **T-P10-03 A2 — `--dry-run` default & zero writes:**
  - Running `artisan('legacy:import', ['entity' => 'all'])` (no `--apply`) and `artisan('legacy:import', ['entity' => 'all', '--dry-run' => true])` both default to dry-run, return exit 0, report source/imported/rejected/exception counts, and leave all target tables (`users`, `categories`, `courses`, `course_sections`, `lessons`, `curriculum_items`, `media_assets`, `orders`, `seo_redirects`) AND `migration_batches`, `migration_records`, `migration_exceptions` with zero new rows.
  - Combining `--dry-run` and `--apply` fails with exit code 1 and localized error `__('migration.errors.mutually_exclusive_mode')`.
- **T-P10-03 A1 — Idempotency & Checksum updates:**
  - Running `legacy:import all --apply --chunk=2` imports all valid fixture rows.
  - Rerunning `legacy:import all --apply --chunk=2` produces zero duplicates (`imported_count === 0`, `updated_count === 0`, `unchanged_count` equals mapped counts) and leaves identical row counts across every target table.
  - Updating a mutable legacy row (e.g. a course title in a test copy of the SQLite fixture) changes its checksum and updates the existing target course ID cleanly on rerun instead of creating a second row.
  - Changing a legacy `payment.amount` for an already-imported `Order` does NOT mutate frozen `orders.total_minor` (avoids `LogicException`), records `outcome = skipped`, and logs a `decision_required` exception `paid_order_changed_in_source`.
- **T-P10-03 A3 — Count & Rejection / Exception Report:**
  - Verify `EntityImportResult` reconciles `sourceCount === imported + updated + unchanged + skipped + rejected` for each entity.
  - Verify exact rejection codes from the synthetic fixture: `invalid_email` & `duplicate_email` (`users`), `category_depth_exceeded` & `missing_parent_category` (`categories`), `missing_category` (`courses`), `missing_course` (`sections`, `live_sessions`, `certificates`), `missing_section` (`lessons`), `missing_lesson` (`resources`), `expiry_before_start` & `missing_user` (`enrollments`), `invalid_amount` (`orders`), `invalid_rating` & `unsupported_ratable_type` (`reviews`), plus `quiz_attempt_not_submitted` skipped in `quiz_results`.
  - Verify Migration Plan §5A & `INVENTORY.md` exceptions: paid course YouTube/Wasabi lessons emit `paid_t3_media` (`severity = decision_required`, `MediaAsset::protection_tier === 'T3'`), mobile demo placeholder emits `demo_mobile_placeholder` warning, PDF resource emits `pdf_curriculum_candidate` warning without creating AI document rows, `watch_histories.quiz_result` emits `watch_history_quiz_mismatch` warning.
- **Resume after Failure:**
  - Run `RunLegacyImport` on `courses` with `chunkSize: 1` and `beforeRow` throwing a `RuntimeException` on the second source ID (`102`).
  - Assert `MigrationBatch` has `status === MigrationBatchStatus::Failed`, `last_source_id === '101'`, and `courses` contains only course `101`.
  - Resume via `ImportOptions(entity: 'courses', dryRun: false, chunkSize: 1, resumeBatchId: $failedBatch->id)` (and test `legacy:import courses --apply --resume=...`), asserting `status === completed`, `resumed_from_batch_id === $failedBatch->id`, course `102` imported without duplicating `101`, and course `103` rejected.
  - Assert invalid resume attempts (`--dry-run --resume`, `--resume` with `all`, completed batch ID, mismatched entity) throw `MigrationResumeException`.
- **I01 Entitlements & Progress & Curriculum Ordering:**
  - Migrated active enrollment (`701`) passes `EntitlementService::canAccessCourse($layla, $course101)->allowed === true`; expired enrollment (`703`) returns `allowed === false, reasonCode === 'entitlement_expired'`.
  - `GrantEntitlement` does NOT send `AccessNotice` notifications for `EntitlementSource::LegacyMigration`.
  - `tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php` and `tests/Arch/DomainTest.php` pass.
  - Lessons and quizzes on course `101` are ordered strictly by `curriculum_items.sort` (ED-002); `User` Arabic/English script detection sets `name_ar` or `name_en` exclusively (Migration Plan §5A).
- **URL Redirects & `legacy:verify`:**
  - After `legacy:import all --apply`, `GET /home/course/101`, `GET /category/acting-theater`, `GET /home/instructor_page/2`, `GET /certificate/cert-leg-5-102`, and `GET /home/courses` return HTTP `301` redirecting to the mapped target URLs (via `LegacyRedirectController`).
  - `artisan('legacy:verify', ['entity' => 'all', '--sample' => 5])` exits with `0` and all 15 entities pass.
  - Deleting one target row (e.g. a `CourseReview`) makes `legacy:verify reviews` return exit `1` with `countsReconcile === false`.
- **Gates:**
  - Run `php artisan app:check-translations`, `php artisan app:check-rtl`, `tests/Arch`, `tests/Feature/Invariants`.

## SQLite limits
- Use a real temporary SQLite file (not `:memory:`) for the `legacy` connection so `DB::connection('legacy')` and `DB::connection()` remain distinct connections with independent PDO handles.
- Never reference or modify `database/database.sqlite`.
