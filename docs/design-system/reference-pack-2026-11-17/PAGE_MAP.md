# Page-to-PRD and shared-component map

| ID | Screen | Family | PRD / UX | Shared IDs | Image |
|---|---|---|---|---|---|
| 00-owner-approved-users | الريفيرنس الأصلي — إدارة المستخدمين | owner | D-026, PRD §19.1 | C01, C02, C04, C05, C06, C07, C08, C09, C10 | [00-owner-approved-users.png](00-owner-approved-users.png) |
| 01-admin-overview | نظرة عامة للإدارة | staff | PRD §19.1, PRD §21 | C01, C02, C04, C05, C06, C10 | [01-admin-overview-v2.png](01-admin-overview-v2.png) |
| 02-course-directory | إدارة الدورات | staff | PRD §9.1, PRD §9.3, PRD §19.1 | C01, C02, C04, C05, C06, C07, C08, C09, C10 | [02-course-directory.png](02-course-directory.png) |
| 03-curriculum-builder | بناء المنهج وإعداد الدرس | staff | PRD §9.5, PRD §9.6, PRD §9.7, AI spec §6 | C01, C02, C04, C06, C11, C14, C15 | [03-curriculum-builder.png](03-curriculum-builder.png) |
| 04-learner-dashboard | لوحة الطالب | learner | PRD §10.1, PRD §11–14, UX J04/J06 | C01, C03, C04, C06, C12, C13 | [04-learner-dashboard.png](04-learner-dashboard.png) |
| 05-public-course-catalog | كتالوج الدورات | public | PRD §8 YG-WEB-003, PRD §4.2 | C01, C03, C04, C06, C08, C12 | [05-public-course-catalog-v2.png](05-public-course-catalog-v2.png) |
| 06-public-course-detail | تفاصيل الدورة | public | PRD §8 YG-WEB-004, PRD §17.1–17.3 | C01, C03, C04, C06, C12, C14, C19 | [06-public-course-detail-v2.png](06-public-course-detail-v2.png) |
| 07-recorded-player-ai | مشغل الدرس ومساعد الدرس | learner | PRD §10.2, PRD §15, AI spec §2–4/15 | C01, C03, C06, C13, C14, C16, C17 | [07-recorded-player-ai-v2.png](07-recorded-player-ai-v2.png) |
| 08-parent-dashboard | لوحة ولي الأمر | learner | PRD §5.2, PRD §29.1, PRD §16, Decision Register O-016 | C01, C03, C04, C06, C10, C13, C18, C22 | [08-parent-dashboard-v2.png](08-parent-dashboard-v2.png) |
| 09-instructor-help-workspace | طلبات المساعدة للمدرب | staff | PRD §16, AI spec §12, UX J11 | C01, C02, C04, C05, C06, C07, C08, C10, C18 | [09-instructor-help-workspace-v2.png](09-instructor-help-workspace-v2.png) |
| 10-finance-ledger | دفتر المدربين والتسويات | staff | PRD §17.4, PRD §21, UX J10 | C01, C02, C04, C05, C06, C07, C08, C09, C19 | [10-finance-ledger-v2.png](10-finance-ledger-v2.png) |
| 11-egypt-checkout | إتمام الطلب والدفع | public | PRD §17.3, PRD §29.6, UX J09 | C01, C03, C04, C06, C11, C19 | [11-egypt-checkout.png](11-egypt-checkout.png) |
| 12-quiz-attempt | محاولة اختبار | learner | PRD §12, AI spec §8.3, UX J07 | C01, C03, C04, C06, C20 | [12-quiz-attempt.png](12-quiz-attempt.png) |
| 13-course-wizard | معالج إنشاء الدورة | staff | PRD §9.2, PRD §9.4 | C01, C02, C04, C06, C11, C13, C21 | [13-course-wizard-v2.png](13-course-wizard-v2.png) |
| 14-manual-payment-review | مراجعة إثبات دفع يدوي | staff | PRD §17.3, PRD §29.6, PRD §22 | C01, C02, C04, C05, C06, C07, C08, C09, C11, C19 | [14-manual-payment-review.png](14-manual-payment-review.png) |
| 15-public-home | الصفحة الرئيسية العامة | public | PRD §8 YG-WEB-002, PRD §4.1 | C01, C03, C06, C12 | [15-public-home.png](15-public-home.png) |
| 16-mobile-learner-dashboard | لوحة الطالب على الموبايل | mobile | PRD §10.1, PRD §24, UX J04 | C01, C03, C06, C12, C13, C24 | [16-mobile-learner-dashboard.png](16-mobile-learner-dashboard.png) |
| 17-mobile-lesson-assistant | مساعد الدرس على الموبايل | mobile | PRD §10.2, PRD §15, AI spec §15 | C01, C03, C06, C14, C16, C17, C24 | [17-mobile-lesson-assistant.png](17-mobile-lesson-assistant.png) |
| 18-shared-component-library | مكتبة العناصر المشتركة | components | PRD §29.13, Design system §2/4/6, UX §3/5 | C01, C02, C03, C04, C05, C06, C07, C08, C09, C10, C11, C12, C13, C14, C15, C16, C17, C18, C19, C20, C21, C22, C23, C24 | [18-shared-component-library-v2.png](18-shared-component-library-v2.png) |
| 19-course-directory-en | إدارة الدورات — English LTR | staff | PRD §4.3, PRD §19.1, Design system §4 | C01, C02, C04, C05, C06, C07, C08, C09, C10 | [19-course-directory-en-v2.png](19-course-directory-en-v2.png) |

These are visual master references, not proof of implementation or exhaustive acceptance coverage. See COMMON_COMPONENTS.md for actual/planned code boundaries.

Related screens reuse these masters: categories/coupons/enrollments/contact/newsletter/audit/report lists use00/02/10/19 with their own PRD fields and permissions; generic staff forms/settings use13 and shared native fields; live pages reuse the learning shell/player layout with AI absent; assignments/certificates/help-thread screens reuse learner cards, scoped detail panels and the corresponding PRD flow. Create a specific reference before claiming those new journeys visually accepted.
