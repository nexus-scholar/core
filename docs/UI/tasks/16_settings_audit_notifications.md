# Task 16: Settings, Audit Log, & Notifications

## Objective
Build the project administration surfaces, the role-gated audit log, and the notification center.

## Context Constraints (For the AI Coding Assistant)
- **Focus ONLY on this task.** Use API role payloads to determine UI visibility.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Review the Settings, Audit, and Notifications specs in the PRD.
  2. **Implement:** Build the API Key manager, Team list, Notification dropdown, and Audit Log table.
  3. **Test:** Verify that "Observer" roles cannot see the Audit Log or Team management actions.
  4. **Commit & Push:** Create a semantic commit and push.

## Specifications
1. **ProviderAPIKeyManager:** Set/rotate/revoke. Owner can toggle `required` flag for hard-failure gating.
2. **TeamMemberList:** View members, roles. Owner can invite/remove.
3. **Audit Log:** 
   - Table of typed events (Corpus locked, override, conflict resolved, etc.).
   - Visibility: Owner sees all; Reviewer sees own; Observer sees none.
4. **Notifications:** Dropdown or list showing run completions, failures, and conflict alerts with routing links.

## Acceptance Criteria
- [ ] Management actions (keys, team) are strictly hidden/disabled for non-Owners.
- [ ] Audit log enforces the 3-tier visibility rules.
- [ ] Required-provider toggle is present and functional in the API Key manager.
