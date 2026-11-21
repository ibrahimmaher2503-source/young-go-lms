# Administrator guide

Panel: `/admin` (title **Administration**). Demo account: `admin@demo.younggo.test`. See [README.md](README.md) for sign-in and two-factor.

## Who can open what

The demo `admin` account has the `admin` role. Some pages need other permissions. If a menu item is missing, the role lacks the permission.

| Page | Needs | Default holders |
|---|---|---|
| Users, Learner 360, Audit log, Reports | `user.view`, `learner.record.view`, `audit.view`, `report.view` | admin |
| Settings (hub), Notification templates, Safeguarding recipients | `system.settings.manage` | admin |
| Privacy requests, Data retention | `privacy.manage` | admin |
| **Edit roles** (on Users), **Roles** page | the `super_admin` role | super_admin only |
| Assistant controls (AI on/off, provider, budgets) | `ai.configure` | super_admin only |
| General settings, Manual payment details | `system.site_settings.manage` | super_admin only |
| Marketing tags | `content.seo.manage` | content manager, marketing, super_admin |
| Custom pages, Homepage blocks, Blog | `content.page.manage`, `content.blog.manage` | content manager, marketing, super_admin |

Note: `docs/DEMO_SCRIPT.md` steps 15 and 19 show the admin opening Marketing tags, General settings and Manual payment details. The code does not give the `admin` role these permissions. Sign in as a `super_admin` for those pages.

Sidebar groups, in order for admin: **Review and grading**, **Support and messages**, **Courses**, **Sales and enrolment**, **People**, **Reports**, **Finance and earnings**, **Website**, **Lesson assistant**, **Settings**. **Dashboard** is always first.

## 1. Read the dashboard

1. Open **Dashboard**.
2. **Key figures** shows the last 30 days (7, 30 and 90 are offered). Tiles include Net revenue, Paid orders, Active learners, Course completions.
3. **Needs your attention** lists live queues: Manual payments to review, Refund requests to approve, Instructor applications, Reviews to moderate, Help requests awaiting a reply.
4. Click a queue to open it.

Expect: figures can lag up to 60 seconds. Same figures as **Reports** for the same period.

## 2. Users and roles

1. Open **People > Users**. Tabs: **All users**, **Students**, **Instructors**. Search by name or email.
2. Filter by role or status (**Active**, **Suspended**, **Deactivated**).
3. Press **Export CSV** to download the list (needs `user.export`).
4. To change roles (super_admin only): open the row **Actions** menu, press **Edit roles**, choose roles, press **Save changes**. You see "This change will be recorded in the audit log."
5. To change what a role may do (super_admin only): open **People > Roles**, press **Permissions** on a role, tick or untick, save. Every change is recorded.

Common errors
- No **Edit roles** button: you are not `super_admin`, or the target is a `super_admin`.
- A user keeps an old menu: ask them to reload. Permissions are cached for a short time.

## 3. Two-factor authentication

1. Staff must set it up on first sign-in (steps in [README.md](README.md)).
2. To change it later, open the account menu and choose **Two-factor authentication**. You can **Create new recovery codes** or **Turn off two-factor authentication**.
3. If a staff member loses their device and recovery codes, no screen resets it. Escalate to a developer. Do not share codes.

## 4. Settings hub

1. Open **Settings**. The page **Young Go settings** shows only entries you may open, in three groups: Platform, payments and notifications; Privacy, safeguarding and marketing; Assistant, roles and audit.
2. **General settings** (super_admin): site name, tagline, contact, social links, default language and market, maintenance banner, mail sender. Press **Edit**, change, **Save**. Expect "Settings saved."
   - Pending owner input (T-P9-09): default language and market are stored but not read at run time yet.
   - Pending owner input: the mail sender has no real transactional provider yet.
3. **Manual payment details** (super_admin): the Instapay or bank details shown at checkout for transfers. Enter demo values only.
4. **Notification templates**: open a template, press **Edit wording**, save. Placeholders such as `:order` stay as they are. Do not change wording without the owner. The full list is in `docs/NOTIFICATION_MATRIX.md`.
5. **Safeguarding recipients**: add extra email addresses (each must belong to an active user). Admin and support are always told. Pending owner input (O-028): wording is a draft and recipients are not final.
6. **Data retention**: see section 8.
7. **Audit log**: see section 9.

## 5. AI controls

The lesson assistant exists only in recorded lessons. Staff see status, never learner conversations.

1. Open **Lesson assistant > Assistant controls** (super_admin).
2. **Assistant for all learners** is the master switch. **Turn on** needs an approval reference (owner approval of provider and privacy terms). **Turn off** stops it for everyone at once. Lessons and progress are not affected.
3. **Provider, model and prompt**: press **Edit**. Only providers the platform knows can be chosen. Never type credentials here.
4. **Quotas and budgets**: questions per learner per day, monthly budget. Amounts are in minor units (10000 means 100.00). Leave a budget empty for no ceiling.
5. **Retention**: how long messages and usage metrics are kept.
6. **Close the circuit**: use only when the provider is known to work again.
7. **Go-live checklist** shows what is still open (global switch, provider, prompt, evaluation, budget, age tiers, safeguarding, retention).
8. **Usage and cost** (admin can open): counts, cost and errors per day, no messages.
9. **Assistant by course**: switch the assistant per course or lesson. A lesson also needs an approved PDF.

Pending owner input (O-014): no real AI provider is approved. Production cannot answer until the owner approves one. On demo, the test console uses a scripted answer.
Pending owner input (O-015): the adult age setting.

## 6. Consent and marketing tags

1. Check the public banner: open `/courses` in a private window. You see **Cookies** with **Accept** and **Decline**. Decline is the privacy default. A **Cookie settings** link remains.
2. Open **Marketing tags** (needs `content.seo.manage`). Fields: **Google Analytics 4 measurement ID** (looks like G-XXXXXXXXXX) and **Meta Pixel ID** (digits). Both are empty by default. Press **Edit**, enter, save. Expect "Marketing tags saved."
3. Tags load on public pages only after a visitor accepts. They never load in lessons, learner or parent areas, or the assistant.
4. Do not enter real IDs on demo.

Pending owner input (O-027): the consent wording is a draft ("Draft wording, pending owner and counsel approval").

## 7. Reports

1. Open **Reports**. Choose **Report**, then **From** and **To**. Optional filters: Course, Instructor, Market.
2. Reports: Platform overview, Student progress, Course content, Sales and revenue, Course and bundle sales by course, Refunds, Course performance, Live session attendance, Assessment results, Certificates issued, Instructor earnings and settlements, Instructor operations, AI usage and cost, Help requests and response time, Payment reconciliation.
3. Totals are per currency and never mixed.
4. Press **Export CSV**. The file has translated headings and money as numbers.
5. Expect "Showing the first N of M rows. Export the CSV for all rows." for big reports.

Common errors: "This report has N rows, more than the export limit" means narrow the dates or filters.

## 8. Privacy requests and retention

1. Open **Settings** then **Privacy requests** (or the Settings hub entry). Summary: Waiting for review, Approved not completed, Open deletions.
2. Open a request. Types: Download my data, Correct my data, Delete my account.
3. Press **Approve** (or **Reject** with a reason).
4. Press **Complete**. Export builds a private archive the requester can download. Correction: make the change in the user record first, then write a **Note**. Deletion anonymizes the account permanently. Orders, payments, refunds, receipts and consent records are kept.
5. Expect "Request completed."
6. **Data retention** shows how long each data class is kept. Press **Change periods** to edit. Orders, payments, certificates, consent records and the audit log are never deleted by retention.

Pending owner input (O-025): final retention periods. Current values are conservative defaults. The anonymizer refuses to run on production.

## 9. Audit log

1. Open **Audit log**. Filter by event, actor, subject, **From date**, **Through date**.
2. Use it to check role changes, report exports, view-as sessions (`support.view_as_started`, `support.view_as_stopped`).

## 10. Review queues

- **Courses**: rows in **Pending review** have **Approve** and **Reject**. A reject needs a reason, shown to the instructor.
- **Course revisions**: approve or reject changes to published courses. You cannot review your own revision.
- **Course reviews**: **Approve** a pending learner review or **Hide** a published one.
- **Instructor applications**: **View ID document** (private link), then Approve or Reject. Approve gives the instructor role and a draft profile.
- **Instructor agreements**: create a **New version**, then **Publish**. A published version cannot be edited. Pending owner input (D-026): the seeded text is a placeholder, not the legal agreement.
- **Lesson PDF text**: admin holds `lesson_document.approve`. Use the steps in the instructor guide, section 5.

## 11. Demo reset

Not a screen. A developer runs `php artisan demo:reset` on local or staging. Production refuses it. After the privacy anonymization demo, always reset.
