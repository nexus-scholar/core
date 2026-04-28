# Task 06: Query Builder Form & YAML Sync

## Objective
Build the visual `QueryBuilderForm` and its bi-directional synchronization logic with a YAML editor.

## Context Constraints (For the AI Coding Assistant)
- **Do not implement the Saved Query Library drawer yet.** Just add the button placeholder.
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the YAML sync model and QueryBuilderForm specs.
  2. **Implement:** Build the form, the YAML editor component, and the state machine connecting them.
  3. **Test:** Write unit tests for the state machine transitions.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **Form Controls:** Keywords (AND/OR/NOT), Title/Full-text toggle, Year range, Max results (1-10000, warn >2000), Provider checkboxes.
2. **YAML State Machine:**
   - `synced`: Form and YAML match. Editing one updates the other.
   - `manual-edit`: User edits YAML directly. Form becomes read-only.
   - `parse-error`: YAML is invalid. Save disabled. Line/column error surfaced.
   - `diverged`: YAML uses advanced flags. Form disabled with banner.
3. **Validation:** Ensure `from_year` <= `to_year` and at least one provider is selected before enabling submission.

## Acceptance Criteria
- [ ] Visual form updates generate valid YAML.
- [ ] Editing the YAML updates the visual form (in synced state).
- [ ] State machine correctly transitions to `manual-edit` and `parse-error` based on user input.
