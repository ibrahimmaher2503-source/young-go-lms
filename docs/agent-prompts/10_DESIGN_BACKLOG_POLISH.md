# 10: Design backlog polish (optional, after the functional work)

> Read `docs/agent-prompts/00_COMMON.md` first. Agents: `ui-builder` does the work, and an independent `ux-reviewer` scores it.

## Background
The owner accepted P8 and P9 with these design defects still open: **D2, C1, H1, X6, X7, W1b, P1, R2**. The details are in `docs/reviews/G7_P8_*` and `G7_P9_*`. Many tasks in P1–P7 are marked G7 VISUAL DEFERRED, for example T-P1-08 (scored 4.25), T-P1-10 and the T-P2-0x tasks.

## Method
1. Gather every open visual defect from the G7 reports and the `*_VISUAL_MATCH.md` files into one list. Rank it by how often users see each page, starting with home, catalog, course detail, checkout, player and the dashboards.
2. Fix the defects using only `resources/css/tokens.css` and the shared components. Do not add hard-coded colours or fonts.
3. After each batch, run an independent review: a scratch database, Playwright at 390px and 1440px in Arabic and English, and a mean over the six dimensions. The bar is a mean of 4.5 with every dimension at 4.0 or higher.

## Output
- `docs/reviews/G7_BACKLOG_POLISH_2026-11-xx.md` with these columns: defect | page | score before and after | commit.
- A summary in Arabic.
