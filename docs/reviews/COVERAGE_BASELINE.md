# Coverage baseline

Measured: **unavailable**. This PHP build has neither `pcov` nor `xdebug`, so
`pest --coverage` cannot produce line or mutation numbers. This file records a
structural inventory instead: critical production classes and the test files that
exercise them. Percentages must not be inferred from it.

| Area | Production classes (representative) | Existing test files before this pass |
|---|---|---|
| Identity | `CreateChildAccount`, `LinkGuardian`, auth actions | `tests/Feature/Identity/*`, `Auth/*` |
| Catalog | course publishing, pricing | `tests/Feature/Catalog/*`, `Pages/PublicCatalogTest.php` |
| Curriculum | `CurriculumAccess`, `ManageCurriculum`, `ManageAssessmentItems` | `tests/Feature/Curriculum/{CurriculumManagerTest,AssessmentItemsTest}.php`, `Filament/CurriculumPageTest.php` |
| Media | `SourceUrl`, `VttValidator`, `Watermark`, `PlaybackDescriptor` | `tests/Feature/Media/{PaidT3PublishTest,PrivateFilesTest,SubtitleTracksTest,UploadLessonMediaTest}.php` |
| Library | `LibraryAccess`, library actions | `tests/Feature/Library/*` |
| AI | ingestion and restricted retrieval | `tests/Feature/AI/*` |
| Entitlements | `EntitlementService`, `CourseAccessSummary` | `tests/Feature/Entitlements/EntitlementServiceTest.php` |
| Progress | progress recorder and completion rules | `tests/Feature/Learning/*` |
| Cohorts | `ManageCohorts`, `PromoteFromWaitlist` | `tests/Feature/Cohorts/*` |
| Live | join window, attendance, replay | `tests/Feature/Live/*` |
| Notifications | router, dispatcher, guardian copy | `tests/Feature/Notifications/*` |
| Audit / Settings | audit logger, platform flags | `tests/Feature/{Audit,Settings,PlatformFlagsTest}.php` |

## How to produce real numbers

```powershell
php -d extension=pcov -d pcov.enabled=1 vendor\bin\pest --coverage --min=90
# mutation (requires infection/infection or pest mutation plugin)
vendor\bin\pest --mutate --min=80
```

Prerequisite: install `pcov` for PHP 8.4.21 ZTS, or use a CI image that has it.
