# Legacy sidebar parity (PRD 19.2, T-P9-09)

Every legacy item and where it lives now. Sidebar groups and order come from `App\Filament\Shared\StaffNavigation`. No capability is dropped; intentional removals need an owner approval entry (none so far).

| Legacy item | New module | Panel | Sidebar group | Status |
|---|---|---|---|---|
| Dashboard | `WorkspaceDashboard` | admin, finance, instructor | (top, no group) | built |
| Young Go Home Page | `HomepageBlockResource` | admin | Website | built |
| Custom Pages | `CmsPageResource` | admin | Website | built |
| Subscription Plans | `PlanResource`, `SubscriptionResource` | admin, finance | Sales and enrolment | built |
| Manual Grants | `ManualGrantResource` | admin, finance | Sales and enrolment | built |
| Role Assignments | `RolePermissionResource` | admin | People | built |
| Instapay Payments | `ManualPaymentProofResource`, `PaymentReconciliationPage` | admin, finance | Sales and enrolment | built |
| Coupon Checkout Usage | `CouponResource` (usage report) | admin, finance | Sales and enrolment | built |
| Young Go Settings | `SettingsHub` + `GeneralSettingsPage`, `PaymentPayeeSettings`, `MarketingTags`, `RetentionSettings`, notification templates | admin | Settings | built |
| Course Management | `CourseResource` | admin, instructor | Courses | built |
| Add New Course | `CourseResource` create action (course wizard) | admin, instructor | Courses | built |
| Categories | `CategoryResource` | admin | Courses | built |
| Coupons | `CouponResource` | admin, finance | Sales and enrolment | built |
| Enrollments/Registrations | `ManualGrantResource`, `SubscriptionResource`, learner 360 | admin | Sales and enrolment, People | built |
| Reports | `ReportsPage` | admin, finance, instructor | Reports | built |
| Users | `StaffUserResource` | admin | People | built |
| Messages/announcements | `AnnouncementResource` | admin, instructor | Messages | built |
| Help-request inbox | `HelpRequestResource` | admin, instructor | Messages | built |
| Newsletter | `NewsletterSubscriberResource` | admin | Messages | built |
| Contact enquiries | `ContactEnquiryResource` | admin | Messages | built |
| Blog | `BlogPostResource`, `BlogCategoryResource`, `BlogTagResource` | admin | Website | built |
| Profile | Account menu (security/2FA) and `/profile` | all | user menu | built |

Added beyond legacy (kept from earlier tasks): lesson assistant pages (group Lesson assistant), instructor earnings/statements/payout profile and agreements (Finance and earnings), privacy requests, retention and audit log (Settings), submission/essay review and course reviews (Courses).

Visibility rule: an item shows only when the panel registers it and the user passes its `canAccess()`/`canViewAny()`; empty groups are hidden. A class registered in a panel but absent from the catalog appears under "More".
