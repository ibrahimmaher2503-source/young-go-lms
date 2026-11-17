# Agent loop — how to run Young Go LMS from first task to launch

## What is here
| File | Purpose |
|---|---|
| `../AGENTS.md` | Project rules for every agent (Codex reads it automatically) |
| `../CLAUDE.md` | Claude Code entry; lists subagents and models |
| `../.claude/agents/*.md` | Builders: implementer, ui-builder (sonnet), test-writer, chore (haiku). Verifiers: prd-auditor, qa-verifier (sonnet), ux-reviewer, reviewer (opus) |
| `../.claude/commands/next-task.md` | `/next-task [ID]` runs one iteration |
| `ORCHESTRATOR.md` | Orchestrator algorithm: select → plan → delegate → review → verify → commit → record |
| `LOOP_PROMPT.md` | The exact prompt for one iteration |
| `SUBAGENT_BRIEF_TEMPLATE.md` | How to brief a subagent precisely |
| `REVIEW_PROTOCOL.md` | Review checklist with BLOCKER/SHOULD/NIT |
| `VERIFICATION_GATES.md` | G1–G11 gates, verification wave, milestone gate |
| `tasks/P00.md … P12.md` | 123 tasks with deps, agents, size, risk, AI line, steps, acceptance |
| `../docs/TRACEABILITY_MATRIX.md` | Requirement → task map (regenerate after spec/task edits) |
| `PROGRESS.md` | The only status board |
| `run_loop.sh` | Headless loop for Claude Code (N iterations) |

## Setup (once)
1. Create the project repository and copy this pack in its root (`docs/`, `agents/`, `.claude/`, `AGENTS.md`, `CLAUDE.md`, continuity files).
2. Commit on `develop`. Never connect the loop to legacy production credentials.
3. Supply what you can for T-P0-01 (repo, server, legacy read-only access). Missing items become `blocked` notes; the loop continues with local tasks.

## Run interactively (recommended at the start)
```text
claude --model opus
> /next-task            # one task end-to-end
> /next-task T-P1-05    # force a specific task
```
Watch the first 3–5 tasks closely, then switch to headless.

## Run headless
```bash
agents/run_loop.sh 5 opus      # 5 iterations, orchestrator on opus
```
The script stops when `PROGRESS.md` has `LOOP_STATE: STOP`, or the tree is dirty after an iteration.

## With Codex
Codex reads `AGENTS.md`. Give it `agents/LOOP_PROMPT.md` as the task. Codex has
no subagent definitions here, so it executes the briefs itself, in order, and
must still run the gates and the review protocol. You can mix: Claude Code
orchestrator + `codex exec "<brief>"` as an implementer for specific subtasks.

## Your role (human)
- Answer `blocked` items (credentials, OPEN decisions, owner approvals).
- Review milestone gates (`T-Px-MG`) and screenshots.
- Approve gated actions: live Paymob, AI provider + minors, migration scope, cutover.
