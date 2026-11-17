# CLAUDE.md

Follow `AGENTS.md` (project rules) — it is the single rule file for all agents.

When running as the orchestrator, follow `agents/ORCHESTRATOR.md` and start each
iteration with `agents/LOOP_PROMPT.md` (or the `/next-task` command).

Subagents are defined in `.claude/agents/`:

| Agent | Model | Use for |
|---|---|---|
| `implementer` | sonnet | Domain Actions, models, migrations, controllers, Livewire, Filament resources |
| `ui-builder` | sonnet | Blade components, layouts, pages, Tailwind/tokens, screenshots |
| `test-writer` | haiku | Pest feature/unit/arch tests from an acceptance list |
| `chore` | haiku | Translations, seeders, factories, docs, registry rows, config |
| `reviewer` | opus | Independent code/security review of a finished task diff (high-risk tasks) |
| `prd-auditor` | sonnet | Requirement coverage, scope creep, OPEN-item and AI-line check (every task) |
| `qa-verifier` | sonnet | Runs the real app in a browser: journeys + edge cases AR/EN (user-visible tasks) |
| `ux-reviewer` | opus | Scores screenshots against design system and UX principles (UI tasks) |

The orchestrator runs on the strongest available model, writes tests first (via test-writer), and never skips the verification wave.
