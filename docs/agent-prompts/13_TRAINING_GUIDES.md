# 13: Training guides (T-P11-05)

> Read `docs/agent-prompts/00_COMMON.md` first. Agents: `chore` writes the guides and `ui-builder` takes the screenshots. Do not change production code.

## Guides (Arabic first, then English)
1. **Platform admin:** settings, users and roles, approving courses and revisions, orders and refunds, manual payment proofs, coupons, bundles and plans, settlements, reports, Learner 360 and view-as, and privacy requests.
2. **Instructor:** the application, the course wizard, the curriculum, media and its protection, quizzes and assignments, live sessions, revisions after publishing, and earnings and statements.
3. **Parent:** linking a child, the dashboard, buying for a child, and notifications.
4. **Learner (child or teen):** signing in, the player, progress and certificates. Write in simple language that suits their age.
5. **Support:** common problems and how to solve them, such as a failed payment, access that did not arrive, or a device limit.

## Method
Run the demo dataset on a scratch database. Take screenshots from the real app: Arabic at 1440px, plus the key screens on mobile. Name each step exactly as it appears in the UI.

## Output
- `docs/guides/<role>.ar.md` and `docs/guides/<role>.en.md`, with images in `docs/guides/img/`.
- A summary in Arabic listing the guides, plus any screens that look confusing to users (UX feedback).
