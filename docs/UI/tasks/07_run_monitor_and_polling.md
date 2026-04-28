# Task 07: Run Monitor & Provider Polling

## Objective
Implement the `RunStatusCard` and `ProviderProgressTable` to monitor async search executions.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.** Use mocked API polling responses to simulate run progression.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Search Run System specs in the PRD.
  2. **Implement:** Build the RunStatusCard, the ProgressTable, and the polling hook/logic.
  3. **Test:** Verify skeleton loading and terminal state freezing.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **RunStatusCard:** Shows status badge (`queued`, `running`, `partial`, `completed`, `failed`, `cancelled`), timestamps, raw/unique counts. Enables "View Results" only on `completed` or valid `partial`.
2. **ProviderProgressTable:**
   - Initial state: 7 shimmer skeleton rows.
   - Row states: pending, success, soft fail (red ✗ + inline error), rate-limited.
   - 150ms fade-in per row as data arrives.
3. **Polling Logic:** 
   - Shows trust timestamp: `Last updated: N seconds ago · Auto-refreshing every 10s`.
   - Freezes polling and timestamp when the run reaches a terminal state.

## Acceptance Criteria
- [ ] 7 skeleton rows appear before provider data resolves.
- [ ] Polling interval respects the 10s rule and completely stops on terminal states.
- [ ] Soft and Hard failures display appropriate UI states and tooltips.
