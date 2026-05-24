# Contracts and Ports

## Purpose

Ports are the anti-drift mechanism of the codebase. They define what the application expects from infrastructure without binding the domain to any specific library or framework.

## Search Ports

### AcademicProviderPort
Responsibilities:
- search by query
- fetch by ID
- advertise supported namespaces
- expose stable alias

Implementations:
- OpenAlex adapter
- Crossref adapter
- arXiv adapter
- Semantic Scholar adapter
- PubMed adapter
- DOAJ adapter
- IEEE adapter [cite:11]

### RateLimiterPort
Responsibilities:
- enforce wait before external request
- optionally support non-blocking checks

The old package’s rate limiter existed but was not actually used, so in the redesign this port is non-optional for provider request flow. [Code Review](old-nexus-review/nexus-php-code-review.md)

### HttpClientPort
Responsibilities:
- perform HTTP requests with timeouts, headers, query params
- expose parsed results or structured response
- isolate the rest of the code from Guzzle/PSR-18 details

### SearchCachePort
Responsibilities:
- get cached results
- put results with TTL/versioning
- invalidate by version or prefix

Do not copy the old “flush an untagged cache namespace” mistake. [Code Review](old-nexus-review/nexus-php-code-review.md)

### ProviderSearchExecutorPort
Responsibilities:
- execute a query against a selected immutable provider list
- return normalized provider results and `ProviderStat` records
- isolate execution strategy from `SearchAggregator`
- keep optional concurrent fan-out behind a package-owned contract

Implementations:
- `SequentialProviderSearchExecutor`: default, preserves current provider execution order.
- `ConcurrentProviderSearchExecutor`: optional bounded fan-out for providers that implement `ConcurrentSearchProviderPort`.

The concurrent executor sees only package-owned `ProviderSearchTask` objects. Guzzle promises and async HTTP details are confined to infrastructure.

Laravel configuration:

```text
NEXUS_SEARCH_EXECUTION_MODE=sequential|concurrent
NEXUS_SEARCH_CONCURRENCY=3
```

## Deduplication Ports

### DeduplicationPolicyPort
Responsibilities:
- inspect works
- return duplicate findings
- remain composable inside a policy pipeline

Possible implementations:
- exact DOI policy
- exact namespace ID policy
- fuzzy title policy
- fingerprint policy

## Citation Network Ports

### SnowballingProviderPort
Responsibilities:
- get citing works
- get referenced works

Only providers that can support these operations should implement it. Search capability and snowball capability are related but distinct.

### CitationGraphRepositoryPort
Responsibilities:
- persist graph snapshots
- retrieve graph by ID or project
- abstract Eloquent/SQL/storage details

### GraphAlgorithmPort
Responsibilities:
- compute metrics or derived graph data
- stay isolated from persistence and transport

## Dissemination Ports

### BibliographySerializerPort
Responsibilities:
- serialize corpora into citation formats

### NetworkSerializerPort
Responsibilities:
- serialize graphs into visualization formats

### FullTextSourcePort
Responsibilities:
- determine support for a work
- attempt PDF retrieval
- return success/failure artifact object

### FileStoragePort
Responsibilities:
- write/read/delete files
- produce URLs if needed
- hide local vs cloud storage details
