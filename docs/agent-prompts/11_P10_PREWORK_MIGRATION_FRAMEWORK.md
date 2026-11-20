# 11: Migration pre-work before legacy source access (P10)

> Read `docs/agent-prompts/00_COMMON.md` first. T-P10-01 is blocked on O-003. This work **does not close** any T-P10-xx task. It shortens the work once access arrives.

## Goal
Build a migration framework that runs end to end on a synthetic dataset shaped like the legacy schema. Switching it to the real source must take only a configuration change.

## Steps
1. Write a **draft** mapping spec in `docs/migration/MAPPING_DRAFT.md`, based on `docs/migration/INVENTORY.md`, `docs/08_LEGACY_MIGRATION_PLAN.md` and `docs/LEGACY_PARITY.md`. For each source table and column, give the target model and field, any transformations, and open questions. Mark every assumption `ASSUMPTION` until someone has seen the real source.
2. Build the framework in `app/Domain/Migration`, or wherever the architecture docs say it belongs:
   - A separate, read-only source connection named `legacy`, configured through config.
   - One idempotent importer per entity, with an ID map table (legacy_id → new_id) and checksums.
   - `--dry-run` as the default, batching, the ability to resume after a failure, and a count report showing source vs imported vs rejected, with the reason for each rejection.
   - A URL redirect map from the legacy paths to the new ones.
   - Every write to entitlements goes through the Entitlements module (I01).
3. Create a synthetic fixture dataset in SQLite that follows the legacy schema in the inventory, so the framework can be run end to end.
4. Add the command `artisan legacy:import {entity} --dry-run`, and a `legacy:verify` command that compares counts and samples.

## Output
- The commits, the draft mapping spec, the test prompt `docs/test-prompts/T-P10-PREWORK.md`, and this note on T-P10-02 and T-P10-03: "pre-work, awaiting O-003".
- A summary in Arabic that lists the questions only the real source can answer.
