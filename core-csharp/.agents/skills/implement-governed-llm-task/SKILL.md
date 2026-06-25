---
name: implement-governed-llm-task
description: Implement a model-assisted Nexus task with explicit authority, context provenance, evidence, schema validation, privacy, and human action.
---

Before provider code, define:

- task purpose and authority level;
- authoritative context sources and digests;
- output schema and validation failures;
- evidence-reference requirements;
- model and parameter metadata;
- external data-transfer and retention policy;
- allowed tools;
- fallback behavior;
- required human action before canonical mutation.

Use `llm_governance_reviewer`. Store raw and parsed output separately. Preserve the original proposal when a researcher edits or rejects it. Unit tests use fixture-backed fake clients only.
