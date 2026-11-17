# T-P5-03 visual match (G7)

Screens captured: `tests/Browser/screenshots/T-P5-03/` (live card and dashboard schedule, AR/EN, 390 and 1440).

- Shell, curriculum column, course header, progress card and badges reuse the player layout of reference 07 (recorded player); no layout was changed.
- The live card uses the same card, radius, shadow, primary button and badge tokens as the dashboard cards of references 04 and 16.
- Dashboard "Coming up" card keeps the reference position and title; rows show the session, course, time in the learner timezone and a Join or Open link.

Open differences (not explained by the references):
- The references contain no live session card, so its inner layout (icon, time, countdown, join button) is a new composition from existing tokens and has no reference to compare against. Owner decision needed before G7 can close for this card.
- Date and time inside Arabic text mix digits and words; direction is correct but the order of the time range follows the browser bidi rules.
