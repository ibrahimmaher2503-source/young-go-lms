# Approved calm table workspace

Owner-approved 2026-11-17 in chat: use the attached calm card reference and make its elements fast to compose on every table page. Reference: docs/design-system/references/calm-workspace-approved-2026-11-17.png.

## Direction and approaches
The owner chose the light calm direction over the purple expressive concept: pale slate canvas, white sidebar/cards, restrained teal emphasis, clear Arabic typography, thin borders, soft shadows, and compact meaningful groups. Approval covers implementing and documenting this direction. This supersedes the provisional blue backoffice visual direction; official logo/assets remain O-027. The drawn mockup logo, sample people, counts and unsupported actions are not product data or feature authorization.

## Reuse architecture
Use installed Filament5.11.4 ListRecords, Table, Schema Grid/Group/Section/View and existing Domain Actions. A WorkspaceListRecords base composes the native table/tabs/hooks with optional summary strip and optional sidebar schema components. Empty sidebar gives a full-width table. Mobile stacks content without horizontal page overflow. Native search, filters, pagination, exports and permissions remain. Summary counts use authorized resource queries; no invented awaiting-activation status. Roles/activity cards show only real, permission-allowed data. Existing users, roles and audit lists adopt the base. AI impact: none (P1-08); do not add assistant functionality.

## Developer contract
Override getWorkspaceSummary(): list of label/value/icon entries and getWorkspaceSidebar(): list of Filament schema components. Configure columns, filters/actions/tabs with native Filament APIs. Use semantic tokens, shared sections and bilingual language keys. No per-resource table markup or copy/paste layouts. Expose a compact cookbook showing a minimal page, summary, aside cards, permissions and empty/loading/error behavior.

## Acceptance
All three existing lists use the shared shell; native controls/authorization still work. Users show actual summary and role breakdown plus authorized audit activity. No summary/aside data leaks to missing permissions. Both themes and AR/EN on390/1440 are keyboard/axe/overflow checked. Add sample users only in test fixtures. Save original reference, decision and reuse/agent instructions; demonstrate a new page config without new layout CSS.


## Owner steering after first rendering

The owner rejected the first UI as visually weaker than the reference and directed root to personally refine every existing screen and enforce reference fidelity for future pages. Scope includes operational layouts, tables, dialogs/forms, three panel login/welcome pages and existing component previews; unimplemented LMS business modules are not fabricated. VISUAL_ACCEPTANCE.md and visual-contract.json make the reference/details binding. Implementation remains root-owned; subagents are read-only verifiers after this direction.
