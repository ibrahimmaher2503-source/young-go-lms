# Fast UI implementation and visual matching

**Owner requirement. Match the reference layout and component details for every page. Keep G7 open while any visible mismatch remains unexplained.**

1. Open the PNG on the task's visual line. Use the [page map](PAGE_MAP.md) for new tasks. Where a PNG illustrates a related screen, reuse its layout and components and take fields, actions and states from the task and PRD.
2. Start from the closest existing page. Staff lists use WorkspaceListRecords and native Filament tables, tabs, filters and actions. Staff forms use native schemas and steps. Public, learner and family pages use their respective layouts and existing x-ui primitives. Check the [component map](COMMON_COMPONENTS.md) and [reuse registry](../../REUSE_REGISTRY.md) first.
3. Build the populated state with existing demo data or a browser fixture. Compose the shell, header, content grid and cards, then bind the authorized query or action. Reuse semantic tokens and add the applicable loading, empty, error and disabled states.
4. After asset changes, run npm run build and the browser test for the affected page. Capture a screenshot at comparable dimensions, language and theme. Compare side by side or with an overlay. Align shell and grid first, then typography, spacing, colors, borders, icons, controls and states. Exclude the mockup caption and use matching fixture content where practical.
5. Run the applicable gates, including AR/EN desktop and mobile screenshots, light/dark for SKIT, keyboard and axe checks. Save screenshots under tests/Browser/screenshots/<task-id>/. Record the reference file, viewport, language, theme, screenshot path and comparison result. Document intentional differences required by the PRD or the displayed state, and fix remaining visual mismatches before closure.

The PRD and Decision Register govern behavior and authorization. Follow the [visual acceptance standard](../VISUAL_ACCEPTANCE.md) and use the selected PNGs in the manifest throughout review.
