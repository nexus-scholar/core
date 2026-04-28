# Task 05: Project Wizard & Lifecycle Bar

## Objective
Implement the `ProjectWizard` for creating new SLR projects and the `LifecycleStatusBar` for tracking a project's progression.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.** Assume API endpoints will be wired later; use mocked state or build the corresponding API controllers if capable in one session.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review ProjectWizard and LifecycleStatusBar specs in the checklist.
  2. **Implement:** Build the wizard modal and the horizontal stepper.
  3. **Test:** Verify form validation and stepper visual states.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **ProjectWizard:**
   - 3-step modal: Basics (Name, Question) → Template (PRISMA, Cochrane, Scoping, Rapid, Custom) → Team invite.
   - "Next" disabled until required fields are valid.
   - Submission shows spinner and disables inputs.
2. **LifecycleStatusBar:**
   - 6 stages: Draft, Active Search, Corpus Locked, Screening, Reporting, Archived.
   - States per node: Completed (filled + check), Current (pulsing ring), Upcoming (empty), Blocked (warning icon).
   - Must support a persistent chip fallback for post-lock banner replacement: `ℹ Corpus locked — view details`.

## Acceptance Criteria
- [ ] Wizard accurately transitions through steps and blocks progression on invalid input.
- [ ] Lifecycle bar accurately reflects the 6 stages and visual states (completed, current, upcoming, blocked).
