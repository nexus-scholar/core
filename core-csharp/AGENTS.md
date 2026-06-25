# Nexus Scholar Core - Codex Working Agreement

## Mission

Build an audit-grade, local-first C# research workflow kernel. Research methods, human decisions, automation, evidence, amendments, deviations, and outputs must be reconstructable.

## Source of truth

1. Approved files in `specs/`.
2. Accepted ADRs in `docs/adr/`.
3. Golden fixtures in `fixtures/`.
4. Observable behavior of the pinned PHP reference.
5. Current C# implementation.
6. Informal notes and comments.

When sources conflict, do not guess. Record the conflict and stop the affected work until an ADR resolves it.

## Product laws

- Suggestion is not a decision.
- Draft is not an approved protocol.
- Reporting guidance is not a conduct method.
- Automation is not scientific authority.
- Current state is not historical record.
- Plugin isolation is not a security sandbox.
- Vector retrieval is not an evidence source.
- A wiki is not canonical scientific state.
- Cloud storage does not transfer data ownership.

## Domain rules

- Approved protocol versions are immutable.
- Amendments create new versions and preserve supersession links.
- Scientific mutations identify an actor and append provenance.
- Corpus and release snapshots are immutable.
- LLM outputs remain proposals until an authorized human action accepts them.
- Plugins receive scoped capabilities, never database credentials.
- File paths are references, not scientific identities.
- Scientific identity uses stable identifiers and content digests.
- Domain projects must not reference EF Core, ASP.NET Core, UI frameworks, provider SDKs, storage SDKs, or concrete model clients.
- Infrastructure depends inward; domain code never depends outward.

## Porting policy

- Port observable behavior, not PHP syntax or Laravel structure.
- Every compatibility claim needs a golden fixture or focused test.
- Never edit a golden output merely to make a C# test pass.
- Fixture changes require a dedicated generation task and source metadata.
- Preserve the pinned PHP commit in `specs/SOURCE.lock.json`.

## Change policy

- One task implements one coherent behavior.
- Do not modify unrelated modules.
- Do not change specifications unless the task explicitly authorizes it.
- Do not add a production dependency without an ADR or written rationale.
- Do not publish packages, push branches, or modify remote systems without explicit approval.

## Verification

For normal changes run:

1. `dotnet build NexusScholar.Core.slnx -c Release`
2. `dotnet test NexusScholar.Core.slnx -c Release --no-build`
3. `dotnet format NexusScholar.Core.slnx --verify-no-changes --no-restore`

Also run affected architecture and conformance tests. CI must not call live scholarly providers or live LLMs.

## Completion report

Report behavior implemented, files changed, invariants enforced, tests added, commands run, unresolved risks, ADR impact, and PHP compatibility impact.
