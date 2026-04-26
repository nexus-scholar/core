# Class Specs — Search Application Services

> **File:** `docs/spec-search-application.md`
> **Namespace:** `Nexus\Search\Application`
> **Rule:** No HTTP. No SQL. No framework. Depends only on domain + ports.

---

## `SearchAcrossProviders` (Command)

**File:** `src/Search/Application/SearchAcrossProviders.php`

```php
final class SearchAcrossProviders
{
    public function __construct(
        public readonly SearchQuery $query,
        public readonly array       $providerAliases = [],
        // empty = use all registered providers
    )
}
```

---

## `SearchAcrossProvidersHandler` (Application Service)

**File:** `src/Search/Application/SearchAcrossProvidersHandler.php`

```php
final class SearchAcrossProvidersHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array           $providers,
        private readonly SearchCachePort $cache,
        private readonly int             $cacheTtl = 3600,
    )

    /**
     * Execute the search command and return a merged CorpusSlice.
     *
     * Algorithm:
     * 1. Determine which providers to use (all or filtered by $command->providerAliases)
     * 2. Build cache key using $command->query->cacheKey($providerAliases)
     * 3. Return cached result if present
     * 4. For each provider, call $provider->search($command->query)
     *    — collect results, emit ProviderSearchCompleted or ProviderSearchFailed events
     * 5. Build a CorpusSlice from each provider's results (addWork merges same works)
     * 6. Cache the final slice
     * 7. Return the merged CorpusSlice
     *
     * Each provider call is independent:
     * — one provider failing should not stop other providers
     * — failed providers emit ProviderSearchFailed, not an exception
     */
    public function handle(SearchAcrossProviders $command): SearchAcrossProvidersResult
}

final class SearchAcrossProvidersResult
{
    public function __construct(
        public readonly CorpusSlice $corpus,
        public readonly array       $providerResults,
        // ProviderSearchResult[]
        public readonly bool        $fromCache,
        public readonly int         $durationMs,
    )
}

final class ProviderSearchResult
{
    public function __construct(
        public readonly string $providerAlias,
        public readonly int    $resultCount,
        public readonly bool   $success,
        public readonly ?string $error = null,
        public readonly int    $durationMs = 0,
    )
}
```

**Tests:**
```
it_merges_results_from_two_providers
it_returns_cached_result_on_second_call
it_uses_correct_cache_key_with_provider_aliases
it_continues_other_providers_when_one_fails
it_emits_provider_search_completed_for_each_successful_provider
it_emits_provider_search_failed_for_failed_provider
it_returns_from_cache_flag_true_on_cache_hit
it_only_calls_providers_matching_given_aliases
it_returns_empty_corpus_when_all_providers_fail
```

---

## `SearchByWorkId` (Command)

**File:** `src/Search/Application/SearchByWorkId.php`

```php
final class SearchByWorkId
{
    public function __construct(
        public readonly WorkId $id,
        public readonly array  $providerAliases = [],
    )
}
```

---

## `SearchByWorkIdHandler` (Application Service)

**File:** `src/Search/Application/SearchByWorkIdHandler.php`

```php
final class SearchByWorkIdHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array $providers,
    )

    /**
     * Try each provider that supports the ID namespace.
     * Return the first successful result.
     * Return null if no provider finds the work.
     */
    public function handle(SearchByWorkId $command): ?ScholarlyWork
}
```

**Tests:**
```
it_returns_work_from_first_supporting_provider
it_skips_providers_that_do_not_support_namespace
it_returns_null_when_no_provider_finds_work
it_tries_providers_in_registration_order
```

---
