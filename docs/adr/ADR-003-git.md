# ADR-003 — Branching, commits and releases

- **Status:** Accepted (orchestrator, T-P0-05) · items marked **proposed** need owner/lead confirmation before adoption
- **Date:** 2026-11-16
- **Refs:** AGENTS.md rule 9; `agents/ORCHESTRATOR.md` §8; `agents/README.md` Setup; `agents/run_loop.sh`; DMC §12; Checklist §1
- **Current state (2026-11-16 continuation):** private remote `https://github.com/ibrahimmaher2503-source/young-go-lms` exists; published baseline is `3caa7127935c03080c29dbf0f81c54b98ff5f0fe` on `main`. This local folder is now attached on `feat/T-P0-05-module-map`. Existing local files were preserved; their differences from the published baseline are not merged or pushed.

## 1. Branches

| Branch | Rule | Source |
|---|---|---|
| `develop` | Proposed integration branch; published baseline already exists on main | `agents/README.md` Setup step 2 |
| `feat/<task-id>-<slug>` | One branch per task, e.g. `feat/T-P1-05-child-accounts` | ORCHESTRATOR §8 |
| `milestone/P<n>` | Milestone branch the task branches merge into, e.g. `milestone/P1` | **Proposed.** ORCHESTRATOR §8 requires "the milestone branch" but gives no name |
| `main` | Production-ready history; only released milestones land here | **Proposed.** DMC §12 requires separating dev/staging/production; no branch name given |
| `hotfix/<slug>` | Urgent production fix from `main`, merged back to `main` and `develop` | **Proposed** |

Flow: `feat/*` → `milestone/P<n>` (green gates + passed verification wave) → `develop` after the milestone gate `T-Px-MG` → `main` on release.

## 2. Merge rules

- Merge a task branch only with gates G1–G6, G10 green (+G7/G8/G9 where they apply) and the verification wave passed (ORCHESTRATOR §6–§8).
- The same commit/merge updates PROGRESS.md, REUSE_REGISTRY.md, CHANGELOG.md, TRACEABILITY_MATRIX.md (ORCHESTRATOR §8).
- Never commit secrets; `.env.example` has keys only (DMC §12, T-P0-03).
- The headless loop stops when the tree is dirty after an iteration (`agents/README.md`, `run_loop.sh`).
- No history rewrites on `develop`, `milestone/*` or `main` (**proposed**).

## 3. Commits

- Format: `type(scope): summary [T-Px-yy]` — small and focused (AGENTS.md rule 9).
- `type`: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `ci`, `perf` (**proposed** list; AGENTS.md gives only the format).
- `scope`: the module folder from ADR-002 in lower case (e.g. `entitlements`, `commerce`), or `docs`, `ci`, `agents` (**proposed**).
- Concise English (AGENTS.md rule 10).

## 4. Releases and tags

- Tag each milestone accepted at its gate on `develop`: `p<n>-mg` (e.g. `p1-mg`) (**proposed**).
- Tag production releases on `main` with SemVer `v<major>.<minor>.<patch>`; first production cutover = `v1.0.0` (**proposed**).
- Checklist §1 requires repository URL, default branch, branch convention and release tags to be recorded: the proposed integration branch (`develop`) and convention are recorded here; repository URL is recorded above; actual remote default remains `main`. `develop` is a proposed integration branch, not an observed default.

## 5. Environments (DMC §12)

`develop` → local/dev; `milestone/*` or `develop` → staging; `main` → production only after a release decision; the current `main` contains documentation and is not deployed. Staging and production hosts are UNKNOWN (B-05). Never develop on production; never touch legacy production (AGENTS.md rule 1).
