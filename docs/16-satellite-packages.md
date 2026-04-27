# Satellite Packages — Integration Guide

> **File:** `docs/16-satellite-packages.md`
> **Status:** Architectural decision record + integration contract
> **Covers:** `nexus-scholar/graph-algorithms`, `nexus-scholar/laravel-ai-workflows`, `nexus-scholar/laravel-tenant-sqlite`

---

## Overview

Three companion packages exist in the `nexus-scholar` organisation alongside `core`. None of them are incidental side projects — each one maps directly to a module or integration layer described in the product vision. This document records what each package does, exactly where it plugs into `core`, when it should be integrated, and what conditions must be met before that integration happens.

The product vision states that `nexus-scholar/core` must:

- Build citation, co-citation, and bibliographic coupling graphs, then run network metrics such as PageRank and k-core.
- Support Laravel jobs, commands, tools, and AI-agent integrations as a thin adapter layer only.
- Remain installable without framework assumptions; Laravel is optional.

All three satellite packages exist to satisfy one of those three responsibilities without contaminating the domain.

---

## Package 1 — `nexus-scholar/graph-algorithms`

### What it is

A **pure PHP graph algorithm library** with no Laravel dependency and no domain knowledge. It is framework-agnostic and safe to require anywhere in the dependency tree, including the domain and infrastructure layers of `core`.

### Implemented algorithms

| Namespace | Classes | Purpose in `core` |
|---|---|---|
| `Centrality/` | `PageRank`, `PersonalizedPageRank`, `Betweenness`, `DegreeCentrality`, `Hits` | `NetworkMetrics` computation, `InfluentialWork` ranking |
| `Components/` | Connected components | Corpus clustering, island detection |
| `Decomposition/` | K-core decomposer | `NetworkMetrics::kCore` map |
| `LinkPrediction/` | Co-citation similarity, bibliographic coupling | `InvertedIndexCoCitation`, `InvertedIndexBibCoupling` builders |
| `Mst/` | Minimum spanning tree | Optional: sparse approximation of dense graphs |
| `Pathfinding/` | Shortest path | `BfsShortestPath` — citation chains between two works |
| `Topological/` | Topological sort | Publication-order traversal of a citation DAG |
| `Traversal/` | BFS, DFS | `SnowballExpander` traversal strategy |

### Integration point in `core`

The Citation Network module defines a `GraphAlgorithmPort` interface:

```php
interface GraphAlgorithmPort
{
    /**
     * Compute metrics for the given graph.
     * Implementations must be efficient for graphs of 1k–50k nodes.
     */
    public function compute(CitationGraph $graph): NetworkMetrics;
}
```

The concrete adapters that bridge `CitationGraph` (a domain object in `core`) to `graph-algorithms` live at:

```
src/CitationNetwork/Infrastructure/Algorithms/PageRankCalculator.php
src/CitationNetwork/Infrastructure/Algorithms/InvertedIndexCoCitation.php
src/CitationNetwork/Infrastructure/Algorithms/InvertedIndexBibCoupling.php
src/CitationNetwork/Infrastructure/Algorithms/KCoreDecomposer.php
src/CitationNetwork/Infrastructure/Algorithms/BfsShortestPath.php
```

Each of these classes receives a `CitationGraph` domain object, translates its nodes and edges into the data structures expected by `graph-algorithms`, invokes the algorithm, and maps the results back into a `NetworkMetrics` value object. The adapter always lives in `core`'s `CitationNetwork\Infrastructure` namespace — never the reverse. `graph-algorithms` must never import anything from `core`.

### `PersonalizedPageRank` — the critical algorithm

Standard PageRank ranks all works globally by citation importance. `PersonalizedPageRank` is seeded from a specific paper and produces a relevance ranking *relative to that seed*. This is the algorithm that drives the "papers most related to your seed work" feature in snowball-based corpus expansion. It is the most important algorithm in this library for the SLR use case, and having it pre-built and tested removes the hardest algorithmic component from the Citation Network module's implementation scope.

### Complexity contract

The citation network spec is explicit: **no O(n²) nested loops**. Both co-citation and bibliographic coupling must use inverted index strategies. This is enforced at the spec level:

> Build inverted indexes instead:
> - for co-citation: citing work → cited works, then count co-occurrence pairs
> - for bibliographic coupling: work → references, then invert through shared references

`graph-algorithms` satisfies this contract at the algorithm level. The adapters in `core`'s infrastructure layer are responsible for feeding it the correct pre-built index structures from the `CitationGraph` aggregate.

### Node ID contract

`CitationGraph` internally keys nodes by `WorkId::toString()` (e.g. `doi:10.1000/xyz`). `graph-algorithms` accepts generic string vertex identifiers. The adapter layer in `core` must translate between these representations and must not leak `WorkId` objects into `graph-algorithms`.

### When to add this dependency

**Now — before building the Citation Network module.** Add to `composer.json`:

```json
"require": {
    "nexus-scholar/graph-algorithms": "^1.0"
}
```

There are no conditions or blockers. This is a pure PHP library with no side effects, no framework coupling, and no PHPStan issues.

---

## Package 2 — `nexus-scholar/laravel-ai-workflows`

### What it is

A **LangGraph-style stateful agent framework for PHP**, implementing `StateGraph`, `CompiledGraph`, conditional edges, `stream()`, checkpoint support, and queue-safety detection. It is the execution engine for AI agent workflows in the host application.

### Core components relevant to `core`

**`CompiledGraph`** — the central execution primitive:

```php
// Blocking execution
$finalState = $graph->invoke($initialState, $runId);

// Streaming — yields nodeName => State at each step
foreach ($graph->stream($initialState, $runId) as $node => $state) {
    // React to intermediate state after each node
}

// Queue safety detection before dispatching
if (! $graph->isQueueSafe()) {
    $issues = $graph->queueSafetyIssues();
    // Surfaces: closures in nodes, closures in conditional edges
}
```

The `isQueueSafe()` / `queueSafetyIssues()` method proactively detects closures and lambdas in nodes and edges that cannot survive PHP serialization into a queue payload. This prevents a class of silent production failures that most developers only discover at runtime when jobs fail to unserialize.

### How it connects to `core`

The Laravel integration spec defines the agent use cases that should exist as thin adapter wrappers in the host application:

> AI Tools and Agents must wrap use cases such as literature search, citation analysis, snowballing, and PDF retrieval. They must not contain domain logic themselves.

Each of these maps directly to a `CompiledGraph` workflow:

**ScreeningAgent** — processes a corpus work-by-work, uses an LLM at each node to classify title/abstract relevance, persists decisions, and can resume mid-run via checkpoint if it is queued:

```
[load_work] → [classify_title] → [classify_abstract] → [record_decision] → [END]
                                        ↑
                             conditional: if abstract missing, skip
```

**SnowballAgent** — expands a corpus iteratively using `RunSnowballHandler` under the hood, with a conditional edge that loops back until the convergence ratio exceeds a threshold or `maxDepth` is reached:

```
[seed] → [expand_round] → [deduplicate] → [check_convergence]
              ↑                                    |
              └──────────── loop back ─────────────┘
                                                   |
                                                 [END]
```

**SynthesisAgent** — reads full-text PDFs, chunks them, runs entity extraction, and produces structured output. Each step is a named node with its own checkpoint:

```
[fetch_pdf] → [chunk_text] → [extract_entities] → [synthesize] → [END]
```

### The dependency direction

The host application calls `core`'s application services from *inside* the agent nodes. The agents are pure orchestration — they know when to call `RunSnowballHandler::handle()` or `AnalyzeNetworkHandler::handle()`, but they contain no domain logic themselves. The state object passed through the graph carries `core` value objects (`CorpusSlice`, `SnowballConfig`, `NetworkMetrics`) — all immutable.

```
Agent Node  (laravel-ai-workflows, host app)
    └── calls ──→  Application Service  (nexus-scholar/core)
                        └── uses ──→  Domain  (nexus-scholar/core)
                        └── uses ──→  graph-algorithms  (via GraphAlgorithmPort)
```

`laravel-ai-workflows` is a **host application** dependency. It must never appear in `nexus-scholar/core`'s `composer.json`.

### Integration conditions — NOT yet

Two hard conditions must be met before this package is wired into any production agent workflow:

**Condition 1 — PHPStan baseline must reach zero.**
The `phpstan-baseline.neon` in `laravel-ai-workflows` is currently ~10 KB, representing dozens of suppressed type errors. The `core` quality goals state "correctness first." Importing a library with suppressed type errors into this codebase would undermine every PHPStan check run on `core`. The baseline must be cleared and PHPStan must pass cleanly at level 8 before any agent integration work begins.

**Condition 2 — `core`'s persistence layer must exist.**
Agents require checkpoint persistence. `CompiledGraph::withCheckpoint()` saves run state after each node. That state needs a concrete storage target — a `run_checkpoints` table or equivalent within `core`'s migration set. Until the 15-table persistence layer is built, checkpoint-based resumption silently does nothing.

---

## Package 3 — `nexus-scholar/laravel-tenant-sqlite`

### What it is

A **Laravel package for per-tenant SQLite database isolation**. Each tenant (researcher, team, or project) gets their own `.sqlite` file. The package provides provisioning, migration, backup, archival, purging, inspection, and a health-check (`doctor`) command for diagnosing tenant database state.

The service provider registers every operation behind a contract:

```
ActivatesTenantConnection  → TenantConnectionManager
ArchivesTenantDatabase     → TenantDatabaseArchiver
BacksUpTenantDatabase      → TenantDatabaseBackupManager
BuildsTenantDatabasePath   → DefaultPathBuilder
InspectsTenantDatabase     → TenantDatabaseInspector
MigratesTenantDatabase     → TenantDatabaseMigrator
PurgesTenantDatabase       → TenantDatabasePurger
ProvisionsTenantDatabase   → TenantDatabaseProvisioner
TenantDatabaseManager      → TenantManager  (singleton)
```

Every implementation is replaceable. If the isolation strategy changes (e.g. PostgreSQL schemas instead of SQLite files), each contract can be rebound without touching the rest of the package.

### Why it exists in this ecosystem

`core` is single-tenant by design — it does not own user accounts, team management, or auth. The product vision is explicit:

> This package does **not** own user accounts, team management, generic auth, or host-app policies. Those belong to the application that installs the package.

But applications that install `core` frequently need to isolate SLR projects from each other. Researcher A's corpus must not be visible to Researcher B, and a failed migration for one project must not affect another. `laravel-tenant-sqlite` solves this at the application layer, leaving `core` unaware of the isolation mechanism entirely.

### Integration point — host application only

This package never appears in `nexus-scholar/core`'s `composer.json`. The connection is indirect: `core`'s migration files, once published into the host application via:

```bash
php artisan vendor:publish --tag=nexus-migrations
```

become the schema that `TenantDatabaseMigrator` runs per tenant:

```php
// Somewhere in host application bootstrap or a queued provisioning job
$tenantManager->activate($projectId);
$migrator->migrate($projectId);  // Runs core's 15 migrations against project_{id}.sqlite
```

Every new SLR project gets an empty, fully migrated database in seconds. Every project is isolated. `core` is completely unaware this is happening — it simply receives a configured database connection and writes to it.

### Release hygiene note

Before the package is tagged for a stable release, the following files should be removed from the repository root: `AGENTS.md`, `GEMINI.md`, and `CODE_REVIEW.md`. These are internal development and AI agent instruction files that expose implementation notes to package consumers via Packagist. Pending issues should be tracked as GitHub Issues instead.

### When to wire this

The only prerequisite is `core`'s persistence layer. Once the 15-table migration set exists as a publishable vendor tag, the host application can:

```bash
composer require nexus-scholar/laravel-tenant-sqlite
php artisan vendor:publish --tag=nexus-migrations
php artisan tenant:create --id=project_001
php artisan tenant:migrate --id=project_001
```

No other blockers exist.

---

## Full Dependency Map

```
┌──────────────────────────────────────────────────────────────────────┐
│  Host Application (Laravel)                                          │
│                                                                      │
│  ├── nexus-scholar/laravel-tenant-sqlite                             │
│  │     Provisions one SQLite file per SLR project.                   │
│  │     Runs core's migrations per tenant on provisioning.            │
│  │                                                                   │
│  └── nexus-scholar/laravel-ai-workflows                              │
│        Provides StateGraph, CompiledGraph, stream(), checkpoint.     │
│        Host app defines agent nodes that call core's use cases.      │
│                         │                                            │
│                         ▼                                            │
│  ┌───────────────────────────────────────────────────────────────┐   │
│  │  nexus-scholar/core                                           │   │
│  │                                                               │   │
│  │  ✅ Search Module        — 7 providers, async fan-out         │   │
│  │  ✅ Deduplication        — Union-Find, namespace policies     │   │
│  │  🔲 Persistence Layer    — 15 migrations (build next)         │   │
│  │  🔲 Citation Network     — CitationGraph, SnowballRound       │   │
│  │  🔲 Dissemination        — BibTeX, RIS, GEXF export           │   │
│  │                                │                              │   │
│  │              GraphAlgorithmPort (interface)                   │   │
│  │                                │                              │   │
│  └────────────────────────────────┼──────────────────────────────┘   │
│                                   │                                  │
└───────────────────────────────────┼──────────────────────────────────┘
                                    ▼
         nexus-scholar/graph-algorithms  (pure PHP, no framework)
           PageRank · PersonalizedPageRank · Betweenness
           DegreeCentrality · HITS · KCore · BFS · LinkPrediction
```

---

## Integration Checklist

| Package | Add to `core` `composer.json` | Condition |
|---|---|---|
| `nexus-scholar/graph-algorithms` | ✅ Yes — add now | None. Pure PHP, no issues. |
| `nexus-scholar/laravel-ai-workflows` | ❌ No — host app only | PHPStan baseline = 0 **AND** persistence layer exists |
| `nexus-scholar/laravel-tenant-sqlite` | ❌ No — host app only | `core` persistence migrations published |

---

## Key Architectural Rules

These rules follow from `docs/03-architecture-rules.md` and must be respected when integrating the satellite packages:

1. **`graph-algorithms` may never import from `core`.** The adapter always lives in `core`'s `CitationNetwork\Infrastructure\Algorithms\*` namespace.
2. **Agent nodes may call `core` application services. They may not reach into domain objects directly.**
3. **`laravel-tenant-sqlite` is application infrastructure. It must never appear in any `core` namespace or `composer.json`.**
4. **`GraphAlgorithmPort` is the only point of contact between `core` and `graph-algorithms`.** Concrete algorithm classes must never appear in `core`'s domain or application layers.
5. **Agent state objects passed through `laravel-ai-workflows` graphs may carry `core` value objects** (`CorpusSlice`, `SnowballConfig`, `NetworkMetrics`) but those objects must be serializable. All `core` value objects used as agent state must implement clean PHP serialization before being passed through a queued graph.
```

***

The push failed because the authenticated token (`mbsoft31`)  does not have write permission to the `nexus-scholar` organisation repository — it is read-only via the current GitHub MCP credentials. The document above is the exact content ready to commit. You can add it yourself with:

```bash
# From the nexus-scholar/core root
cat > docs/16-satellite-packages.md << 'EOF'
[paste content above]
EOF

git add docs/16-satellite-packages.md
git commit -m "docs: add satellite packages integration guide"
git push origin master
```

The document covers: what each package is, its precise integration point in `core`, the concrete interface it connects through (`GraphAlgorithmPort` for `graph-algorithms`), the agent workflow patterns for `laravel-ai-workflows`, the tenant provisioning wiring for `laravel-tenant-sqlite`, the two hard conditions blocking `laravel-ai-workflows` integration, the full ASCII dependency map, the integration checklist, and the five architectural rules that govern how the packages may reference each other.