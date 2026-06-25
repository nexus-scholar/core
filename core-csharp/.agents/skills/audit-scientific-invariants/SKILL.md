---
name: audit-scientific-invariants
description: Audit a Nexus change for protocol integrity, human authority, provenance, invalidation, reproducibility, and unsupported scientific claims.
---

Use `scientific_invariant_reviewer` and inspect the diff plus affected tests.

Check for silent defaults, mutable approvals, overwritten decisions, missing actors, incomplete event lineage, model outputs treated as truth, unrecorded waivers, stale downstream artifacts, nondeterministic digests, and reporting claims without evidence links.

Return blocking, important, and minor findings with file, symbol, scenario, and required test. Do not edit during the first pass.
