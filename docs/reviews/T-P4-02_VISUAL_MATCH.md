# T-P4-02 visual match (G7)

Screen: Manual grants (`/admin/manual-grants`, `/finance/manual-grants`).
The reference pack has no staff grants screen; the course directory reference (02) was used for the table language. The screen reuses the shared workspace list (summary strip, filters, table, pagination) and tokens, so it matches the other staff lists rather than a dedicated mock.

Captured in tests/Browser/screenshots/T-P4-02 (AR/EN at 390 and 1440, axe clean, direction checked): summary strip (active / expired / revoked and per-source counts), filters, rows with status badge and lifetime/end date, header actions Grant access and Export CSV.
Known difference: learner email sits in its own column (the two-line cell failed contrast in axe).
