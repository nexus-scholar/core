---
name: implement-vertical-slice
description: Implement an end-to-end Nexus workflow slice across domain, application, persistence boundary, CLI, audit, and tests without broad scaffolding.
---

Define one researcher-visible outcome and its exact lifecycle. Trace inputs through decisions, state transitions, artifacts, provenance, output, and verification.

Implement the domain first, then application coordination, then the smallest adapter and CLI surface. Preserve human approval boundaries and append-only history.

Add a happy path, invalid transitions, deterministic replay, audit reconstruction, and round-trip test when applicable.

Do not create unrelated projects, generic repositories, or extension points without an immediate use in the slice.
