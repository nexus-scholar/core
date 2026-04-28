# Task 14: Conflict Queue & Resolution

## Objective
Build the `ConflictQueue` surface for reviewing and adjudicating disagreements between AI models (or human reviewers).

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the ConflictQueue and Progress Accounting specs in the PRD.
  2. **Implement:** Build Layout A (reasoning present) and Layout B (reasoning absent).
  3. **Test:** Verify the distinct conflict progress counter behaves independently of the main queue.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **Queue Structure:** Separated from the main screening queue. Tracks "N of M conflicts resolved".
2. **Comparison View:** Side-by-side columns (Model A / Model B / Model C) showing recommendation, confidence, and reasoning.
3. **Layout B (No Reasoning):** If the API returns no reasoning, show muted italic "No reasoning provided" and display the full abstract below the columns. Show "—" for missing confidence scores.
4. **Resolution:** Human decision controls (same as main workspace) resolve the conflict. Original AI decisions remain visible in history.

## Acceptance Criteria
- [ ] Conflicted works are correctly omitted from the main queue denominator.
- [ ] Layout cleanly handles missing reasoning and missing confidence scores.
- [ ] Resolving a conflict moves the work to a "resolved" section.
