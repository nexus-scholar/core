# Task 15: Export Builder & History

## Objective
Implement the 3-step `ExportBuilder` flow, live previews, and the `ExportHistory` list.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Export System specs and Export Gating rules in the PRD.
  2. **Implement:** Build the stepper, format grid, gated states, and preview panels.
  3. **Test:** Verify pre-lock banner logic and gated card tooltips.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **Stepper Flow:** Step 1: Scope (All / Stage 1 / Stage 2 / Custom) → Step 2: Format (Multi-select) → Step 3: Options (Filename, Fields).
2. **Live Previews:** Syntax-highlighted text area for BibTeX/RIS/JSON. Mini table for CSV. Tabs for multi-format.
3. **Gating Rules:**
   - "Annotated Bibliography" and "PRISMA Flowchart" are locked/greyed out.
   - Use plain-language tooltips for standard users, technical tooltips for Admins.
   - Never show gated formats as "error" or "broken".
4. **Pre-lock State:** If `corpus_locked` is false, show banner: "This corpus is not yet locked. This export is not citable."
5. **History List:** Shows generated exports, allows re-download, and provides "Duplicate settings" action.

## Acceptance Criteria
- [ ] Gated formats render correctly according to PRD copy rules.
- [ ] Pre-lock banner is visible when `corpus_locked` is false and disappears when true.
- [ ] Live preview updates based on selected formats.
