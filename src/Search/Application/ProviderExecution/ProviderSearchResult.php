<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Shared\Domain\ScholarlyWork;
use Throwable;

final readonly class ProviderSearchResult
{
    /**
     * @param  ScholarlyWork[]  $works
     */
    public function __construct(
        public string $alias,
        public array $works,
        public ProviderStat $stat,
        public ?Throwable $error = null,
    ) {}

    /**
     * @param  ScholarlyWork[]  $works
     */
    public static function success(string $alias, array $works, float $latencyMs): self
    {
        return new self(
            alias: $alias,
            works: $works,
            stat: new ProviderStat($alias, count($works), $latencyMs),
        );
    }

    public static function failure(string $alias, Throwable $error, float $latencyMs): self
    {
        return new self(
            alias: $alias,
            works: [],
            stat: new ProviderStat($alias, 0, $latencyMs, $error->getMessage()),
            error: $error,
        );
    }

    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
