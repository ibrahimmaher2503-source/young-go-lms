# Subscriptions, Bundles & Admin Access Editing — Short Design Spec

**Date:** 2026-11-21  
**Status:** Approved by owner

## 1. Intent & Why
Make subscription plans and course bundles clearly discoverable and actionable for visitors and learners on the public storefront (catalog, course detail page, and plans/offers page), and give administrators/finance staff direct modal actions in the Filament panels to edit existing course access grants (`Entitlement`) and learner subscription periods (`Subscription` / `SubscriptionPeriod`).

## 2. Goals, Non-Goals & Anti-Goals
- **Goals:**
  1. Surface active sellable subscription plans and course bundles covering a course directly on the public course detail page (`catalog/show.blade.php` & `catalog/_cta.blade.php`), replacing the disabled "Enrollment opens soon" button on subscription-only courses when a sellable plan covers the course.
  2. Display active sellable bundles on the public `/plans` page (`catalog/plans.blade.php`) and add a visible offer banner/link on `/courses` (`catalog/index.blade.php`) when sellable plans or bundles exist.
  3. Allow staff with `manual_entitlement.grant` to edit an existing active/scheduled `Entitlement` (`starts_at`, `ends_at` / `lifetime`, `reason`) via a new `UpdateEntitlement` domain action and `ManualGrantResource` table action.
  4. Allow staff with `payment.approve_manual` and `manual_entitlement.grant` to edit a learner's subscription period end date (`ends_at`, recomputing `grace_ends_at`, reactivating cancelled/expired status when extended into the future, and syncing period-linked course entitlements via `UpdateSourceEntitlementDates` in the `Entitlements` module) from `SubscriptionResource`.
- **Non-Goals:**
  - Changing the top public header navigation bar (locked by D-046).
  - Automatic recurring card charges (manual renewal remains per O-024 / R-017).
- **Anti-Goals:**
  - Writing to the `entitlements` table from outside `App\Domain\Entitlements` (violates Invariant `I01`).
  - Leaving any user-facing string untranslated in `lang/ar` or `lang/en`, or using non-logical directional CSS utilities.

## 3. Approaches Considered
1. **Chosen Approach — Domain Actions in `Entitlements` & `Subscriptions` + Storefront Offer Hooks:**
   - Keep all `entitlements` mutations inside `App\Domain\Entitlements\Actions` (`UpdateEntitlement` and `UpdateSourceEntitlementDates`), called by `App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod`, and expose modal edit actions on `ManualGrantResource` and `SubscriptionResource`.
   - Extend `CheckoutOffers` with `plansForCourse(Course $course, Market $market)` and `bundlesForCourse(Course $course, Market $market)` so `PublicCourseController`, `PublicPlansController`, and `PublicCatalogController` use the single commerce offer authority.
2. **Rejected Approach — Direct Eloquent edits in Filament resources or cross-module table updates:**
   - Violates architecture rules (business logic only in `app/Domain/*/Actions`) and Invariant `I01` (only `App\Domain\Entitlements` may write to `entitlements`).
