# Decisions approved 2026-11-18 (PO) - summary

Full table: `docs/07_DECISION_REGISTER.md`, section D. Legal or accounting verification is still advised for O-011, O-013, O-014, O-015, O-023, O-025, O-028.

## Money
- O-008: 70% instructor / 30% platform default (independent instructors). Rules per instructor or course override. Academy-owned courses: no instructor earnings.
- O-009: bundles allocated proportionally to member courses' list price.
- O-010: subscription earnings = 70% of the period price, split equally across the plan's courses, per student-course-period.
- O-011: base is net of discount, excluding VAT; platform bears gateway fees; refunds reverse proportionally.
- O-012: monthly settlement; bank transfer (EG also wallet/Instapay); minimum payout 500 EGP / 100 SAR (configurable); four-eyes (batch creator cannot approve unless super_admin).
- O-006: Paymob EG cards + wallets, Instapay manual proof. O-013: Paymob KSA (market stays disabled). O-023: ETA adapter disabled.
- O-024 no auto-renew; O-017 no family plan.

## Learners and safety
- O-015: current age bands; AI off under 18 pending legal review; guardian check = parent link + OTP.
- O-016: parent sees help status only. O-028: safeguarding routes to admin + support (draft wording).
- O-020: completion = all required items + quiz pass 60% (per-course configurable). O-025: retention defaults kept; financial >= 7 years (EG 5, KSA 6); learning data active + 2 years.
- O-018: migrate courses/content, users, enrollments, progress, certificates, quiz results, orders; not AI chat history.

## Providers and platform
- O-014 OpenAI (inert without key, never for minors). O-005 Bunny Stream. O-021 Amazon SES + Google Meet. O-029 Sentry.
- O-030: 2000 learners, 300 concurrent, 150 in a live session. O-002 repo ibrahimmaher2503-source/young-go-lms, branch feat/lms-build. O-004 MySQL 8 (SQLite local/tests), PHP 8.4 (note: composer.json requires ^8.4).

## Still OPEN (owner facts)
O-001 DNS/hosting access, O-003 legacy source/DB, O-007 seller name/tax id/VAT, O-019 certificate issuer wording, O-022 dates/budget, O-026 SKIT kit, O-027 brand assets, O-006 Paymob credentials, O-013 Saudi legal entity.
