# Task 08: Results Browser Layout & Stats

## Objective
Build the structural layout for viewing deduplicated works: the `CorpusStatsBar`, `CorpusFilterSidebar`, and the main list/grid container.

## Context Constraints (For the AI Coding Assistant)
- **Do not build the detailed `WorkCard` yet.** Just use a placeholder block in the list.
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Results Browser section of the PRD.
  2. **Implement:** Build the layout, stats bar, and filter sidebar components.
  3. **Test:** Ensure responsive layout behavior (sidebar collapses on mobile).
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **CorpusStatsBar:** Displays total raw records, unique works, dedup savings %, provider breakdown, and year distribution. Must show a "Locked" badge if `corpus_locked` is true.
2. **CorpusFilterSidebar:**
   - Filters: Provider, Year range, Open-access, Has-abstract, Completeness.
   - Emits active filter states to a parent component.
3. **Layout:** Displays active filter chips above the main results list. On mobile (<768px), sidebar should be hidden behind a toggle button.

## Acceptance Criteria
- [ ] Stats bar correctly displays the aggregated metrics.
- [ ] Filter sidebar allows selection and emits filter state correctly.
- [ ] Layout responds correctly to desktop/tablet/mobile breakpoints.
