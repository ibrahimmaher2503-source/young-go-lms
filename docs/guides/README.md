# Young Go LMS: training guides (T-P11-05)

One guide per staff role, in English and Arabic. Each guide lists the main workflows as numbered steps. Menu items, page titles and button names are copied from `lang/en/*.php` and `lang/ar/*.php`.

دليل لكل دور من أدوار الفريق، بالإنجليزية والعربية. يسرد كل دليل سير العمل الرئيسي في خطوات مرقمة. أسماء القوائم والصفحات والأزرار منقولة من ملفات `lang/en` و`lang/ar`.

## Who each guide is for

| Role | EN | AR | Panel | What it covers |
|---|---|---|---|---|
| Administrator (`admin`, `super_admin`) | [admin.en.md](admin.en.md) | [admin.ar.md](admin.ar.md) | `/admin` | Users and roles, two-factor, settings hub, AI controls, consent and marketing tags, reports, review queues, privacy requests, demo reset |
| Content manager (`content_manager`) | [content-manager.en.md](content-manager.en.md) | [content-manager.ar.md](content-manager.ar.md) | `/admin` | Custom pages, homepage blocks, blog, categories, catalog SEO, content library |
| Instructor (`instructor_academy`, `instructor_independent`) | [instructor.en.md](instructor.en.md) | [instructor.ar.md](instructor.ar.md) | `/instructor` | Course wizard, curriculum, PDF upload and AI readiness approval, quizzes and assignments, live sessions, help requests, revisions |
| Finance (`finance`) | [finance.en.md](finance.en.md) | [finance.ar.md](finance.ar.md) | `/finance` | Orders, Paymob, manual transfer proofs, coupons, refunds and receipts, ledger, statements, settlements, reconciliation, reports |
| Support (`support`) | [support.en.md](support.en.md) | [support.ar.md](support.ar.md) | `/admin` | Learner 360, view as learner, help requests, privacy requests |

Learners and parents are not covered here. They use the public site and `/login`.

## Before you start

- Work on a **local** or **staging** build with the demo data. Never run the demo on production.
- Menu items depend on role permissions (`config/permissions.php`). If a menu item in a guide is missing for you, your role does not have that permission. Each guide says which role is needed.
- The Arabic interface is right-to-left. Switch the language with the language switcher in the top bar of the panel.
- Some features wait for owner decisions. Each guide marks them with **Pending owner input**. The main ones are:
  - **B-07**: real Paymob credentials and endpoint checks. Until then online card and wallet payment is not live.
  - **O-014**: the real AI provider. Until then the lesson assistant cannot answer in production.
  - Transactional email provider: not supplied. Emails use the array mailer on demo, so nothing is delivered to a real inbox.
  - **O-012**: settlement rules and minimum payouts. **O-025**: data retention periods. **O-027**: cookie wording. **O-028**: safeguarding wording and recipients. **D-026**: agreement wording.
  - **T-P9-09**: the default language and market settings are stored but not read at run time yet.

## Sign in to the demo

1. Load the demo data (a developer runs this once):
   ```bash
   php artisan migrate
   php artisan demo:seed
   ```
2. Open the panel for the role (see the table above): `http://localhost:8000/admin`, `/finance` or `/instructor`. Replace the host with your staging host. Filament sends you to the sign-in page, titled **Welcome back** (أهلاً بعودتك).
3. Sign in with the email and the password below. The password for every demo account is `DemoPass#2026`. It is public and opens demo accounts only.

| Account | Email | Role | Panel |
|---|---|---|---|
| Admin | `admin@demo.younggo.test` | `admin` | `/admin` |
| Finance | `finance@demo.younggo.test` | `finance` | `/finance` |
| Sara, academy instructor | `instructor.academy@demo.younggo.test` | `instructor_academy` | `/instructor` |
| Karim, independent instructor | `instructor.indie@demo.younggo.test` | `instructor_independent` | `/instructor` |
| Nour, learner (AR) | `learner.nour@demo.younggo.test` | `student` | `/login` |
| Omar, learner (EN) | `learner.omar@demo.younggo.test` | `student` | `/login` |
| Laila, learner (AR) | `learner.laila@demo.younggo.test` | `student` | `/login` |
| Mona, parent | `parent@demo.younggo.test` | `parent` | `/login` |
| Adam (child, 9) and Yara (child, 12) | usernames `demo.adam`, `demo.yara` | `student` | `/login` |

### Gaps in the demo accounts

- **There is no demo account for the content manager, support or `super_admin` roles.** `docs/DEMO.md` lists none. The demo `admin` account has the `admin` role only.
- Only a `super_admin` can give a role to a user. The **Edit roles** action on **Users** and the **Roles** page are visible to `super_admin` only. A developer must create the first `super_admin` account. This guide set does not document how.
- To try the content manager or support guides, a `super_admin` opens **Users**, picks a test account and uses **Edit roles** to add the role. Use a test account, never a real person.
- Some admin pages need permissions the `admin` role does not hold by default (AI controls, General settings, Manual payment details, Marketing tags, custom pages and blog). The admin guide lists them.
- Demo reset is a command (`php artisan demo:reset`), not a screen. It is refused on production.

### Two-factor sign-in for staff

`TWO_FACTOR_ENFORCED` is on by default. The first time a staff user signs in, the app sends them to the two-factor setup. Use your own authenticator app. Do not bypass this step.

1. After sign-in you see **Two-factor authentication** (المصادقة الثنائية) with the message "Your role requires two-factor authentication. Set it up to continue."
2. Under **Set up your authenticator app**, install an authenticator app such as Google Authenticator or Microsoft Authenticator.
3. Scan the QR code, or type the key shown under "Or enter this key manually".
4. Type the 6-digit code in **Authentication code**. Press **Turn on two-factor authentication**.
5. Save the **Your recovery codes** shown on the next screen. They appear only once. Press **Continue**.
6. On later sign-ins you see **Two-step verification**. Enter the code (or a recovery code) and press **Verify**.

You can return to this page later from the account menu item **Two-factor authentication**.

## Reset the demo

A developer runs `php artisan demo:reset` (rebuilds the demo rows) or `php artisan demo:reset --remove-only`. It only works in `local`, `staging` and `testing`. It stops if a real record points at a demo record.

## Related documents

- `docs/DEMO.md`: demo data and accounts.
- `docs/DEMO_SCRIPT.md`: the product owner walk-through with Pass/Fail checklist.
- `docs/NOTIFICATION_MATRIX.md`: every notice, who gets it, and which are mandatory.
- `docs/15_UX_FLOWS_AND_PATTERNS.md`: the journeys these guides follow (J03, J09, J10, J11, J12).

## Screenshots

The task plan asks for screenshots from staging. These guides contain none yet: capture them on staging and add them next to each numbered step.
