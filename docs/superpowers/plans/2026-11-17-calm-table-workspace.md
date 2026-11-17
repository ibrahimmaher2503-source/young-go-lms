# Calm Table Workspace Implementation Plan

> **For agentic workers:** Use subagent-driven-development for independent scopes; root reviews and verifies integration.

**Goal:** Apply the approved calm workspace through reusable fast-to-compose Filament table pages.
**Architecture:** Native ListRecords remains the table owner; shared base adds optional summary and schema sidebar. One token/theme layer controls appearance.
**Tech Stack:** Laravel13, Filament5.11.4, Livewire4, Tailwind4, Pest5.
**Spec:** docs/superpowers/specs/2026-11-17-calm-table-workspace-design.md

## Global constraints
No backend mutations/new features, no fake production data; preserve existing permission/audit boundaries, bilingual strings and dark mode; existing older local docs must be preserved. Approval is already given by owner. AI none.

## Review focus
Empty and long table data; sidebar hidden from unauthorized roles; theme selector parity; mobile scroll containment; native actions and filter semantics survive wrapping.

## Work
- [x] Luna shell: tests first; build WorkspaceListRecords and summary strip; preserve native table/hooks; absent sidebar full width.
- [x] Luna theme: token-based slate/white/teal theme, dark support, responsive summary/cards, logical CSS.
- [x] Root integration: failing data/scoping assertions, adopt base in three list pages, real users summary/role breakdown/activity cards; native tab/filter reuse.
- [x] Root instructions: decision D-026, approved screenshot, design rules, cookbook/reuse registry and future agent contract.
- [ ] Root verification: focused tests, Pint/Larastan, translations/RTL/build; seeded AR/EN mobile/desktop both themes screenshots; independent Luna PRD/UX/source review. Repair failures and report limits honestly.

Root owns all refinements after the owner rejected the first visual implementation. Native controls remain, with personally reviewed shared typography, component spacing, table inset, headers, meters, modal contrast and keyboard behavior. Independent agents review read-only; their source PASS cannot replace visual evidence.
