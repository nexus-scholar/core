# Prioritized Implementation Plan

Last updated: 2026-05-20

This document turns the TODO map into execution plans for the next developer. It tracks the work by priority, repo ownership, dependencies, suggested sequence, test expectations, and definition of done.

Status terms:

- `done`: implemented and covered by tests.
- `ready`: scoped enough to start.
- `blocked`: needs a product, architecture, credential, or dependency decision.
- `defer`: intentionally later than the current milestone.

## Current Baseline

The original P0 stabilization slice is complete in `core`:

- Architecture leak guardrails are in place through ports and an architecture test.
- Empty PHP placeholder files have been removed and are now prevented by a regression test.
- Work persistence uses an internal UUID primary key with provider IDs stored in external ID rows.
- Work provider provenance round-trips through `work_providers`.
- Provider config supports enabled flags, API keys, rate limits, retries, timeouts, and immutable per-request selection.
- PDF fetch persistence and citation edge weights have SQL regression coverage.
- Legacy `docs/spec-*` files are marked as design artifacts when they are not current implementation inventories.

P1 search orchestration and Laravel integration are now implemented in `core`, with a compatibility bridge in `nexus-cli`.

P2 citation-network foundation is now partially implemented:

- `graph-core` and `graph-algorithms` are local path dependencies of `core`.
- `graph-algorithms` now includes real connected-components behavior.
- `graph-core` dev tooling was upgraded for the active PHP toolchain.
- `core` has citation graph builders for direct citation, co-citation, and bibliographic coupling graphs.
- `core` has graph-package adapters for PageRank, K-core, degree metrics, and shortest citation paths.
- Laravel binds the citation graph handlers and graph algorithm port.
- `core` has the first snowballing application slice: provider port, provider collection, command/result DTOs, handler, round/provider stats, dedup handoff, and fake-provider tests.
- `SemanticScholarAdapter`, `OpenAlexAdapter`, and `CrossrefAdapter` implement the snowballing provider port where their public APIs expose reliable traversal data.

P2 full-text retrieval is also implemented beyond the original PDF-only scope:

- The retrieval pipeline supports direct URLs, Unpaywall, PMC OAI XML, Europe PMC, arXiv, OpenAlex metadata PDF URLs, and Semantic Scholar metadata PDF URLs.
- XML full-text candidates are stored as deterministic `.xml` artifacts and get extracted `.txt` sidecars recorded in fetch metadata.
- PDF validation remains strict: non-PDF responses, oversized payloads, and mismatched content types are rejected before storage.
- Full-text source metadata, licenses, OA status, and source evidence are carried into audit metadata when available.

P3 release validation is implemented:

- Composer scripts exist for `test`, `test:unit`, `test:feature`, `analyse`, `format`, and `format:check`.
- PHPStan runs at an intentionally low starter level over `src`.
- Laravel Pint configuration exists, with changed-file checks in CI to avoid a noisy full-tree formatting rewrite.
- GitHub Actions validates Composer metadata, tests supported PHP/Laravel combinations, runs static analysis, and checks formatting.
- `.gitattributes` keeps local/dev artifacts out of package archives.

The next primary focus is no longer "start citation networks", "harden full-text retrieval", or "add release tooling". It is host-app integration and handoff readiness: CLI/HTTP surfaces for package use cases, lock-policy decisions, job progress read models, export list/download flows, and release packaging checks.

## P0: Stabilization Guardrails

Status: done

These items should stay closed. Future work should preserve the guardrails rather than reopen the same risks.

### P0.1 Architecture Leaks

Status: done

Objective:
- Keep domain and application code independent from Laravel, Eloquent, facades, queues, and storage.

Done criteria:
- Non-Laravel bounded contexts do not import `Illuminate\*`.
- Project lock checks go through `ProjectLockPort`.
- Transactions go through `TransactionPort`.
- New application handlers accept ports in constructors.

Regression coverage:
- `tests/Unit/Architecture/FrameworkBoundaryTest.php`
- Handler tests using fake ports.

Maintenance rule:
- Any new framework dependency outside `src/Laravel` must fail code review unless it is explicitly part of infrastructure.

### P0.2 Placeholder Surface

Status: done

Objective:
- Prevent empty class files from implying behavior that does not exist.

Done criteria:
- Empty strict-types-only PHP files are absent from `src` and `tests`.
- Planned behavior lives in docs or issues, not empty classes.

Regression coverage:
- `tests/Unit/Architecture/NoEmptyPhpFilesTest.php`

Maintenance rule:
- A new class must contain real behavior, a contract, or a failing/pending test that explains why it exists.

### P0.3 Persistence Identity

Status: done

Objective:
- Separate internal database identity from external scholarly/provider IDs.

Done criteria:
- `scholarly_works.id` stores an internal UUID.
- DOI, arXiv, OpenAlex, Semantic Scholar, PubMed, IEEE, and other external IDs live in `work_external_ids`.
- Foreign keys from query works, clusters, graphs, and PDF fetches point to internal UUIDs.

Regression coverage:
- `tests/Feature/Persistence/WorkRepositoryTest.php`
- `tests/Feature/Persistence/SearchQueryRepositoryTest.php`
- `tests/Feature/Persistence/CitationGraphRepositoryTest.php`
- `tests/Feature/Persistence/PdfFetchRepositoryTest.php`

Maintenance rule:
- Repository callers can pass domain `WorkId` values, but Eloquent repositories must resolve them before writing FK columns.

### P0.4 Provider Provenance

Status: done

Objective:
- Preserve provider context for scoring, deduplication, debugging, and audit output.

Done criteria:
- `sourceProvider` survives save/load.
- Provider sightings live in `work_providers`.
- Query-work links keep provider alias and provider work ID.

Regression coverage:
- `tests/Feature/Persistence/WorkRepositoryTest.php`
- `tests/Feature/Persistence/SearchQueryRepositoryTest.php`

Maintenance rule:
- Avoid synthetic provider names like `persisted` except as a last-resort fallback.

### P0.5 Documentation Truthfulness

Status: done

Objective:
- Make onboarding docs honest about what is shipped versus planned.

Done criteria:
- P0 tracker records no open P0 stabilization items.
- Legacy spec docs are labeled design artifacts when appropriate.
- UI docs do not claim unimplemented core services are live.

Maintenance rule:
- Any new roadmap document must say whether it is a current implementation inventory, an implementation plan, or a product proposal.

## P1: High-Value Reusable Search And Laravel Integration

Status: done

This milestone moved reusable search parsing, execution, provider selection, and persistence into `core`, while keeping app-specific run JSON and wiki/screening behavior in `nexus-cli`.

### P1.1 Register Only Real Laravel Commands

Status: done

Repo ownership:
- Primary: `core`
- Consumer validation: `nexus-cli`

Current state:
- `core` registers `nexus:search`.
- Empty command placeholders were removed.
- `nexus-cli` contains richer app-level commands around runs, wiki ingestion, screening, and PDFs.

Objective:
- Make the command surface explicit and honest.
- Avoid registering commands that are not implemented.
- Decide which commands belong in reusable `core` and which belong only in host applications like `nexus-cli`.

Implementation plan:
1. Inventory current command classes in `core` and `nexus-cli`.
2. Classify each command as:
   - reusable package command,
   - host-app workflow command,
   - planned command.
3. Keep `core` service provider registration limited to implemented reusable commands.
4. Add command discovery tests in `core`.
5. Add a small compatibility note for `nexus-cli` showing which workflows remain app-owned.

Recommended decisions:
- Keep `nexus:search` in `core` only if it delegates to reusable application services.
- Keep wiki, screening, run file layout, and thesis-specific commands in `nexus-cli`.
- Do not add `nexus:dedup`, `nexus:snowball`, `nexus:fetch-pdf`, or `nexus:export` until their application services and tests exist.

Tests:
- Feature test: `nexus:search` is registered.
- Feature test: removed/planned commands are not registered.
- `nexus-cli` smoke test: app commands still resolve after updating `core`.

Done criteria:
- Running `php artisan list nexus` in a consuming Laravel app shows only implemented commands.
- Command registration has feature coverage.
- Docs state where host-app commands live.

Completion notes:
- `core` registers only the implemented reusable `nexus:search` command.
- Command registration is covered by `tests/Feature/Laravel/CommandRegistrationTest.php`.
- Planned commands remain documentation/backlog items until their application services exist.

### P1.2 Extract `nexus:search` YAML Parsing Into A Reusable Service

Status: done

Repo ownership:
- Primary: `core`
- Consumer validation: `nexus-cli`

Current state:
- `core` has a Laravel command that parses inline/file inputs.
- `nexus-cli` has app-level search command logic for YAML-defined thesis queries and run JSON.
- Reusable parsing/orchestration should not be trapped in Artisan code.

Objective:
- Make search input parsing and batch execution reusable by CLI, jobs, and HTTP controllers.

Proposed target design:
- `SearchPlan`: immutable parsed representation of one or more search requests.
- `SearchPlanItem`: query text, project ID, year range, max results, provider aliases, metadata, priority, source query ID.
- `SearchPlanParserPort`: parses structured input into a `SearchPlan`.
- `YamlSearchPlanParser`: infrastructure parser using Symfony YAML.
- `SearchPlanRunner`: application service that executes a plan item-by-item through `SearchAcrossProvidersHandler`.
- `SearchPlanResult`: aggregate result with per-item result, failures, duration, and provider stats.

Implementation plan:
1. Characterize current YAML shape from `nexus-cli/resources/queries/thesis-queries.yml`.
2. Define package-neutral DTOs in `core` application layer.
3. Move only generic parsing rules to `core`.
4. Keep host-specific run JSON writing in `nexus-cli` until a persistence-backed run recorder exists.
5. Refactor `core` `NexusSearchCommand` to call the parser and runner.
6. Refactor `nexus-cli` command to reuse the parser or adapter if dependency boundaries permit.

Boundary rules:
- YAML parsing may live in infrastructure or Laravel integration, not domain.
- File paths, wiki output, and thesis-week behavior stay in `nexus-cli`.
- The application runner should not know about Artisan output.

Tests:
- Unit: valid YAML produces the expected `SearchPlan`.
- Unit: invalid YAML fails with useful messages.
- Unit: provider aliases and priority filters normalize correctly.
- Application: plan runner continues after one item fails when configured to continue.
- Feature: Artisan command delegates to parser/runner and renders summary.
- Regression: current `nexus-cli` thesis query file parses without behavior loss.

Done criteria:
- CLI, future jobs, and future HTTP can share the same parse/execute path.
- No YAML parsing remains in the command except file loading and error presentation.
- Existing command output remains stable enough for users.

Completion notes:
- `core` now provides `SearchPlan`, `SearchPlanItem`, `SearchPlanRunOptions`, `SearchPlanRunner`, `SearchPlanResult`, and `YamlSearchPlanParser`.
- The parser supports `searches` and legacy `queries`, `query` and legacy `text`, `limit` and legacy `max_results`, `year_from/year_to`, `year_min/year_max`, global and per-query providers, metadata priority, and screening hints.
- `NexusSearchCommand` now delegates parsing and batch execution to reusable services.
- `nexus-cli` keeps run JSON writing app-owned, but can delegate plan parsing to the core parser when the updated core package is installed.

### P1.3 Add Provider Selection To Application Flow Everywhere

Status:
- done

Objective:
- Ensure every search entrypoint can select providers without mutating a singleton registry.

Implementation plan:
1. Audit all constructors and commands that create `SearchAcrossProviders`.
2. Ensure provider aliases are accepted from:
   - inline CLI option,
   - YAML item,
   - YAML global default,
   - job payload,
   - future HTTP request DTO.
3. Persist selected aliases in `search_queries.provider_aliases`.
4. Render selected aliases in command summaries and future run summaries.
5. Add unknown-provider handling policy.

Decision needed:
- Unknown provider alias should probably fail validation before execution rather than silently skip.
- Disabled selected provider should report a clear skipped/disabled provider stat.

Tests:
- Unit: provider aliases normalize and deduplicate.
- Application: selected providers only execute selected adapters.
- Feature: persisted query reloads selected aliases.
- Feature: CLI `--providers=openalex,arxiv` executes only those providers.
- Regression: cache key changes when provider selection changes.

Done criteria:
- Provider selection behavior is consistent across CLI, jobs, and HTTP.
- Unknown/disabled provider handling is explicit and tested.

Completion notes:
- `SearchQuery` carries normalized provider aliases and includes them in cache identity.
- `AdapterCollection::matching()` validates selected aliases before provider execution.
- `nexus:search --providers=openalex,arxiv` flows through the same application path and persists selected aliases.
- Unknown provider aliases fail clearly before cache lookup or provider execution.

### P1.4 Wire Persistence Into Search Orchestration

Status: done

Repo ownership:
- Primary: `core`
- Consumer validation: `nexus-cli`

Current state:
- Repositories exist for search query, provider progress, work, and query-work provenance.
- Aggregator returns `AggregatedResult`.
- Search execution does not yet write a complete persistent run through application ports.

Objective:
- A search run should persist enough data for dashboards, PRISMA counts, dedup traces, resume/retry, and audit.

Proposed target design:
- `SearchRunRecorderPort`
  - `recordStarted(SearchQuery $query): void`
  - `recordProviderStat(string $queryId, ProviderStat $stat): void`
  - `recordWork(string $queryId, ScholarlyWork $work, string $providerAlias, string $providerWorkId, int $rank): void`
  - `recordCompleted(string $queryId, AggregatedResult $result): void`
  - `recordFailed(string $queryId, Throwable $error): void`
- Eloquent implementation coordinates existing repositories.
- Application handler writes through recorder, not direct Eloquent.

Implementation plan:
1. Characterize existing repository methods and schema.
2. Add a recorder port and in-memory fake for tests.
3. Update `SearchAcrossProvidersHandler` or introduce `PersistentSearchRunner`.
4. Persist query before provider execution.
5. Persist provider stats after aggregation.
6. Persist works and query-work rows with provider provenance and rank.
7. Decide whether dedup clusters should be persisted in this path or a separate dedup/lock workflow.
8. Add transaction boundaries where partial writes would be dangerous.

Important design choice:
- Keep search aggregation pure and side-effect-light. Prefer a runner/handler wrapper for persistence rather than burying database writes inside `SearchAggregator`.

Tests:
- Application: fake recorder receives started, provider stats, works, completed.
- Application: provider partial failure records failed provider stat but still completes if at least one provider succeeds.
- Feature: SQL rows are written for query, provider progress, works, external IDs, work providers, and query-work links.
- Feature: repeated run does not duplicate work/external IDs.
- Regression: provider source metadata survives persistence.

Done criteria:
- One search command creates a coherent persisted search trace.
- Run stats can be computed from SQL, not only transient command output.
- Future HTTP/job entrypoints can use the same runner.

Completion notes:
- `SearchExecutorPort` separates pure search handling from persistent execution.
- `PersistentSearchRunner` wraps `SearchAcrossProvidersHandler` and records started, provider stats, deduped works, query-work provenance, completion, and failure.
- `EloquentSearchRunRecorder` coordinates existing query and work repositories, persists provider progress, stores query-work links, and updates search query status/totals.
- Laravel binds `SearchExecutorPort` to the persistent runner, while direct `SearchAcrossProvidersHandler` usage remains available for pure execution.

### P1.5 Replace `nexus-cli` Workarounds With Core Services

Status: done

Repo ownership:
- Primary: `nexus-cli`
- Support: `core`

Current state:
- `nexus-cli` binds a NullSearchCache workaround.
- Search output and run JSON are app-specific.

Objective:
- Remove workarounds once `core` offers stable service contracts.

Implementation plan:
1. Re-run `nexus-cli` tests after each `core` P1 slice.
2. Remove NullSearchCache workaround if `LaravelSearchCache` is stable in a consuming app.
3. Update `nexus-cli` command constructors to consume `core` services.
4. Keep app-only output responsibilities in `nexus-cli`.
5. Add a small integration smoke test around the installed `core` package.

Tests:
- `composer test` in `nexus-cli`.
- Command smoke test for `nexus:search --id=<known query>`.
- Fixture-based run JSON assertion.

Done criteria:
- `nexus-cli` search command uses `core` parser/runner where appropriate.
- App-level code is thinner and easier to reason about.

Completion notes:
- The local `NullSearchCache` workaround was removed from `nexus-cli`.
- `nexus-cli` keeps app-owned responsibilities: run file layout, latest pointer, global JSON output, wiki/screening/PDF workflows.
- `nexus-cli` now uses the new core parser and persistent executor automatically when the updated core package is installed; it keeps a compatibility fallback for the currently locked older core package.

## P2: Feature Completion

Status: in progress

P2 should not be treated as one large "finish everything" PR. Each item below deserves its own milestone.

For citation-network graph package history and the current use of `mbsoft31/graph-core` and `mbsoft31/graph-algorithms`, see `docs/19-graph-packages-p2-assessment.md`.

### P2.1 Citation Network Implementation

Status: in progress

Objective:
- Implement graph building, snowballing, metrics, and scalable algorithms for real.

Implemented:
- `CitationGraph` prevents duplicate work identity collisions and missing in-graph citations.
- `CitationGraphBuilder` builds direct citation graphs from provider reference IDs.
- `CitationGraphBuilder` builds co-citation and bibliographic-coupling graphs with inverted indexes and weighted undirected edges.
- `MbsoftCitationGraphMapper` maps Nexus graphs into `Mbsoft\Graph\Domain\Graph` without exposing graph-package types from the domain model.
- `MbsoftNetworkMetricsCalculator` computes PageRank, degree metrics, K-core values, connected components, and shortest paths through the graph packages.
- `BuildCitationGraphHandler`, `AnalyzeNetworkHandler`, and `FindShortestCitationPathHandler` expose reusable application use cases.
- `EloquentCitationGraphRepository` persists graph metadata and weighted edges.
- Laravel binds `CitationGraphRepositoryPort`, `GraphAlgorithmPort`, and the citation graph handlers.
- `SnowballingProviderPort` and `SnowballingProviderCollection` define the provider traversal contract without leaking provider APIs into the domain.
- `SnowballCorpusHandler` runs forward/backward rounds through selected providers, deduplicates discovered works through `DeduplicationPort`, separates already-known from net-new works, records provider failures, and uses net-new works as the next-depth seeds.
- `SemanticScholarAdapter` resolves citing and referenced works for snowballing, reusing provider config, API key headers, timeout, rate limiting, retry behavior, and existing work normalization.
- `OpenAlexAdapter` resolves forward snowballing through OpenAlex `cites` filters and backward snowballing through `referenced_works`, including seed resolution by OpenAlex ID, DOI, PubMed ID, or arXiv ID.
- `CrossrefAdapter` resolves backward snowballing through deposited DOI reference metadata; forward citation traversal is intentionally not treated as public metadata because Crossref Cited-by is a separate participation flow.

Remaining sub-slices:
1. Provider citation traversal
   - `SnowballingProviderPort` for references and citations is implemented.
   - Semantic Scholar, OpenAlex, and Crossref provider traversal is implemented where their APIs support it.
   - Add more real provider adapters only where APIs expose reliable reference/citation data.
   - Provider failure stats are implemented in the application result; persistence/progress events remain open.
2. Snowballing
   - Forward and backward modes are implemented at the application layer.
   - Depth limits are implemented at the application layer.
   - New-vs-known work counts are implemented in `SnowballRoundResult`.
   - Dedup integration through `DeduplicationPort` is implemented.
   - Persisted round progress.
3. Persistence policy
   - Rebuild/recompute policy for graph snapshots and metrics.
   - Decide whether metrics stay in graph metadata or move to a dedicated metrics table when JSON becomes too large.
4. Performance guardrails
   - Add representative graph tests that protect the inverted-index builders from accidental all-work pairwise scans.

Recommended design:
- Keep graph algorithm implementations outside domain.
- Keep provider citation traversal behind a `SnowballingProviderPort`.
- Add algorithm ports only when the first algorithm is implemented.
- Prefer scalable index-based algorithms over nested O(n^2) loops for co-citation and bibliographic coupling.

Legacy source to inspect:
- `repos/nexus-php` snowball and citation graph services.

Tests:
- Unit: graph invariants and duplicate edges.
- Unit: weighted edge behavior.
- Unit: graph-package adapter mapping and shortest path behavior.
- Unit: PageRank, degree, K-core, and connected-components metrics.
- Application: graph builder and analysis handlers.
- Application: snowball round counts through fake providers.
- Application: provider failure does not discard other provider results.
- Feature: graph save/load with weighted edges.
- Integration: provider citation fixtures with VCR only.
- Performance guard: representative graph does not use O(n^2) implementation where avoidable.

Done criteria:
- Citation network UI can be un-gated after provider traversal, snowballing, and graph rebuild policy are implemented and covered.

### P2.2 PDF And Full-Text Flow

Status: partially done

Objective:
- Turn current PDF retrieval into a production-grade full-text pipeline.

Implemented:
- `RetrieveFullTextHandler` tries registered sources and records fetch attempts.
- `DirectPdfSource`, `UnpaywallPdfSource`, `PmcOaiFullTextSource`, `EuropePmcFullTextSource`, `ArXivPdfSource`, `OpenAlexPdfSource`, and `SemanticScholarPdfSource` resolve lawful open full-text artifacts from work metadata and OA APIs.
- `GuzzlePdfDownloader` applies an HTTP timeout and passes response content type to the application layer.
- `PdfFetchRepositoryPort` and the Eloquent implementation persist PDF fetch audit rows by internal work UUID.
- Laravel binds the source collection, downloader, storage, and repository ports.
- Existing successful fetches are reused when the persisted file path still exists.
- Downloaded content is rejected before storage when it lacks a `%PDF-` signature or reports a non-PDF content type.
- XML artifacts are validated, stored with `.xml` paths, and paired with extracted text sidecars recorded in metadata.
- Downloads retry transient failures before auditing a source failure.
- Downloaded PDFs are rejected before storage when they exceed the command size limit.
- Recent failed source URLs are skipped during the command cooldown window.
- Stored PDF paths are deterministic and sanitize work IDs, source aliases, and destination folders.
- OpenAlex paid content download is not used by default; only metadata-provided PDF URLs are considered.

Remaining sub-slices:
1. Source resolution
    - Direct URL source is implemented for explicit PDF URL metadata.
    - Unpaywall, PMC OAI, and Europe PMC source coverage is implemented for legal OA retrieval.
    - Additional legal sources can be added only through the same candidate/source port contract.
2. Download safety
    - Retry policy is implemented.
    - MIME validation is implemented for reported content types.
    - Size limit is implemented.
    - PDF signature sniffing is implemented before storage.
    - XML validation is implemented before storage.
3. Storage
    - Local/non-Laravel storage adapter if needed.
    - Deterministic path policy is implemented for generated PDF filenames.
    - Deterministic path policy is implemented for XML artifacts and extracted text sidecars.
4. Duplicate avoidance
    - Existing successful path lookup is implemented.
    - Re-fetch policy.
   - Failed attempt cooldown is implemented.
5. Audit
   - One row per attempted source.
   - HTTP status.
   - error message.
   - duration.

Tests:
- Unit: each source resolves correctly from work metadata.
- Application: MIME/signature validation rejects non-PDF content.
- Application: invalid XML is audited and retrieval continues to later sources.
- Application: XML artifacts are stored with extracted text sidecars.
- Application: retry exhaustion audits one source failure.
- Application: oversized PDFs are rejected before storage.
- Application: recent failed source URLs are skipped during cooldown.
- Application: successful first source stops later sources.
- Application: failed first source continues to next source.
- Feature: SQL audit rows are written for success and failure.
- Feature: repeated fetch uses cached successful path.
- Feature: failed source cooldown is backed by persisted PDF fetch audits.

Done criteria:
- Host apps can call full-text retrieval from CLI, job, or HTTP without duplicating source/download logic.

### P2.3 Laravel Jobs, Events, And Listeners

Status: partially done

Objective:
- Provide background-safe entrypoints for search, deduplication, full-text retrieval, and citation-network work.

Current state:
- Domain events exist for search, deduplication, and dissemination concepts.
- `SearchJob` exists and carries only `SearchPlan` plus optional `SearchPlanRunOptions`.
- `SearchJob` resolves `SearchPlanRunner` from the Laravel container when handling the queued payload.
- `DeduplicateCorpusJob` exists and carries only `CorpusSlice`, `projectId`, and selected policy aliases.
- `DeduplicateCorpusJob` resolves `DeduplicateCorpusHandler` from the Laravel container when handling the queued payload.
- `RetrieveFullTextJob` exists and carries only `ScholarlyWork` plus destination folder.
- `RetrieveFullTextJob` resolves `RetrieveFullTextHandler` from the Laravel container when handling the queued payload.
- `SnowballJob` exists and carries only a `SnowballCorpus` application DTO.
- `SnowballJob` resolves `SnowballCorpusHandler` from the Laravel container when handling the queued payload.
- `NexusJobStarted`, `NexusJobCompleted`, and `NexusJobFailed` define serializable lifecycle event payloads.
- `NexusJobProgressed` defines serializable progress payloads with stable per-run progress keys.
- The implemented jobs dispatch started/completed/failed lifecycle events around their application handler calls.
- `SnowballJob` emits progress records for each snowball round and provider stat from the completed handler result.
- `RecordNexusJobLifecycle` records lifecycle events through `JobLifecycleRecorderPort`.
- The default `JobLifecycleRecorderPort` binding is `EloquentJobLifecycleRecorder`, which writes `job_lifecycle_records` through package migrations.
- Search, PDF retrieval, deduplication, and citation graph use cases are container-resolvable enough to be wrapped by jobs.

Sub-slices:
1. Job payload design
   - Serializable IDs and DTOs only.
   - No closures or non-serializable service instances.
2. Jobs
   - `SearchJob` is implemented.
   - `DeduplicateCorpusJob` is implemented.
   - `RetrieveFullTextJob` is implemented.
   - `SnowballJob` is implemented.
3. Events
   - started, completed, failed are implemented for package jobs.
   - provider-level progress events are implemented for snowballing through `NexusJobProgressed`.
4. Listeners
   - lifecycle listener contract is implemented.
   - persistent listener storage upserts by `JobLifecycleRecord::$idempotencyKey`.
   - progress rows denormalize `project_id` and `work_id` from context for host-app status screens.
   - avoid hidden side effects that duplicate handler work.
5. Queue safety tests
   - serialize and unserialize job payloads.
   - execute job with fake ports.

Tests:
- Feature: jobs resolve application services from container.
- Feature: job serialization round-trip.
- Application: fake ports prove correct service calls.
- Feature: failed provider event records error without failing entire run.

Done criteria:
- Queue workers can process package jobs safely in a Laravel host app.

### P2.4 Corpus Lock Lifecycle

Status: foundation implemented; policy integration remains.

Objective:
- Make project/corpus locking a first-class lifecycle, not just a boolean guard.

Current state:
- `ProjectLockPort` is used by application handlers that mutate project search state.
- `ProjectLockLifecyclePort` provides lock, unlock, and status operations.
- `LockCorpusHandler` locks the project and existing dedup clusters in one transaction.
- `UnlockCorpusHandler` unlocks the project and existing dedup clusters in one transaction.
- `EloquentProjectLock` provides the Laravel-side lock check, lock/unlock lifecycle writes, and audit rows.
- Lock metadata includes actor id, reason, timestamps, and JSON metadata.

Remaining risk:
- Host applications still need an authorization policy for who can unlock.
- Screening, export, and future graph mutation paths need explicit lock-policy decisions.
- Lock eligibility warnings should account for recent provider failures before a user freezes a corpus.

Sub-slices:
1. Lock domain policy
   - Which mutations are blocked.
   - Who can unlock.
   - Whether screening changes are allowed after lock.
2. Persistence
   - lock timestamp is implemented.
   - locked by is implemented.
   - unlock reason is implemented.
   - audit trail is implemented.
3. Enforcement
   - search mutation blocked through `ProjectLockPort`.
   - dedup cluster mutation blocked through locked clusters.
   - screening changes policy.
   - export allowed.
4. UX/API support
   - lock eligibility.
   - warning when latest run had provider failures.

Tests:
- Unit: lock policy decisions.
- Unit: lock/unlock handlers update project lifecycle and cluster lock state.
- Application: handlers check `ProjectLockPort` or `ProjectLockLifecyclePort`.
- Feature: explicit lock/unlock writes project state and audit rows.
- Feature: locked project blocks future mutation paths as they are added.

Done criteria:
- Locked corpus is safe to use for reproducible export and screening.

### P2.5 Export History And Format Validation

Status: foundation implemented; rebuild policy remains.

Objective:
- Make exports auditable, repeatable, and format-safe.

Current state:
- Bibliography export use cases and serializers exist for BibTeX, RIS, CSV, JSON, and JSONL.
- Network serializers exist for GEXF, GraphML, and Cytoscape.
- Export handlers write files through `FileStoragePort`.
- `ExportHistoryPort` records successful exports to SQL through `export_histories`.
- Bibliography and network export handlers validate filename extensions against selected formats before writing.
- Citation graph exports use `mbsoft31/graph-core` exporters for Cytoscape JSON, GraphML, and GEXF so node attributes, edge weights, and directedness are preserved.
- Laravel binds bibliography serializers, legacy corpus network serializers, graph-core citation graph serializers, and export handlers.

Remaining risk:
- Re-download/rebuild policy is not implemented.
- Host apps still need user-facing endpoints/commands for listing previous exports and downloading stored paths.

Sub-slices:
1. Export request validation
   - supported format is enforced through serializer collections.
   - filename extension is validated.
   - source corpus/query/cluster.
   - empty corpus behavior.
2. Export history persistence
   - format is persisted.
   - file path is persisted.
   - requested by is persisted.
   - timestamps are persisted.
   - options/metadata are persisted.
3. Serializer coverage
   - BibTeX.
   - RIS.
   - CSV.
   - JSON.
   - JSONL.
   - GEXF.
   - GraphML.
   - Cytoscape.
4. Re-download/rebuild policy
   - cached file if present.
   - regenerate if source changed.

Tests:
- Unit: serializer snapshots.
- Unit: unsupported format rejected.
- Feature: export history row written.
- Feature: generated file exists on configured storage disk.
- Regression: graph-core-backed citation graph exports preserve node IDs and edge weights.

Done criteria:
- Host apps can show export history from persisted package data.

## P3: Release Readiness

Status: baseline release validation done; release packaging checks remain.

Current state:
- GitHub Actions runs on PHP `8.3` with Laravel `12.*`, plus PHP `8.4` with Laravel `12.*` and `13.*`.
- Composer scripts are present for `test`, `test:unit`, `test:feature`, `analyse`, `format`, and `format:check`.
- PHPStan configuration is present at level 1 over `src`.
- Laravel Pint configuration is present.
- `.gitattributes` is present with export-ignore rules for local/dev artifacts.
- `composer audit --format=plain` still reports existing Symfony advisories in transitive dependencies; audit enforcement should wait until those are upgraded or explicitly accepted.

### P3.1 Remove Local Artifacts

Objective:
- Keep the package clean for Packagist and external contributors.

Plan:
1. Audit tracked files for IDE config, logs, cache files, generated app artifacts, and agent-only drafts.
2. Move contributor-useful docs into `docs/`.
3. Remove or ignore local-only files.
4. Keep `.gitattributes` export-ignore rules current as release assets change.

Tests:
- `git status --ignored` inspection.
- Composer archive smoke check before tagging.

Done criteria:
- Source distribution contains package code, tests, docs, and config only.

### P3.2 Static Analysis

Objective:
- Add PHPStan or Psalm at a practical level and ratchet upward.

Recommendation:
- Start with PHPStan.
- Use a low baseline level first.
- Generate a baseline only if necessary, then reduce it over time.

Plan:
1. Keep `phpstan/phpstan` as a dev dependency.
2. Maintain `phpstan.neon.dist`.
3. Analyze `src` first.
4. Add tests only after source passes or with a separate config.
5. Ratchet the level upward as dynamic Laravel repository noise is reduced.

Done criteria:
- `composer analyse` runs locally and in CI.
- Baseline is documented if present.

### P3.3 Formatting And Lint Scripts

Objective:
- Make style checks boring and repeatable.

Plan:
1. Keep Laravel Pint as the formatter.
2. Keep config matching project style.
3. Keep composer scripts:
   - `test`
   - `test:unit`
   - `test:feature`
   - `analyse`
   - `format`
   - `format:check`
4. Move from changed-file formatting checks to full-tree checks only after a deliberate formatting PR.

Done criteria:
- New dev can run one documented command for tests and one for formatting.

### P3.4 CI Matrix

Objective:
- Match declared support and catch package integration drift.

Current state:
- GitHub Actions runs on PHP `8.3` with Laravel `12.*`, plus PHP `8.4` with Laravel `12.*` and `13.*`.
- The matrix matches current declared PHP support while avoiding unsupported Laravel 13 on PHP 8.3.

Plan:
1. Keep PHP `8.3` coverage while package support remains `^8.3`.
2. Test Laravel/Testbench compatibility versions declared by composer constraints.
3. Run:
   - Composer validate.
   - Unit tests.
   - Feature tests.
   - Integration provider tests with VCR only.
   - Static analysis.
   - Format check.
4. Add a separate `nexus-cli` consumer smoke workflow later if cross-repo automation is available.

Done criteria:
- CI is green on clean checkout without live provider network.

## Recommended New Developer Onboarding Path

Give the new developer a contained integration slice, not a sweeping feature. P0, P1, and baseline P3 validation are now closed.

Best first assignment:
- Lock-policy integration for screening, graph mutation, and export workflows.

Why:
- The search, deduplication, full-text retrieval, citation graph, snowballing, job lifecycle, snowball progress, export history, and corpus lock lifecycle foundations are now in place.
- The next risk is inconsistent host-app behavior when users mutate, screen, export, or rebuild after locking a corpus.

Second assignment:
- Build package read models and CLI/HTTP surfaces for job progress, export history, and stored full-text artifacts.

Third assignment:
- Release packaging: archive smoke test, advisory review, versioning/tagging policy, and Packagist readiness.

## Cross-Repo Working Rules

- Use `nexus-php` as behavior evidence, not a structure template.
- Implement reusable package behavior in `core`.
- Keep host-specific workflows in `nexus-cli`.
- Keep one branch and one PR per child repository.
- Validate from the repo that changed.
- When a change spans `core` and `nexus-cli`, merge or test `core` first, then update the consuming app.
