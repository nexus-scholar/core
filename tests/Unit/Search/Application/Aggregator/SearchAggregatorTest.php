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
    };

    $adapter2 = new class($work2) implements AcademicProviderPort {
        public function __construct(private $work) {}
        public function alias(): string { return 'provider_2'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            return [$this->work];
        }
    };

    $adapter3 = new class implements AcademicProviderPort {
        public function alias(): string { return 'provider_error'; }
        public function supports(WorkIdNamespace $ns): bool { return true; }
        public function fetchById(WorkId $id): ?ScholarlyWork { return null; }
        public function search(SearchQuery $query): array {
            throw new ProviderUnavailable('provider_error', 'Simulated failure');
        }
    };

    $dedup = new class implements DeduplicationPort {
        public function deduplicate(CorpusSlice $corpus): CorpusSlice {
            // Simply return the corpus as-is for the stub
            return $corpus;
        }
    };

    $aggregator = new SearchAggregator([$adapter1, $adapter2, $adapter3], $dedup);

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
