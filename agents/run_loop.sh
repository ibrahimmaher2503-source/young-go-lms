#!/usr/bin/env bash
# Young Go LMS - headless orchestrator loop (Claude Code).
# Runs N iterations; each iteration = one task via agents/LOOP_PROMPT.md.
# Usage: agents/run_loop.sh [iterations=5] [model=opus]
# Review the permission settings below before use. Run only on a dev machine / repo,
# never with access to legacy production credentials.
set -euo pipefail

ITERATIONS="${1:-5}"
MODEL="${2:-opus}"
LOG_DIR="agents/logs"
mkdir -p "$LOG_DIR"

for i in $(seq 1 "$ITERATIONS"); do
  ts="$(date +%Y%m%d-%H%M%S)"
  echo "=== Iteration $i/$ITERATIONS ($ts) ==="

  # Stop if PROGRESS.md says the loop must stop.
  if grep -q '^LOOP_STATE: STOP' agents/PROGRESS.md; then
    echo "LOOP_STATE: STOP found in PROGRESS.md - exiting."; break
  fi

  claude -p "$(cat agents/LOOP_PROMPT.md)" \
    --model "$MODEL" \
    --allowedTools "Read,Edit,Write,Grep,Glob,Task,Bash(composer *),Bash(php artisan *),Bash(vendor/bin/*),Bash(npm *),Bash(git status*),Bash(git diff*),Bash(git add*),Bash(git commit*),Bash(git checkout*),Bash(git switch*),Bash(git merge*),Bash(git log*)" \
    2>&1 | tee "$LOG_DIR/iteration-$ts.log"

  # Safety: stop if the working tree is dirty after an iteration (orchestrator must commit).
  if [ -n "$(git status --porcelain)" ]; then
    echo "Working tree not clean after iteration - stopping for human review."; break
  fi
done
