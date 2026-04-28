# Task 11: Lock Corpus Flow & Banner Machine

## Objective
Implement the irreversible `LockCorpusModal` and the complex state machine for the post-lock banner.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.** Use mocked API endpoints to simulate lock validation and success.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Lock Flow and Post-lock banner machine specs in the PRD.
  2. **Implement:** Build the modal, validation logic, and the banner state machine.
  3. **Test:** Write unit tests for the 3-state banner dismissal logic (0-1hr, 1-48hr, >48hr).
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **LockCorpusModal:**
   - Corpus summary, Delta block (if >1 run).
   - Partial/failed run warning block (if applicable) requiring a checkbox acknowledgement.
   - Typed confirmation: `Type "[project name]" to confirm`. Case-sensitive.
2. **Post-lock Banner Machine (Overview page):**
   - 0–1 hr: Non-dismissible.
   - > 1 hr: Dismissible with ✕ control.
   - > 48 hr: Auto-hides.
   - Replaced by a permanent `LifecycleStatusBar` chip upon dismissal or auto-hide.

## Acceptance Criteria
- [ ] Lock action is disabled if typed name does not match exactly.
- [ ] Acknowledgement checkbox correctly blocks submission when partial run warning is present.
- [ ] Banner strictly follows the time-based dismissal rules.
