<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Domain\SearchQuery;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SequentialProviderSearchExecutor implements ProviderSearchExecutorPort
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    public function execute(SearchQuery $query, array $providers): ProviderSearchExecutionResult
    {
        $results = [];

        foreach ($providers as $provider) {
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

        return new ProviderSearchExecutionResult($results);
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
