# T-P4-03 visual match (G7)

Screen: course player (`/learn/{course}/{item}`).
Reference: `07-recorded-player-ai-v2.png` and `17-mobile-lesson-assistant.png`.

Captured in tests/Browser/screenshots/T-P4-03 (AR/EN at 390 and 1440, axe clean, direction checked; mobile also captures the curriculum drawer).
Matches: lesson title and type badge, media stage with a watermark slot, toolbar (speed, captions, full screen), previous/next, curriculum list with the current lesson highlighted, locked items with their reason, free-preview badge.
Known differences: the assistant panel is not built (a slot is rendered only when the assistant is available; P7 fills it); the page keeps the shared 5xl layout width, narrower than the reference's wide player; no progress bars (T-P4-04); the video in the captures is an empty fixture so the browser shows its idle player.
