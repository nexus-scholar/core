---
name: implement-nexus-gate
description: Implement one accepted Nexus gate with constrained scope, tests, documentation, and a verifiable completion report.
---

Before editing, read the gate plan, `AGENTS.md`, relevant nested instructions, ADRs, specifications, and fixtures.

Work in dependency order. Keep changes inside the allowed paths. Add domain tests before adapters. Add negative-transition, determinism, architecture, and conformance coverage where applicable.

Run the gate's verification commands and `scripts/verify` when available.

Stop rather than inventing behavior when specifications, fixtures, and the PHP reference disagree.

Finish with behavior implemented, files changed, invariants enforced, tests added, commands run, unresolved risks, ADR impact, and PHP compatibility impact.
