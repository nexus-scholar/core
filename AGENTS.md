# AGENTS.md

## Start Here
- Read `README.md` first; it is the architecture reading order and points to the canonical docs set.
- Code is organized by bounded contexts under `src/`: `Search`, `Deduplication`, `CitationNetwork`, `Dissemination`, plus shared types in `Shared` and framework glue in `Laravel`.
- Treat `Nexus\Laravel` as an integration shell only; keep business rules in core namespaces (see `docs/03-architecture-rules.md`).

## Architecture and Data Flow
- Main implemented runtime path today is search aggregation: provider adapters -> `SearchAggregator` -> dedup port -> `CorpusSlice` (`src/Search/Application/Aggregator/SearchAggregator.php`).
- Search fan-out is parallelized with `GuzzleHttp\Promise\Utils::settle()` in `SearchAggregator::aggregate`; provider failures become per-provider stats instead of hard failures.
- Dedup is an ordered policy pipeline over Union-Find (`src/Deduplication/Application/DeduplicateCorpusHandler.php` + `src/Deduplication/Infrastructure/UnionFind.php`).
- Provider wiring is centralized in `src/Laravel/NexusServiceProvider.php` (single registration pass, adapter construction, token-bucket limiter per adapter).

## Project-Specific Rules (Do Not Drift)
- Do not compute search cache keys outside `SearchQuery::cacheKey()` (`src/Search/Domain/SearchQuery.php`).
- Keep ID normalization in `WorkId` only (`src/Shared/ValueObject/WorkId.php`); do not duplicate DOI/arXiv parsing logic elsewhere.
- Provider HTTP must go through base adapter request paths so rate limiting/retry behavior is not skipped (`src/Search/Infrastructure/Provider/BaseProviderAdapter.php`).
- Raw payload storage is opt-in only via `SearchQuery::$includeRawData`; default mappings keep `rawData` null.
- Keep domain free of Laravel imports; `Illuminate\*` belongs in `src/Laravel/**`.

## Testing and Debugging Workflow
- Tests use Pest with shared bootstrapping in `tests/Pest.php` (`Feature`, `Unit`, `Integration`).
- Verified command for focused runs:
  - `php vendor/bin/pest tests/Unit/Search/SearchQueryTest.php`
- Provider integration tests use PHP-VCR cassettes from `tests/Fixture/vcr_cassettes` (example: `tests/Integration/Provider/OpenAlexAdapterTest.php`); do not switch CI tests to live APIs.
- Prefer unit tests around value objects/policies first, then adapter integration tests, then Laravel-level feature tests (see `docs/12-tdd-strategy.md`).

## Integrations and External Dependencies
- Core runtime deps: `guzzlehttp/guzzle` (HTTP) and `composer/ca-bundle` (CA resolution) in `composer.json`.
- Dev/test deps: Pest, Mockery, php-vcr, Orchestra Testbench.
- Provider defaults and API-key-sensitive rates are in `src/Search/Infrastructure/Provider/ProviderConfigRegistry.php`.
- Package config surface is `src/Laravel/config/nexus.php` (`NEXUS_IEEE_API_KEY`, `NEXUS_S2_API_KEY`, `NEXUS_PUBMED_API_KEY`, `NEXUS_MAIL_TO`).

## Current State Notes for Agents
- Several Laravel commands/jobs/tools and some citation-network classes are scaffolds (files with only strict-types header). Implement behavior only when tests/spec docs for that area are added.
- Existing concrete command: `nexus:search` in `src/Laravel/Command/NexusSearchCommand.php`; usage example is also shown in `test_cmd.php`.
- Migration history currently has both scaffold-era and newer migrations in `src/Laravel/Migration`; prefer extending the newer concrete set when adding schema changes.

