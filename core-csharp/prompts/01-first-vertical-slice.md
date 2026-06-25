# Codex Task: First Auditable Vertical Slice

Implement one end-to-end local workflow using the existing starter modules.

Outcome:

1. Create a protocol draft with required clarification dimensions.
2. Record researcher decisions without overwriting prior records.
3. Approve an immutable protocol version with a deterministic digest.
4. Compile a deterministic workflow.
5. Append a provenance event.
6. Produce and verify a review-bundle manifest.
7. Demonstrate the flow through the CLI.

Constraints:

- No database, web API, UI, provider, or model adapter.
- No new production dependency.
- No silent default for unresolved research scope.
- Keep approved records immutable.
- Add success, invalid-transition, determinism, architecture, and bundle-verification tests.

Run `scripts/verify` and report behavior, files, invariants, tests, commands, risks, and ADR impact.
