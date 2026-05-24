<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Application\ProviderExecution;

use Nexus\Search\Application\ProviderExecution\CallbackProviderSearchTask;
use Nexus\Search\Application\ProviderExecution\ConcurrentProviderSearchExecutor;
use Nexus\Search\Application\ProviderExecution\ConcurrentSearchProviderPort;
use Nexus\Search\Application\ProviderExecution\ProviderSearchTask;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('starts async provider tasks up to the concurrency limit before awaiting them', function (): void {
    $started = 0;
    $startedSeenAtAwait = [];

    $result = (new ConcurrentProviderSearchExecutor(concurrency: 2))->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            concurrentProviderStub('first', [executorWork('first')], $started, $startedSeenAtAwait),
            concurrentProviderStub('second', [executorWork('second')], $started, $startedSeenAtAwait),
        ],
    );

    expect($started)->toBe(2)
        ->and($startedSeenAtAwait)->toBe([2, 2])
        ->and($result->works())->toHaveCount(2)
        ->and($result->stats()[0]->alias)->toBe('first')
        ->and($result->stats()[0]->resultCount)->toBe(1)
        ->and($result->stats()[1]->alias)->toBe('second')
        ->and($result->stats()[1]->resultCount)->toBe(1);
});

it('flushes async tasks before running a synchronous fallback provider', function (): void {
    $events = [];
    $started = 0;
    $seen = [];

    $result = (new ConcurrentProviderSearchExecutor(concurrency: 3))->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            concurrentProviderStub('async', [executorWork('async')], $started, $seen, $events),
            syncProviderStub('sync', [executorWork('sync')], $events),
        ],
    );

    expect($events)->toBe(['begin:async', 'await:async', 'sync:sync'])
        ->and($result->works())->toHaveCount(2)
        ->and($result->stats()[0]->alias)->toBe('async')
        ->and($result->stats()[1]->alias)->toBe('sync');
});

it('captures async task failures and continues remaining providers', function (): void {
    $started = 0;
    $seen = [];

    $result = (new ConcurrentProviderSearchExecutor(concurrency: 2))->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            concurrentProviderStub('broken', [], $started, $seen, failure: new ProviderUnavailable('broken', 'temporary failure')),
            syncProviderStub('healthy', [executorWork('healthy')]),
        ],
    );

    expect($result->works())->toHaveCount(1)
        ->and($result->stats())->toHaveCount(2)
        ->and($result->stats()[0]->alias)->toBe('broken')
        ->and($result->stats()[0]->resultCount)->toBe(0)
        ->and($result->stats()[0]->skipReason)->toBe('Provider "broken" is unavailable: temporary failure')
        ->and($result->stats()[1]->alias)->toBe('healthy')
        ->and($result->stats()[1]->resultCount)->toBe(1);
});

it('falls back to synchronous search when an async provider declines a task', function (): void {
    $events = [];

    $result = (new ConcurrentProviderSearchExecutor(concurrency: 2))->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            decliningConcurrentProviderStub('declines', [executorWork('declines')], $events),
        ],
    );

    expect($events)->toBe(['begin:declines', 'sync:declines'])
        ->and($result->works())->toHaveCount(1)
        ->and($result->stats()[0]->alias)->toBe('declines')
        ->and($result->stats()[0]->resultCount)->toBe(1);
});

function executorWork(string $alias): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, "10.1234/{$alias}")),
        title: "Executor {$alias}",
        sourceProvider: $alias,
    );
}

/**
 * @param  list<ScholarlyWork>  $works
 * @param  list<int>  $startedSeenAtAwait
 * @param  list<string>  $events
 */
function concurrentProviderStub(
    string $alias,
    array $works,
    int &$started,
    array &$startedSeenAtAwait,
    array &$events = [],
    ?\Throwable $failure = null,
): AcademicProviderPort&ConcurrentSearchProviderPort {
    return new class($alias, $works, $started, $startedSeenAtAwait, $events, $failure) implements AcademicProviderPort, ConcurrentSearchProviderPort
    {
        private int $started;

        /**
         * @var list<int>
         */
        private array $startedSeenAtAwait;

        /**
         * @var list<string>
         */
        private array $events;

        /**
         * @param  list<ScholarlyWork>  $works
         * @param  list<int>  $startedSeenAtAwait
         * @param  list<string>  $events
         */
        public function __construct(
            private readonly string $providerAlias,
            private readonly array $works,
            int &$started,
            array &$startedSeenAtAwait,
            array &$events,
            private readonly ?\Throwable $failure = null,
        ) {
            $this->started = &$started;
            $this->startedSeenAtAwait = &$startedSeenAtAwait;
            $this->events = &$events;
        }

        public function alias(): string
        {
            return $this->providerAlias;
        }

        public function beginSearch(SearchQuery $query): ?ProviderSearchTask
        {
            $this->started++;
            $this->events[] = "begin:{$this->providerAlias}";

            return new CallbackProviderSearchTask($this->providerAlias, function (): array {
                $this->startedSeenAtAwait[] = $this->started;
                $this->events[] = "await:{$this->providerAlias}";

                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->works;
            });
        }

        public function search(SearchQuery $query): array
        {
            throw new \LogicException('Async provider should not use synchronous search in this test.');
        }

        public function fetchById(WorkId $id): ?ScholarlyWork
        {
            return null;
        }

        public function supports(WorkIdNamespace $ns): bool
        {
            return true;
        }
    };
}

/**
 * @param  list<ScholarlyWork>  $works
 * @param  list<string>  $events
 */
function syncProviderStub(string $alias, array $works, array &$events = []): AcademicProviderPort
{
    return new class($alias, $works, $events) implements AcademicProviderPort
    {
        /**
         * @var list<string>
         */
        private array $events;

        /**
         * @param  list<ScholarlyWork>  $works
         * @param  list<string>  $events
         */
        public function __construct(
            private readonly string $providerAlias,
            private readonly array $works,
            array &$events,
        ) {
            $this->events = &$events;
        }

        public function alias(): string
        {
            return $this->providerAlias;
        }

        public function search(SearchQuery $query): array
        {
            $this->events[] = "sync:{$this->providerAlias}";

            return $this->works;
        }

        public function fetchById(WorkId $id): ?ScholarlyWork
        {
            return null;
        }

        public function supports(WorkIdNamespace $ns): bool
        {
            return true;
        }
    };
}

/**
 * @param  list<ScholarlyWork>  $works
 * @param  list<string>  $events
 */
function decliningConcurrentProviderStub(string $alias, array $works, array &$events): AcademicProviderPort&ConcurrentSearchProviderPort
{
    return new class($alias, $works, $events) implements AcademicProviderPort, ConcurrentSearchProviderPort
    {
        /**
         * @var list<string>
         */
        private array $events;

        /**
         * @param  list<ScholarlyWork>  $works
         * @param  list<string>  $events
         */
        public function __construct(
            private readonly string $providerAlias,
            private readonly array $works,
            array &$events,
        ) {
            $this->events = &$events;
        }

        public function alias(): string
        {
            return $this->providerAlias;
        }

        public function beginSearch(SearchQuery $query): ?ProviderSearchTask
        {
            $this->events[] = "begin:{$this->providerAlias}";

            return null;
        }

        public function search(SearchQuery $query): array
        {
            $this->events[] = "sync:{$this->providerAlias}";

            return $this->works;
        }

        public function fetchById(WorkId $id): ?ScholarlyWork
        {
            return null;
        }

        public function supports(WorkIdNamespace $ns): bool
        {
            return true;
        }
    };
}
