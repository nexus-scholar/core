<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Shared\Domain\ScholarlyWork;

final readonly class ProviderSearchExecutionResult
{
    /**
     * @param  ProviderSearchResult[]  $results
     */
    public function __construct(public array $results) {}

    /**
     * @return ScholarlyWork[]
     */
    public function works(): array
    {
        $works = [];

        foreach ($this->results as $result) {
            array_push($works, ...$result->works);
        }

        return $works;
    }

    /**
     * @return ProviderStat[]
     */
    public function stats(): array
    {
        return array_map(
            static fn (ProviderSearchResult $result): ProviderStat => $result->stat,
            $this->results,
        );
    }
}
