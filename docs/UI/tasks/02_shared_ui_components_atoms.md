# Task 02: Shared UI Components (Atoms)

## Objective
Build the foundational, reusable UI components (Atoms) required by the NexusScholar interface. These must be stateless, accessible, and strictly adhere to the design system.

## Context Constraints (For the AI Coding Assistant)
- **Do not build pages or complex layouts.** Stick to isolated components.
- **Focus ONLY on this task.** Do not implement API calls.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the state requirements in the PRD.
  2. **Implement:** Build the components (Vue 3 / React) using Tailwind CSS.
  3. **Test:** Write component tests or a basic Storybook/Showcase page to render all variants.
  4. **Commit & Push:** Create a semantic commit (e.g., `feat(ui): build atomic shared components`) and push.

## Specifications
1. **Buttons:** Primary, Secondary, Ghost, Destructive. Must support `loading`, `disabled`, and `icon` slots.
2. **Inputs:** Text, Textarea, Select, Checkbox. Must support `error` state (inline red text, red border) and `disabled` state.
3. **Badges/Chips:** 
   - Severity variants: info (blue), success (green), warning (amber), error (red), muted (grey).
   - Must support an optional `icon` and an `onDismiss` callback (if dismissible).
4. **Tooltips:** Wrapper component that shows text on hover/focus. Must support keyboard focus.

## Acceptance Criteria
- [ ] All components implement: loading, error, empty, partial, gated, and default states where applicable.
- [ ] No raw error strings; all errors map to UI copy.
- [ ] Keyboard navigation and focus rings work on all interactive atoms.
