# Task backlog format

Each phase file `Pxx.md` lists tasks in execution order. One task = one
reviewable vertical change (typically 1–3 days of human work; S/M/L sizes are
relative, not commitments — O-022).

```markdown
### T-P3-04 — Title
- deps: T-P3-01, T-P3-02          # must be done first
- agents: implementer, ui-builder, test-writer, chore   # suggested subtask owners
- size: S | M | L
- risk: normal | high             # high → independent reviewer + extra gates
- ai: none | hook | core — <exact instruction>   # AI touchpoint (AI spec §23)
- refs: PRD §9.6, YG-GAP-VID-001, Arch §10.1, Checklist §5
- gates: G1-G6, G7                # beyond always-on G1-G6
- blocked-if: O-005 needed for provider X (optional)

**Goal** one paragraph: what works for which user when done.
**Steps**
- [ ] Implement the first step.
**Acceptance**
- [ ] A1..An — each must map to a test or evidence.
**Out of scope** what not to build here.
```

Rules:
- Do not reorder or renumber tasks; add new ones as `T-Px-NNa` and record in PROGRESS.md.
- Every phase ends with a milestone gate task `T-Px-MG`.
- If a task is too big for one review, the orchestrator splits it into
  subtasks — not into new tasks — unless the split changes acceptance.

## UI task references

Open the task's `visual` line before coding. Its PNG links identify the screen or reusable layout for that task. Apply the task's own fields, actions and permissions. For other UI work, select the closest pattern from the [page map](../../docs/design-system/reference-pack-2026-11-17/PAGE_MAP.md) and record the reference before implementation. Follow the [fast implementation guide](../../docs/design-system/reference-pack-2026-11-17/IMPLEMENTATION.md) and close the task only after G7 visual matching passes.

## Task checklists

Check implementation steps when the work is done. Check acceptance items after recording the test or evidence. Keep the A1..An IDs so G10 can match them. For UI, record the actual screenshot and comparison against the linked reference before checking G7. PROGRESS.md records task status and evidence.
