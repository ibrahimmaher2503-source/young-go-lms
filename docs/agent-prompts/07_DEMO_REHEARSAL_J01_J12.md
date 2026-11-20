# 07: Full independent demo rehearsal, J01–J12

> Read `docs/agent-prompts/00_COMMON.md` first. Agent: `qa-verifier`. This task is **read-only** for the code: run the app and report what you find.

## Setup
- A scratch SQLite database, then reset the demo data (see `docs/DEMO.md` and T-P9-11).
- Run `npm run build`, start the server, and drive it with Playwright.

## What to run
Run every journey in `docs/DEMO_SCRIPT.md` (J01–J12), in Arabic and in English, at 390px and at 1440px:
- Public visitor: catalog → course detail → checkout with a fake card (a failure, a retry, then a success).
- Learner: player → progress → quiz or assignment → certificate.
- Parent: the parent dashboard.
- Instructor: the wizard, the curriculum and the approval flow.
- Admin: orders, a refund, settlements, reports, Learner 360 and settings.

At each step, record: pass or fail, a screenshot, console errors, any 4xx/5xx responses in the network log, and the axe result.

Also try these edge cases at each step where they apply:
- the session expires in the middle of checkout
- a double click on the pay button
- the back button after payment
- a child tries to open a page for adults
- a refunded course
- a full cohort

## Output
- `docs/reviews/DEMO_REHEARSAL_2026-11-xx.md`: a table for each journey, locale and width. Save the screenshots in `storage/app/review-media/demo-rehearsal/`.
- A list of bugs with their severity and exact steps to reproduce. These go to prompts 04, 05 or 06.
- A summary in Arabic answering one question: can this demo be shown to a client today?
