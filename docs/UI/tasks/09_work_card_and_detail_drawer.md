# Task 09: Work Card & Detail Drawer

## Objective
Implement the `WorkCard` (list item) and the `WorkDetailDrawer` (expanded view) for displaying deduplicated works.

## Context Constraints (For the AI Coding Assistant)
- **Do not build the `SourcesPanel` yet.** That is a separate task. Leave a placeholder for it in the drawer.
- **Focus ONLY on this task.**
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the WorkCard and WorkDetailDrawer specs in the PRD.
  2. **Implement:** Build the components, including the 6-square micro-grid and the confidence pill.
  3. **Test:** Ensure tooltips render correctly and the micro-grid collapses below 260px.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **WorkCard:**
   - Displays title, authors, venue, year, abstract preview.
   - **Completeness Micro-grid:** 6 squares (DOI, ABS, AUTH, VEN, CIT, ORCID). Green = present, Amber = missing. Includes specific tooltips. Collapses to `[N/6]` pill at card width < 260px.
   - **Confidence Pill:** 🟢 High (DOI), 🟡 Medium (arXiv/S2), 🔴 Low (Heuristic).
2. **WorkDetailDrawer:**
   - Full metadata display.
   - **4-chip identity row:** DOI, PMID, arXiv ID, S2 ID. Active vs Greyed states based on presence.
   - Abstract expand/collapse.

## Acceptance Criteria
- [ ] Micro-grid correctly reflects the boolean payload and collapses gracefully.
- [ ] Identity chips correctly allow copying/linking when present.
- [ ] Tooltips accurately describe the match basis and missing fields.
