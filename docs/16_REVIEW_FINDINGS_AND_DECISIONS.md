# Review findings and engineering decisions — 2026-11-16

Status: specification/methodology review corrections integrated. This deliverable contains instructions; application implementation is outside this review. These are agent engineering
decisions within the requested review, not new owner approval of commercial/legal/privacy policies.

| ID | Finding / decision | Adopted treatment |
|---|---|---|
| REV-01 | Planned requirement links were described as zero gaps | Audit exact IDs (D-* must not match ED-*); map acceptance scenarios and evidence separately |
| REV-02 | Documentation and implementation verification were conflated | Keep application tasks todo; require commands/results/artifacts/current revision before done |
| REV-03 | All-always gates reference nonexistent application commands | Stage pack, bootstrap, application and release gates; absent required command = NOT RUN |
| REV-04 | Codex instructions imply subagents unavailable | Discover actual host capabilities; use independent reviewers and disjoint writable scopes; unavailable review stays pending |
| REV-05 | Source hierarchy conflicts between documents | Latest explicit owner instruction, approved decisions, PRD, AI/architecture, engineering defaults, tasks, workflow; recommendations cannot override approval |
| REV-06 | Guardian creation can imply verified legal guardianship | Separate account linkage from verification and consent; gate real-minor activation on approved policy |
| REV-07 | Refund/access overlap and late payment need explicit contracts | Revoke source-specific grants only; preserve other valid grants; immutable payment snapshots and ledger reversals; test replay, out-of-order and last-seat races |
| REV-08 | AI streaming conflicts with quote verification | Show pending status until validated output; recheck access/source revision before response; no cross-learner private-history cache |
| REV-09 | SOLID can cause unnecessary wrappers | Review SRP by reason to change; OCP/DIP at real provider boundaries; LSP via adapter contract tests; ISP via small consumer needs; composition before inheritance |
| REV-10 | UI quality lacks executable evidence | Shared tokens/components; AR/EN mobile/desktop and staff themes; loading/empty/error/locked states; keyboard, focus, contrast, zoom and observed journeys |
| REV-11 | LearnHouse study overstates inspection | Official documentation is pattern evidence, not runtime acceptance; editor mobile support and progress deletion must not be assumed suitable for Young Go |
| REV-12 | Headless launcher broadly preauthorizes commands | Remove unfinished launcher; use scoped interactive execution with normal host permissions |

## Documentation verification — 16 November 2026

- Reviewed product, architecture/AI, task planning, UI/reference and agent-methodology documents in
  separate scoped subagent passes; root read integrations and corrected remaining cross-file conflicts.
- In-memory Python checks: 123 unique task definitions, 123 matching progress rows, all `todo`;
  metadata/acceptance sections present, referenced dependencies resolve, no dependency cycle and exact
  board/spec dependency agreement. No application commands were run.
- 99 defined YG requirements (including 16 YG-REV) have matrix rows, with no missing/undefined YG IDs.
  Matrix includes 25 D decisions and 37 ED decisions using separate exact namespaces.
- Markdown fences and local Markdown links checked. This is structural evidence, not a proof that
  every narrative business requirement is complete or that future implementation will be correct.
- Sharing detection was an explicitly excluded requirement with no owner; T-P9-10 now owns admin-only
  review flags based on T-P4-07 facts. No automatic ban or accusation is introduced.
- Removed the unfinished audit-only Python validator/tests and headless launcher. Their prior failures
  concerned those temporary tools, not application bugs or a requirement to supply code in this pack.

## Remaining decision boundaries

Commercial refund/dispute terms (O-031), subscription terms (O-032), rates/bases (O-008..012),
provider selection/processing, guardian authority/consent/privacy (O-014..016/O-025/O-028), brand/SKIT
assets, migration scope and deployment approvals remain explicit gates. Engineering can proceed within
approved scope using synthetic fixtures; an agent cannot silently approve these policies.

LearnHouse documented patterns and source URLs are in `14_LEARNHOUSE_REFERENCE_STUDY.md`.
No account-based LearnHouse runtime or visual acceptance was claimed. Public documentation informed
pattern choices; Young Go requirements take precedence.

Instructions reduce hallucination risk but do not guarantee it. Future acceptance requires an actual
requirement-to-behavior evidence map, independent review and applicable runtime checks. No application,
live payment, migration, deployed UI or production acceptance was tested or required in this review.
