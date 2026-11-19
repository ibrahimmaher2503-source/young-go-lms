# Demo dataset (T-P9-11, ED-022)

Bilingual (AR/EN) demo data for local and staging demos. **Never production.** The credentials below are public, non-secret and only open demo accounts.

## Commands
```bash
php artisan migrate                  # adds the demo_anchors marker table
php artisan demo:seed                # create the dataset (refuses if demo data already exists)
php artisan demo:reset               # remove only the demo rows, then rebuild them
php artisan demo:reset --remove-only # remove only
```
- Allowed environments: `config/demo.php` -> `environments` (`local`, `staging`, `testing`). `APP_ENV=production` is refused in code (`DemoGuard`); there is no flag, option or env value to override it.
- Marker: every root record (users, courses, coupons, the bundle, the plan) is registered in `demo_anchors`. `demo:reset` follows the schema foreign keys from those roots and deletes only rows that cannot exist without them. It stops without deleting anything if a real record points at a demo one (for example a real learner enrolled in a demo course). Audit history is append-only and stays. Access rows are removed through the Entitlements module (`PurgeDemoEntitlements`).
- Everything is created through the domain Actions and `User::factory()`; access comes from enrollment and order Actions only. Orders are completed without any payment gateway (nothing leaves the process), mail uses the array mailer and every address is on the reserved `demo.younggo.test` domain (no real email or phone).
- Staging note: the fake payment gateway class only exists in `local`/`testing`, so the demo never calls a gateway at all; paid demo orders are marked paid and fulfilled directly through `TransitionOrder`, `GrantOrderEntitlements` and `RecordOrderEarnings`.

## Accounts
All demo accounts use the password from `config/demo.php` (`demo.password`): `DemoPass#2026`. Email = `<key>@demo.younggo.test`.

| Account | Login | Role |
|---|---|---|
| Admin | `admin@demo.younggo.test` | admin |
| Finance | `finance@demo.younggo.test` | finance |
| Academy instructor (Sara) | `instructor.academy@demo.younggo.test` | instructor_academy |
| Independent instructor (Karim) | `instructor.indie@demo.younggo.test` | instructor_independent |
| Learner Nour (AR) | `learner.nour@demo.younggo.test` | student |
| Learner Omar (EN) | `learner.omar@demo.younggo.test` | student |
| Learner Laila (AR) | `learner.laila@demo.younggo.test` | student |
| Parent Mona | `parent@demo.younggo.test` | parent |
| Child Adam (9) | username `demo.adam` | student, guardian-linked |
| Child Yara (12) | username `demo.yara` | student, guardian-linked |

## What it contains
- 8 courses (recorded, live, hybrid; free, paid, both, subscription-only; academy and independent owner), each with a published module of 3 lessons; `acting-101` also has a quiz (2 questions) and an assignment; the live and hybrid courses have an open cohort.
- Coupons `DEMO-WELCOME10` (10%) and `DEMO-FLAT50` (50 EGP), a bundle (acting + stage workshop), a monthly plan (2 subscription courses).
- Orders: five paid (course, course with coupon, course for a child, bundle, plan), one pending, one cancelled, one failed.
- Learning: free enrollments, Adam 1 of 3 lessons and Yara 3 of 3 (a certificate), Nour finished the acting course (quiz attempt, completion, certificate), a cohort seat, two approved reviews, one help request with an instructor reply (a recorded demo assistant exchange; no AI provider is called).
- The first Drawing Basics lesson has a private sample recording, an approved perspective-drawing PDF and approved key terms. Demo reset removes both generated private files. Automated checks use a scripted fake answer with a verified PDF citation for the adult Omar demo account.
- Refunded orders and settlements can be exercised from the seeded paid orders and ledger.
