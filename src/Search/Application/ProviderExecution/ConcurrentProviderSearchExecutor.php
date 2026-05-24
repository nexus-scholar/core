<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\SearchQuery;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ConcurrentProviderSearchExecutor implements ProviderSearchExecutorPort
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private int $concurrency = 3,
    ) {}

    public function execute(SearchQuery $query, array $providers): ProviderSearchExecutionResult
    {
        $results = [];
        $pending = [];
        $concurrency = max(1, $this->concurrency);

        foreach ($providers as $provider) {
            if ($provider instanceof ConcurrentSearchProviderPort) {
                $task = $this->beginTask($provider, $query, $results);

                if ($task !== null) {
                    $pending[] = [$task, hrtime(true)];

                    if (count($pending) >= $concurrency) {
                        $this->flush($pending, $results);
                    }

                    continue;
                }
            }

            $this->flush($pending, $results);
            $this->runSynchronously($provider, $query, $results);
        }

        $this->flush($pending, $results);

        return new ProviderSearchExecutionResult($results);
    }

    /**
     * @param  list<ProviderSearchResult>  $results
     */
    private function beginTask(
        ConcurrentSearchProviderPort $provider,
        SearchQuery $query,
        array &$results,
    ): ?ProviderSearchTask {
        $alias = $provider instanceof AcademicProviderPort ? $provider->alias() : 'unknown';
        $start = hrtime(true);

        try {
            $task = $provider->beginSearch($query);
        } catch (Throwable $error) {
            $results[] = ProviderSearchResult::failure($alias, $error, $this->elapsedMs($start));
            $this->logger?->warning("Provider search skipped {$alias}", ['reason' => $error->getMessage()]);

            return null;
        }

        return $task;
    }

    /**
     * @param  list<ProviderSearchResult>  $results
     */
    private function runSynchronously(AcademicProviderPort $provider, SearchQuery $query, array &$results): void
    {
        $alias = $provider->alias();
        $start = hrtime(true);

        try {
            $works = $provider->search($query);
            $results[] = ProviderSearchResult::success($alias, $works, $this->elapsedMs($start));
        } catch (Throwable $error) {
            $results[] = ProviderSearchResult::failure($alias, $error, $this->elapsedMs($start));
            $this->logger?->warning("Provider search skipped {$alias}", ['reason' => $error->getMessage()]);
        }
    }

    /**
     * @param  list<array{0: ProviderSearchTask, 1: int|float}>  $pending
     * @param  list<ProviderSearchResult>  $results
     */
    private function flush(array &$pending, array &$results): void
    {
        foreach ($pending as [$task, $start]) {
            $alias = $task->alias();

            try {
                $works = $task->await();
                $results[] = ProviderSearchResult::success($alias, $works, $this->elapsedMs($start));
            } catch (Throwable $error) {
                $results[] = ProviderSearchResult::failure($alias, $error, $this->elapsedMs($start));
                $this->logger?->warning("Provider search skipped {$alias}", ['reason' => $error->getMessage()]);
            }
        }

        $pending = [];
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
