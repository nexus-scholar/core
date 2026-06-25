# Nexus Scholar Core for .NET

This is the ready-to-start C# workspace for Nexus Scholar 2.

The PHP package remains the behavioral reference for proven scholarly workflows. The .NET implementation starts with protocol governance, workflow compilation, artifacts, provenance, plugins, and governed AI. Port observable behavior rather than translating PHP classes.

## Start

```powershell
pwsh ./scripts/bootstrap.ps1
```

```bash
./scripts/bootstrap.sh
```

Then open `NexusScholar.Core.slnx`, read `AGENTS.md`, and use the first prompt in `prompts/00-gate-zero-discovery.md`.

## Verify

```bash
dotnet restore NexusScholar.Core.slnx
dotnet build NexusScholar.Core.slnx -c Release --no-restore
dotnet test NexusScholar.Core.slnx -c Release --no-build
dotnet format NexusScholar.Core.slnx --verify-no-changes --no-restore
```

## Initial modules

- `Kernel`: deterministic primitives and contracts.
- `Protocol`: drafts, decisions, approvals, and amendments.
- `Workflow`: graph compilation, gates, and invalidation.
- `Artifacts`: immutable content identity.
- `Provenance`: append-only activity records.
- `Bundles`: portable review package contracts.
- `Extensibility`: plugin manifests and capability grants.
- `AI`: governed AI task and proposal contracts.
- `Cli`: local developer and researcher entry point.

Search, corpus, screening, extraction, persistence, API, desktop, and web modules are added only when their implementation gates begin.

## Source-of-truth order

1. Approved specifications.
2. Accepted architecture decisions.
3. Golden fixtures.
4. Observable behavior of the pinned PHP reference.
5. Current C# behavior.
6. Informal notes.

Resolve conflicts through an architecture decision and a conformance fixture. Never guess silently.
