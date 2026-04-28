# Task 12: Screening Desktop Workspace

## Objective
Build the desktop-optimised `ScreeningWorkspace` for processing literature against inclusion/exclusion criteria.

## Context Constraints (For the AI Coding Assistant)
- **Do not build mobile swipe gestures yet.** That is a separate task. Focus on desktop layouts and keyboard shortcuts.
- **Do not build the Conflict Queue yet.**
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Screening Workspace desktop specs and AI-Recommendation mode in the PRD.
  2. **Implement:** Build the mode selector, workspace layout, decision bar, and reason picker.
  3. **Test:** Verify all keyboard shortcuts (I, E, M, U, S, etc.).
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **ScreeningModeSelector:** Solo, AI-Recommendation. Collaborative is disabled ("Coming in v2").
2. **Workspace Layout:** Fixed top progress bar, scrollable abstract area, fixed bottom decision bar.
3. **Decision Controls:** Include (I), Exclude (E), Maybe (M), Undo (U), Skip (S).
4. **ExclusionReasonPicker:** Slides up on Exclude. Supports 1-8 numeric shortcuts.
5. **AI Mode Additions:** Recommendation chip and collapsible reasoning block. Overriding AI logs the human decision as final.

## Acceptance Criteria
- [ ] Keyboard shortcuts trigger the correct actions and update the queue.
- [ ] Exclusion reason picker renders correctly and enforces mandatory reasons when configured.
- [ ] AI recommendation and reasoning are visually distinct and advisory.
