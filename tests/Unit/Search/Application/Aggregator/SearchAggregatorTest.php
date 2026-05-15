<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Application\Aggregator;

use Nexus\Search\Application\Aggregator\SearchAggregator;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('aggregates results from multiple providers and deduplicates them', function () {
    $query = new SearchQuery(new SearchTerm('test'));

    $work1 = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1234/a')),
        title: 'Paper A',
        sourceProvider: 'provider_1'
    );

    $work2 = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1234/b')),
        title: 'Paper B',
        sourceProvider: 'provider_2'
    );

    $adapter1 = new class($work1) implements AcademicProviderPort {
        public function __construct(private $work) {}
        public function alias(): string { return 'provider_1'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            return [$this->work];
        }
        public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface {
            return new \GuzzleHttp\Promise\FulfilledPromise($this->search($query));
        }
    };

    $adapter2 = new class($work2) implements AcademicProviderPort {
        public function __construct(private $work) {}
        public function alias(): string { return 'provider_2'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            return [$this->work];
        }
        public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface {
            return new \GuzzleHttp\Promise\FulfilledPromise($this->search($query));
        }
    };

    $adapter3 = new class implements AcademicProviderPort {
        public function alias(): string { return 'provider_error'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            throw new ProviderUnavailable('provider_error', 'Simulated failure');
        }
        public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface {
            return new \GuzzleHttp\Promise\RejectedPromise(new ProviderUnavailable('provider_error', 'Simulated failure'));
        }
    };

    $dedup = new class implements DeduplicationPort {
        public function deduplicate(CorpusSlice $corpus): CorpusSlice {
            // Simply return the corpus as-is for the stub
            return $corpus;
        }
    };

    $cache = new class implements \Nexus\Search\Domain\Port\SearchCachePort {
        public function get(string $key): ?array { return null; }
        public function put(string $key, array $works, int $ttlSeconds = 3600): void {}
        public function has(string $key): bool { return false; }
        public function invalidateAll(): void {}
    };

    $aggregator = new SearchAggregator(new \Nexus\Search\Domain\Port\AdapterCollection($adapter1, $adapter2, $adapter3), $dedup, $cache);

    $result = $aggregator->aggregate($query);

    // Assertions
    expect($result->totalRaw)->toBe(2);
    expect($result->corpus->count())->toBe(2);
    
    // Stats array should have 3 items (2 successes, 1 failure)
    expect($result->providerStats)->toHaveCount(3);
    
    expect($result->providerStats[0]->alias)->toBe('provider_1');
    expect($result->providerStats[0]->resultCount)->toBe(1);
    expect($result->providerStats[0]->skipReason)->toBeNull();

    expect($result->providerStats[1]->alias)->toBe('provider_2');
    expect($result->providerStats[1]->resultCount)->toBe(1);
    expect($result->providerStats[1]->skipReason)->toBeNull();

    expect($result->providerStats[2]->alias)->toBe('provider_error');
    expect($result->providerStats[2]->resultCount)->toBe(0);
    expect($result->providerStats[2]->skipReason)->toBe('Provider "provider_error" is unavailable: Simulated failure');
});

it('returns empty corpus when all providers fail', function () {
    $failingAdapter = new class implements AcademicProviderPort {
        public function alias(): string { return 'dead'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            throw new ProviderUnavailable('dead', 'All down');
        }
        public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface {
            return new \GuzzleHttp\Promise\RejectedPromise(new ProviderUnavailable('dead', 'All down'));
        }
    };

    $dedup = new class implements DeduplicationPort {
        public function deduplicate(CorpusSlice $corpus): CorpusSlice { return $corpus; }
    };

    $cache = new class implements \Nexus\Search\Domain\Port\SearchCachePort {
        public function get(string $key): ?array { return null; }
        public function put(string $key, array $works, int $ttlSeconds = 3600): void {}
        public function has(string $key): bool { return false; }
        public function invalidateAll(): void {}
    };

    $result = (new SearchAggregator(new \Nexus\Search\Domain\Port\AdapterCollection($failingAdapter), $dedup, $cache))->aggregate(
        new SearchQuery(new SearchTerm('test'))
    );

    expect($result->totalRaw)->toBe(0);
    expect($result->corpus->count())->toBe(0);
    expect($result->providerStats[0]->skipReason)->not->toBeNull();
});

it('uses only provider aliases selected on the query', function (): void {
    $calls = (object) ['counts' => []];

    $makeAdapter = static function (string $alias) use ($calls): AcademicProviderPort {
        return new class($alias, $calls) implements AcademicProviderPort {
            public function __construct(private readonly string $providerAlias, private readonly object $calls) {}

            public function alias(): string { return $this->providerAlias; }
            public function supports(WorkIdNamespace $ns): bool { return true; }
            public function fetchById(WorkId $id): ?ScholarlyWork { return null; }

            public function search(SearchQuery $query): array
            {
                $this->calls->counts[$this->providerAlias] = ($this->calls->counts[$this->providerAlias] ?? 0) + 1;

                return [ScholarlyWork::reconstitute(
                    ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, "10.1234/{$this->providerAlias}")),
                    title: "Paper {$this->providerAlias}",
                    sourceProvider: $this->providerAlias,
                )];
            }

            public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface
            {
                return new \GuzzleHttp\Promise\FulfilledPromise($this->search($query));
            }
        };
    };

    $dedup = new class implements DeduplicationPort {
        public function deduplicate(CorpusSlice $corpus): CorpusSlice { return $corpus; }
    };

    $cache = new class implements \Nexus\Search\Domain\Port\SearchCachePort {
        public ?string $lastKey = null;

        public function get(string $key): ?array
        {
            $this->lastKey = $key;

            return null;
        }

        public function put(string $key, array $works, int $ttlSeconds = 3600): void {}
        public function has(string $key): bool { return false; }
        public function invalidateAll(): void {}
    };

    $aggregator = new SearchAggregator(
        new \Nexus\Search\Domain\Port\AdapterCollection(
            $makeAdapter('provider_1'),
            $makeAdapter('provider_2'),
        ),
        $dedup,
        $cache,
    );

    $query = new SearchQuery(new SearchTerm('test'), providerAliases: ['provider_2']);
    $result = $aggregator->aggregate($query);

    expect($calls->counts)->toBe(['provider_2' => 1])
        ->and($result->providerStats)->toHaveCount(1)
        ->and($result->providerStats[0]->alias)->toBe('provider_2')
        ->and($cache->lastKey)->toBe($query->cacheKey(['provider_2']));
});
