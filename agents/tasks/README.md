# Task backlog format

Each phase file `Pxx.md` groups tasks by capability. Schedule from explicit
dependencies across phases, not file order. One task = one reviewable change;
S/M/L sizes are relative, not duration or staffing commitments (O-022).

```markdown
### T-P3-04 — Title
- deps: T-P3-01, T-P3-02          # must be done first
- agents: implementer, ui-builder, test-writer, chore   # suggested subtask owners
- size: S | M | L
- risk: normal | high             # high → independent reviewer + extra gates
- ai: none | hook | core — <exact instruction>   # AI touchpoint (AI spec §23)
- refs: PRD §9.6, YG-GAP-VID-001, Arch §10.1, Checklist §5
- gates: G1-G6, G7                # stage applicability in VERIFICATION_GATES.md
- blocked-if: O-005 needed for provider X (optional)

**Goal** one paragraph: what works for which user when done.
**Steps** numbered implementation steps (the orchestrator turns them into subtasks).
**Acceptance** A1..An — each must map to a test or evidence.
**Out of scope** what not to build here.
```

Rules:
- Do not reorder or renumber tasks; add new ones as `T-Px-NNa` and record in PROGRESS.md.
- Every phase ends with a milestone gate task `T-Px-MG`.
- If a task is too big for one review, the orchestrator splits it into
  subtasks — not into new tasks — unless the split changes acceptance.
