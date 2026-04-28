# Task 13: Screening Mobile Gestures

## Objective
Enhance the `ScreeningWorkspace` with touch-optimized targets and swipe gestures specifically designed to avoid OS browser navigation conflicts.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.** You are extending the workspace built in Task 12.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Mobile Screening section of the PRD.
  2. **Implement:** Integrate a gesture library (e.g., `useGesture`) and implement vertical swipes.
  3. **Test:** Ensure horizontal swipes do nothing (preventing accidental back/forward browser navigation).
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **Gestures:**
   - Swipe Up (from bottom third) = Include.
   - Swipe Down (from top third) = Exclude.
   - Long press (500ms) = Maybe.
   - NO horizontal swipes.
2. **Mobile Layout Adjustments:**
   - Decision buttons become full-width tap targets in the thumb zone.
   - Top bar collapses to a single-line progress pill.
3. **First-Session Prompt:**
   - Bottom sheet explaining gestures.
   - One-time fullscreen prompt using `document.requestFullscreen()`.

## Acceptance Criteria
- [ ] Vertical swipe gestures map accurately to Include/Exclude actions.
- [ ] Horizontal gestures do not trigger screening actions.
- [ ] Fullscreen prompt and gesture tutorial appear only once per device session.
