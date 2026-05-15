<?php

declare(strict_types=1);

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;

it('delegates to the aggregator when the project is not locked', function (): void {
    $aggregator = new class implements SearchAggregatorPort {
        public ?SearchQuery $received = null;

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            $this->received = $query;

            return new AggregatedResult(CorpusSlice::empty(), [], 0);
        }
    };

    $locks = new class implements ProjectLockPort {
        public array $checked = [];

        public function isLocked(string $projectId): bool
        {
            $this->checked[] = $projectId;

            return false;
        }
    };

    $handler = new SearchAcrossProvidersHandler($aggregator, $locks);
    $result = $handler->handle(new SearchAcrossProviders(
        query: 'machine learning',
        projectId: 'project-1',
        providerAliases: ['OpenAlex', 'arxiv', 'openalex'],
    ));

    expect($result->corpus->isEmpty())->toBeTrue()
        ->and($aggregator->received?->projectId)->toBe('project-1')
        ->and($aggregator->received?->providerAliases)->toBe(['openalex', 'arxiv'])
        ->and($locks->checked)->toBe(['project-1']);
});

it('blocks search when the project is locked', function (): void {
    $aggregator = new class implements SearchAggregatorPort {
        public function aggregate(SearchQuery $query): AggregatedResult
        {
            throw new RuntimeException('Aggregator should not run for a locked project.');
        }
    };

    $locks = new class implements ProjectLockPort {
        public function isLocked(string $projectId): bool
        {
            return true;
        }
    };

    $handler = new SearchAcrossProvidersHandler($aggregator, $locks);

    expect(fn () => $handler->handle(new SearchAcrossProviders('machine learning', 'locked-project')))
        ->toThrow(ProjectLockedException::class);
});

