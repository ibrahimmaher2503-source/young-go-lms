# Agent loop — Young Go LMS methodology

This repository is a specification and instructions pack. Reviewing it does not
implement or verify the future application.

## Entry points
| File | Purpose |
|---|---|
| `../AGENTS.md` | Shared project rules |
| `../CLAUDE.md` | Claude-specific role/model preferences |
| `../.claude/agents/*.md` | Builder and independent reviewer profiles |
| `../.claude/commands/next-task.md` | Interactive task entry |
| `ORCHESTRATOR.md` | Select, scope, implement, review, verify and record |
| `LOOP_PROMPT.md` | One iteration prompt |
| `SUBAGENT_BRIEF_TEMPLATE.md` | File ownership, acceptance and evidence brief |
| `REVIEW_PROTOCOL.md` | Review criteria and verdicts |
| `VERIFICATION_GATES.md` | Gate applicability, bootstrap, evidence and reset safety |
| `tasks/P00.md … P12.md` | Task specifications and dependencies |
| `../docs/TRACEABILITY_MATRIX.md` | Planned requirement-to-task coverage |
| `PROGRESS.md` | Task status and evidence references |

## Setup for an authorized implementation
1. Create/select the intended repository and copy the pack into its root.
2. When repository setup/commits are authorized, use `develop`; preserve unrelated changes.
3. Supply available T-P0-01 facts. Inventory acceptance may explicitly permit UNKNOWN
   values; missing credentials block dependent slices, not independent local work.
4. Never connect execution to legacy production credentials or bypass host permissions.

## Interactive use
Give the host `agents/LOOP_PROMPT.md` and a task ID; state whether the work is a
pack review or application implementation. Claude Code may use `/next-task [ID]`.
Use the configured available model; Claude profile aliases do not establish model
availability or Codex capabilities. No unattended launcher is supplied.

Codex reads `AGENTS.md`. When delegation is authorized and host tools support it,
use actual subagents with the brief template. Otherwise perform implementation
stages sequentially. A role prompt executed by the implementer is self-review,
not independent review. Required reviews need a separate agent/human/session;
record unavailable review as NOT RUN and retain pending status.

Docs-only review checks references, IDs, dependencies, consistency and acceptance
evidence; absent Laravel commands are N/A. Bootstrap tasks introduce application
checks in the order specified in VERIFICATION_GATES. Neither a task map nor test
tags prove execution, visual acceptance, provider readiness or production safety.

## Owner involvement
- Resolve missing decisions and credentials for dependent work.
- Review milestone evidence and screenshots.
- Authorize gated actions under `docs/09_CODEX_START_HERE.md`, including live
  payments, provider/minors activation, migration scope and cutover.
