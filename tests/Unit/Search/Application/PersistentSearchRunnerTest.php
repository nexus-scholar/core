<?php

declare(strict_types=1);

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Port\SearchRunRecorderPort;
use Nexus\Search\Application\UseCase\PersistentSearchRunner;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('records search lifecycle, provider stats, works, and completion', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::OPENALEX, 'W123')]),
        title: 'Persisted Work',
        sourceProvider: 'openalex',
    );

    $aggregator = new class($work) implements SearchAggregatorPort
    {
        public function __construct(private readonly ScholarlyWork $work) {}

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            return new AggregatedResult(
                corpus: CorpusSlice::fromWorks($this->work),
                providerStats: [new ProviderStat('openalex', 1, 12)],
                totalRaw: 1,
                durationMs: 15,
            );
        }
    };

    $locks = new class implements ProjectLockPort
    {
        public function isLocked(string $projectId): bool
        {
            return false;
        }
    };

    $recorder = new class implements SearchRunRecorderPort
    {
        public array $events = [];

        public function recordStarted(SearchQuery $query): void
        {
            $this->events[] = ['started', $query->id];
        }

        public function recordProviderStat(SearchQuery $query, ProviderStat $stat): void
        {
            $this->events[] = ['stat', $stat->alias, $stat->resultCount];
        }

        public function recordWork(SearchQuery $query, ScholarlyWork $work, string $providerAlias, string $providerWorkId, int $rank): void
        {
            $this->events[] = ['work', $providerAlias, $providerWorkId, $rank];
        }

        public function recordCompleted(SearchQuery $query, AggregatedResult $result): void
        {
            $this->events[] = ['completed', $result->totalRaw, $result->corpus->count()];
        }

        public function recordFailed(SearchQuery $query, Throwable $error): void
        {
            $this->events[] = ['failed', $error->getMessage()];
        }
    };

    $runner = new PersistentSearchRunner(new SearchAcrossProvidersHandler($aggregator, $locks), $recorder);
    $runner->handle(new SearchAcrossProviders('texture analysis', 'project-1'));

    expect($recorder->events)->toBe([
        ['started', $recorder->events[0][1]],
        ['stat', 'openalex', 1],
        ['work', 'openalex', 'w123', 1],
        ['completed', 1, 1],
    ]);
});

it('records failure before rethrowing execution errors', function (): void {
    $aggregator = new class implements SearchAggregatorPort
    {
        public function aggregate(SearchQuery $query): AggregatedResult
        {
            throw new RuntimeException('unknown provider');
        }
    };

    $locks = new class implements ProjectLockPort
    {
        public function isLocked(string $projectId): bool
        {
            return false;
        }
    };

    $recorder = new class implements SearchRunRecorderPort
    {
        public array $events = [];

        public function recordStarted(SearchQuery $query): void
        {
            $this->events[] = 'started';
        }

        public function recordProviderStat(SearchQuery $query, ProviderStat $stat): void {}

        public function recordWork(SearchQuery $query, ScholarlyWork $work, string $providerAlias, string $providerWorkId, int $rank): void {}

        public function recordCompleted(SearchQuery $query, AggregatedResult $result): void {}

        public function recordFailed(SearchQuery $query, Throwable $error): void
        {
            $this->events[] = 'failed:'.$error->getMessage();
        }
    };

    $runner = new PersistentSearchRunner(new SearchAcrossProvidersHandler($aggregator, $locks), $recorder);

    expect(fn () => $runner->handle(new SearchAcrossProviders('texture analysis', 'project-1')))
        ->toThrow(RuntimeException::class, 'unknown provider');

    expect($recorder->events)->toBe(['started', 'failed:unknown provider']);
});

it('does not create search persistence records when the project is locked', function (): void {
    $aggregator = new class implements SearchAggregatorPort
    {
        public function aggregate(SearchQuery $query): AggregatedResult
        {
            throw new RuntimeException('Aggregator should not run for locked projects.');
        }
    };

    $locks = new class implements ProjectLockPort
    {
        public function isLocked(string $projectId): bool
        {
            return true;
        }
    };

    $recorder = new class implements SearchRunRecorderPort
    {
        public array $events = [];

        public function recordStarted(SearchQuery $query): void
        {
            $this->events[] = 'started';
        }

        public function recordProviderStat(SearchQuery $query, ProviderStat $stat): void
        {
            $this->events[] = 'stat';
        }

        public function recordWork(SearchQuery $query, ScholarlyWork $work, string $providerAlias, string $providerWorkId, int $rank): void
        {
            $this->events[] = 'work';
        }

        public function recordCompleted(SearchQuery $query, AggregatedResult $result): void
        {
            $this->events[] = 'completed';
        }

        public function recordFailed(SearchQuery $query, Throwable $error): void
        {
            $this->events[] = 'failed';
        }
    };

    $runner = new PersistentSearchRunner(new SearchAcrossProvidersHandler($aggregator, $locks), $recorder, $locks);

    expect(fn () => $runner->handle(new SearchAcrossProviders('texture analysis', 'locked-project')))
        ->toThrow(ProjectLockedException::class);

    expect($recorder->events)->toBe([]);
});
