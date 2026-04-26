# Search Aggregator & Deduplication Hardening Report

This report outlines the recent architectural and resiliency updates implemented in the Nexus Scholarly core library.

## 1. Concurrency & Performance Optimization
- **Parallel Search Orchestration:** Refactored `SearchAcrossProvidersHandler` from a blocking serial loop into a concurrent model using `GuzzleHttp\Promise\Utils::settle()`. Multiple provider queries now execute in parallel.
- **Asynchronous Contracts:** Expanded `HttpClientPort` and `AcademicProviderPort` to require asynchronous variants (`getAsync` and `searchAsync`).
- **Global Search Timeout:** Added a `timeoutMs` parameter to the `SearchAcrossProviders` command to allow for bounded global timeouts.
- **PSR-4 Autoloading Fix:** Abstracted `ProviderSearchResult` out of the result file and into its standalone class file `src/Search/Application/ProviderSearchResult.php`.

## 2. Infrastructure Resilience & Fault Tolerance
- **Exponential Backoff Jitter:** Integrated a randomized float jitter (0.0s to 1.0s) into the `BaseProviderAdapter`'s retry logic to prevent thundering herd requests if providers throttle the application.
- **DOAJ Lucene Injection Fix:** Addressed a critical bug where search strings containing parentheses, colons, and other Lucene characters caused DOAJ queries to fail. Integrated an `escapeLucene` method inside `DoajAdapter`.
- **Semantic Scholar Pagination Safety:** Adjusted `SemanticScholarAdapter` so that if a subsequent continuation page (e.g. page 2) triggers a `ProviderUnavailable` exception, the adapter swallows the error and gracefully returns the items accumulated so far (e.g. from page 1).

## 3. Deduplication Edge Cases & Hardening
- **Extreme Title Normalization:** Expanded `TitleNormalizerTest` to verify that `TitleNormalizer` accurately and safely normalizes titles containing mathematical symbols (`\alpha`, `\beta`), emojis (`🚀`), and extremely long inputs (up to 3,000 characters) without catastrophic memory spikes or errors.
- **Deterministic Representative Election:** Enforced strict fallback parameters within `CompletenessElectionPolicy`. If two scholarly works have the identical completeness score, the tie is broken by the presence of a DOI identifier, and ultimately, by the earliest `retrievedAt` timestamp.
- **Clustering Stress Test:** Injected a 1000-work stress test for `TitleFuzzyPolicy`. It validated that the $O(n \log n)$ adjacent-sort heuristic is performant and processes the batch well under 500ms, effectively avoiding the $O(n^2)$ Levenshtein bottleneck.
- **PubMed XML Degradation:** Implemented an edge-case test proving that the `PubMedAdapter` gracefully parses structurally drifted XML payloads missing critical components like authors, abstracts, or journal details.

## 4. Test Suite Health
- Restored `searchAsync` and `getAsync` interfaces in internal test mocks to address `TypeError`s thrown by strict typing.
- The Pest test suite is now robust, running **136 tests encompassing 254 assertions**, all passing flawlessly (`100% green`).
