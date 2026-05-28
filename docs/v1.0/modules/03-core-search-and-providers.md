# Core Search And Providers

## Purpose

The Search context turns scholarly search requests into normalized corpus works. It owns query identity, provider selection, provider execution, provider normalization, cache identity, search-plan parsing, and persisted search-run provenance.

Search is the first mutation point for a project corpus. It can create or extend project membership when the project is mutable, but it must refuse mutation when the project corpus is locked.

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- domain query objects and validation,
- provider adapters for the supported academic sources,
- sequential and bounded concurrent provider execution,
- normalized provider results,
- deduplication after provider collection,
- cache keys that include provider selection,
- YAML search-plan parsing,
- persisted query/provider/work provenance,
- package command support for one query or a YAML plan.

## What Shipped

### Query Model

`SearchQuery` is the immutable request model for provider search. It carries:

- `SearchTerm`,
- `projectId`,
- optional `YearRange`,
- optional `LanguageCode`,
- `maxResults`,
- `offset`,
- `includeRawData`,
- selected provider aliases,
- generated query id.

`SearchTerm` rejects empty and one-character terms after trimming. `YearRange` validates realistic year bounds, rejects inverted ranges, and exposes helpers for bounded or unbounded ranges.

Provider aliases are normalized and sorted for cache identity. This means equivalent provider selections produce the same cache key even if caller order differs.

### Provider Set

The built-in provider registry covers:

| Alias | Source | Default v1 behavior |
| --- | --- | --- |
| `openalex` | OpenAlex | Enabled by default, supports multiple identifiers, supports forward and backward snowballing. |
| `crossref` | Crossref | Enabled by default, supports DOI and PubMed identifiers, supports backward reference traversal. |
| `semantic_scholar` | Semantic Scholar | Enabled by default, supports DOI, Semantic Scholar, arXiv, and PubMed identifiers, supports forward and backward snowballing. |
| `arxiv` | arXiv | Enabled by default, supports arXiv identifiers. |
| `pubmed` | PubMed | Enabled by default, supports PubMed identifiers and DOI-based search behavior. |
| `ieee` | IEEE | Disabled unless an API key enables it. |
| `doaj` | DOAJ | Enabled by default, supports DOI and DOAJ identifiers. |

Each provider is configured through `ProviderConfig`. Config validation rejects empty aliases, empty base URLs, non-positive rate limits, non-positive timeouts, and invalid retry counts.

### Provider Execution

`SearchAggregator` chooses enabled adapters, applies provider filtering from the query, builds a provider-aware cache key, executes providers, deduplicates the raw result corpus, and returns an `AggregatedResult`.

Provider execution supports two modes:

- `SequentialProviderSearchExecutor` calls providers one at a time.
- `ConcurrentProviderSearchExecutor` can run bounded concurrent calls for providers and HTTP clients that support async execution, with synchronous fallback.

Provider failures are captured as failed provider results and do not automatically fail the whole search. Successful providers still contribute results and progress metadata.

### HTTP, Rate Limits, And Retries

Provider adapters share a base adapter that applies token-bucket rate limiting, HTTP timeouts, retry limits, backoff, and error classification. The adapter retries transient provider and server failures, but does not retry terminal authorization and not-found responses.

The async path is optional. Providers can use it when the configured HTTP client implements the async port; otherwise execution remains synchronous.

### Search Plans

`YamlSearchPlanParser` supports YAML search plans with:

- root project id,
- root providers,
- root `include_raw_data`,
- `searches` or `queries`,
- per-search id,
- query text,
- metadata,
- priority,
- project override,
- provider override,
- limit or max results,
- year bounds,
- include and exclude text filters.

`SearchPlanRunner` runs parsed plans through the search executor and returns a structured plan result.

### Persistent Search Runs

`PersistentSearchRunner` wraps the search handler with persistence concerns. It records:

- search start,
- provider statistics,
- resulting works,
- completed run state,
- failed run state.

It also checks project lock state when a lock policy is available.

## Public API / Commands

The main application entry points are:

- `SearchAcrossProviders`
- `SearchAcrossProvidersHandler`
- `SearchExecutorPort`
- `SearchAggregatorPort`
- `SearchPlanParserPort`
- `SearchPlanRunner`
- `PersistentSearchRunner`

The main provider and persistence ports are:

- `AcademicProviderPort`
- `ProviderSearchExecutorPort`
- `ConcurrentSearchProviderPort`
- `HttpClientPort`
- `AsyncHttpClientPort`
- `SearchCachePort`
- `DeduplicationPort`
- `SearchRunRecorderPort`
- `SearchQueryRepositoryPort`
- `WorkRepositoryPort`

The package command `nexus:search` exposes implemented search behavior for package consumers. The reusable contract remains the handlers, ports, domain objects, and migrations.

## Data Model And Persistence

Search persistence records three relationships:

- query identity in `search_queries`,
- provider participation in `search_query_providers`,
- works returned for a query in `query_works`.

Search writes normalized works into the shared scholarly-work persistence tables:

- `scholarly_works`,
- `work_external_ids`,
- `work_providers`,
- `authors`,
- `work_authors`.

The persistence layer separates canonical work identity from provider sightings and external identifiers. This allows search to preserve provenance without making provider IDs the only package identity.

## Main Workflows

### One Search Request

1. A caller creates `SearchAcrossProviders` or calls the package command.
2. The command constructs a validated `SearchQuery`.
3. The handler checks the project lock state.
4. The aggregator selects providers and cache identity.
5. Providers execute and return normalized results.
6. The deduplication port collapses equivalent works.
7. The result returns provider progress and a normalized corpus slice.

### Persisted Search

1. `PersistentSearchRunner` starts a recorded run.
2. Provider execution runs through the same handler path.
3. Query, provider, work, and provenance records are persisted.
4. The run recorder marks completion or failure.

### YAML Plan Execution

1. The YAML plan parser turns a plan file into ordered search items.
2. Defaults from the root plan are merged with per-search overrides.
3. Each planned search runs through `SearchExecutorPort`.
4. The runner returns item-level outcomes.

## Validation And Tests

Search behavior is covered by:

- query, search term, year range, provider alias, and cache-key unit tests,
- aggregator tests for provider filtering, cache restoration, failures, and deduplication,
- provider-execution tests for sequential and concurrent behavior,
- provider adapter tests with fixture-backed responses,
- YAML plan parser and runner tests,
- persistence tests for query, provider, work, and search-run recording,
- package command feature tests.

Relevant test paths:

- `tests/Unit/Search`
- `tests/Integration/Provider`
- `tests/Feature/Persistence/SearchQueryRepositoryTest.php`
- `tests/Feature/Persistence/SearchRunRecorderFeatureTest.php`
- `tests/Feature/Laravel/NexusSearchCommandTest.php`

## What Did Not Ship In v1

The Search context does not guarantee:

- live provider network calls in CI,
- all providers being enabled without host configuration,
- full feature parity across provider-specific APIs,
- mutation of a locked project corpus,
- a product-specific query builder,
- a stable public contract for internal provider response parsing details.

Provider integrations are covered through adapters, fixtures, and normalized contracts. Hosts are responsible for credentials, environment configuration, and deciding which providers to enable.

## Changed From Earlier Specs

- Search query identity now includes `projectId`, selected provider aliases, cache-key material, and pagination helpers.
- Language handling uses the shared `LanguageCode` value object.
- Provider execution now has sequential and bounded concurrent executors.
- Provider failures are represented in provider progress instead of failing all partial searches by default.
- YAML plan parsing shipped as an application service.
- IEEE is disabled by default unless configured with credentials.
- Cache payloads store normalized works and provider stats, not raw provider objects.

## Implementation References

- Code references:
  - `src/Search`
  - `src/Laravel/Persistence/EloquentSearchRunRecorder.php`
  - `src/Laravel/Command/NexusSearchCommand.php`
  - `tests/Unit/Search`
  - `tests/Integration/Provider`
  - `tests/Feature/Persistence`