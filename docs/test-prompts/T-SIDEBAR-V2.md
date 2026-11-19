# T-SIDEBAR-V2: tests prompt (for another model)

Write Pest tests only; do not change application code. Repo rules: `AGENTS.md`. Group `T-SIDEBAR-V2`.

## Under test
- `App\Filament\Shared\StaffNavigation`: `GROUPS` (item order inside groups), `PANEL_ORDER`, `QUEUE_GROUP`, `order(string $panel)`.
- `App\Filament\Shared\HasQueueBadge` (trait, used by 9 resources) and each resource's `queueCount()`.
- `App\Filament\Resources\Orders\OrderResource` (+ `ListOrders`) and `App\Domain\Commerce\Queries\OrderDirectory`.
- CSS: `resources/css/tokens.css` (`--yg-font-sidebar: 0.875rem`, `--yg-font-sidebar-group: 0.8125rem`) and the sidebar rules in `resources/css/filament-theme.css`.

## Cases
1. Group order per panel: grouped labels from `Filament::getNavigation()` follow `StaffNavigation::order($panel)` filtered to non-empty groups. Dashboard is first; the second ungrouped item opens the course wizard if the current panel registers it and the user can create. Admin starts Review and grading, Support and messages, Courses, Sales. Finance starts Sales, Finance. Instructor starts Courses, Review and grading, Support and messages.
2. Item order: Finance shows Transfer Proofs, Refunds and Orders first within Sales; Admin starts with Orders. Courses precedes Categories; Help Requests is first in Support and messages. Review queues have their own group.
3. Every registered class of each panel is in exactly one catalog group (existing parity test must stay green); `OrderResource` is registered in admin and finance, never in instructor.
4. Collapse: open the active group and the panel queue group (`reviews` for admin and instructor, `sales` for finance) by default. When the role has no visible review queue, open Support and messages if available.
5. Badges, one dataset per resource (transfer proofs `pending`; help requests `new`/`in_progress`; course reviews `pending`; submissions `submitted`/`resubmitted`; essay attempts awaiting review; instructor applications `pending`; refunds `requested`/`approved`; data requests `requested`; settlements `draft`):
   - Count equals the seeded waiting rows; resolved/other rows are not counted.
   - Zero waiting rows returns `null` (no badge), color `warning`.
   - A user without access to the resource gets `null` even with waiting rows (for example marketing role for refunds, support for settlements).
   - Instructor scope: instructor A with help requests/submissions/essays on own courses sees only own count; instructor B on other courses sees none. Cache key is per user (`nav-badge:{class}:{userId}`), so A's cached count is never returned to B (assert with the same request cycle, two users).
   - Cached for 60 seconds: change a row after the first read and the badge stays until `Cache::flush()` or time travel past 60s.
6. Orders resource: `payment.view` holders (admin, finance) can open `/admin/orders` and `/finance/orders`; roles without it get 403 or no nav item; no create/edit/delete (`canCreate`, `canEdit`, `canDelete` false). The table lists reference, learner, amount, status and gateway (latest attempt by `sequence`; "Bank transfer" label when only a manual proof exists; "—" otherwise), filters by status, and the view dialog lists the order lines. Assert no write to `orders`, `entitlements` or any other table when listing and viewing.
7. Translations: `php artisan app:check-translations` passes; `orders.*` and `navigation.badge_waiting` exist in AR and EN; AR render of the Orders list and sidebar contains no English-only string.
8. CSS guard (file read, no browser): `tokens.css` item size is 0.875rem and group-label size is 0.8125rem and smaller than or equal to the item size is NOT required, but the item rule must not use `overflow-wrap: break-word` and the group-label rule must set `text-transform: none`. `app:check-brand-tokens` and `app:check-rtl` pass.
9. Browser (group `browser`, optional): at 1440 and 390 in AR and EN, the sidebar item label font-size is 14px, group label 13px, no label has `scrollWidth > clientWidth`, and only the queue group is open on the dashboard.

## Notes
- Use `RolePermissionSeeder`; instructor role is `instructor_academy`.
- Badge cache: use `Cache::flush()` in `beforeEach`.
- Screenshots from the implementation run are in `tests/Browser/screenshots/sidebar-v2/` (scratch SQLite, badge counts there were pre-filled in the cache for illustration only).
