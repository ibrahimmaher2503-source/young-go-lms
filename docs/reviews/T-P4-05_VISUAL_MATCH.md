# T-P4-05 visual match (G7) and learner shell rework

References: `04-learner-dashboard.png`, `16-mobile-learner-dashboard.png`, `07-recorded-player-ai-v2.png`, `17-mobile-lesson-assistant.png`.
The first learner UI (T-P4-03) used the generic narrow layout and did not match these references. This pass rebuilt it.

Rebuilt to match: a dedicated learner shell (Young Go lockup with book mark, pill navigation with icons and active tint, bell with unread dot, language switch, account menu, four-item bottom navigation on phones, Noto Sans Arabic as the page font); dashboard hero (welcome line, "continue learning" card with cover, lesson title, lesson progress and a full-width primary action), "my enrolled courses" card with cover, instructor and percentage beside a rounded progress track, side cards for schedule / tasks / certificates / notifications; player with a back link, course title and instructor, curriculum column with course progress and section cards (check / play / empty circles, minutes), lesson title with "Lesson n of N", rounded video stage, materials tabs (PDF and attachments), next-lesson button with the lesson title.

Captured in tests/Browser/screenshots/T-P4-03, T-P4-05 (AR/EN, 390 and 1440, axe clean, direction checked, empty states included).

Known differences (explained): course and lesson covers are brand tiles because courses have no cover image until the course wizard media step (T-P3-03); the side cards show their "nothing yet" states until live sessions, assignments and certificates exist; the lesson assistant column and the 08:24 timestamp chip appear only when the assistant exists (P7); the mobile bottom navigation has Learning / My courses / Link or Family / Profile because Schedule and Certificates pages do not exist yet; the video in captures is an empty fixture.
