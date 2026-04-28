# Task 10: Sources Panel (Dedup Audit)

## Objective
Implement the `SourcesPanel` which lives inside the `WorkDetailDrawer` to show the raw records merged into the representative work.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the SourcesPanel specs in the PRD.
  2. **Implement:** Build the collapsible panel and the data table.
  3. **Test:** Verify the representative row highlighting logic.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **Collapsed State:** "Found in [N] sources — click to inspect" + provider logos.
2. **Expanded State:** 
   - Top: `WorkIdSet` 4-chip identity key row (DOI / PMID / arXiv ID / S2 ID).
   - Table: Provider, Raw title, DOI present, IDs present, completenessScore.
   - Highlight the row that was elected as representative with a "✓ Representative" chip.
   - Show tie-break rule tooltip if multiple rows have the same score.

## Acceptance Criteria
- [ ] Panel defaults to collapsed.
- [ ] Table accurately renders the raw source data.
- [ ] Representative row is clearly distinguished from merged rows.
