# T-G7-FIXES-B tests prompt (checkout order, public catalog, course page)

Context: fixes for review defects D4 (public catalog, reference 05), D5 (course page, reference 06) and D6 (checkout order, reference 11) of `docs/reviews/G7_P8_REVIEW_2026-11-19.md`. Write Pest feature tests only (no production code). Put them in `tests/Feature/Pages/PublicCatalogReferenceTest.php`, `tests/Feature/Pages/CoursePageReferenceTest.php` and `tests/Feature/Commerce/CheckoutOrderReferenceTest.php`. Run per folder: `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Pages` (same for Commerce).

## Checkout order (`GET /checkout/orders/{order}`, route `checkout.order`)
1. Payer block: signed-in payer sees `data-payer-details` with their name and email, read-only (no input named name/email), plus the learner name when the line has a learner.
2. Order of cards in the pending state (assertSeeInOrder with raw HTML): `data-payer-details`, `name="method"`, `data-checkout-manual`, `data-checkout-coupon`, `name="terms"`, then the pay button. (Reference order: payer, method, coupon, terms, pay.)
3. The pay controls are tied to one form: `id="checkout-pay-form"` and `data-checkout-pay` exist once; radios, terms checkbox and submit button carry `form="checkout-pay-form"`; the coupon form (`checkout.coupon.apply`) is NOT nested inside the pay form. Posting `checkout.pay` with `method=manual, terms=1` still redirects to `manual-payments.create`.
4. Summary thumbnail: a course with `cover_image` set (Storage::fake('public')) renders an `<img>` with that URL inside `data-summary-cover`; a course without cover (and a bundle/plan order) renders the placeholder without an `<img>`.
5. Manual transfer card: contains the order reference, a link `data-transfer-upload` to `manual-payments.create`, and a status chip `data-transfer-status="none"`; after a pending proof the order page shows `data-status="proof-pending"` (existing) and after a rejected proof the chip is `rejected`.
6. Secure order badge text (`checkout.secure`) is shown on the order step; the paid / failed / cancelled states still render without the pay form.
7. AR and EN: assertSee the translated labels `checkout.payer_title`, `checkout.transfer_upload` in both locales; the order reference stays in a `<bdi dir="ltr">`.

## Public catalog (`GET /courses`, `/en/courses`)
1. Hero band `data-catalog-hero` with `catalog.hero_title` and `catalog.hero_subtitle`.
2. Category chips `data-category-chips`: an "All" chip plus one chip per active category (unpublished or inactive categories are absent); the chip of the selected `?category=` has `aria-current="true"`; chip URLs keep other filters (q, level, sort) but replace `category`.
3. Cards: a course with `cover_image` shows its cover `<img>`; one without any cover shows `data-card-cover-placeholder`; other card users (home, category page, wishlist) do NOT show the placeholder.
4. Filters collapsible on mobile: `data-filters-collapsible` contains a `<details>`; it is `open` only when a filter (other than q) is active; the count badge `data-filter-count` shows the number of active non-search filters; with no filters there is no badge.
5. Behaviour kept: filters, sort, availability, active chips (`data-active-filters`), clear-all link and pagination URLs unchanged (existing PublicCatalog* tests must still pass).
6. Content language `both` is shown as the translated label (`catalog.both`) in the language filter options.

## Course page (`/courses/{course}`)
1. Tabs use the shared component: `data-ui-tabs` with `role="tablist"`; tabs Overview / Course content (+ Instructor when instructors exist, + FAQ when FAQs exist); first tab `aria-selected="true"`, panels `role="tabpanel"` and the others `hidden`; the underline variant has the `border-b-2` / `aria-selected:border-primary` classes. No plain `<nav aria-label="course_page.sections">` link row remains.
2. All section content is still in the HTML (outcomes, curriculum rows `data-lesson-row`, requirements, custom fields, FAQ) so SEO and existing assertions keep working.
3. Hero: with a cover the hero shows the image; without a cover it shows `data-cover-placeholder`; the preview play link `data-preview-link` stays when a preview lesson exists.
4. Raw enum never shown: for a course with `content_language = both` the language chip reads the translated label; for `access_mode` free / paid / subscription_only / both the access card `data-access-mode` shows `courses.access_free`, `access_paid`, `access_subscription`, `access_both` and never the raw strings `both`, `paid`, `subscription_only`, `individual`, `subscription` as the whole chip text (assert in AR and EN).
5. Instructor chip: the name is wrapped in `<bdi>` (EN page with an Arabic instructor name).
6. CTA states unchanged: signed out (Sign in), learner without access (Buy now, `course-buy-cta`), enrolled (Continue learning); mobile sticky CTA `data-mobile-cta` present.

## Components
- `x-ui.tabs` default (pills) markup unchanged for the existing users (`design/components`, `learn/show`); `variant="underline"` adds the underline classes.
- `x-ui.icon name="filter"` renders an svg.
- Lang parity: `php artisan app:check-translations` stays green (keys added in `lang/{ar,en}/catalog.php`, `checkout.php`, `courses.php`).

Visual (manual or browser tests, 390 and 1440, AR and EN): no horizontal scroll, focus rings visible on chips, summary, tabs and details, sticky summary on lg only.
