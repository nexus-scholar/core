<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Domain\ScreeningRun;

interface ScreeningRunRepositoryPort
{
    public function get(string $screeningRunId): ?ScreeningRun;

    public function start(ScreeningRun $run): void;

    /**
     * @param  array<string, int>  $counts
     */
    public function complete(string $screeningRunId, array $counts): void;

    public function fail(string $screeningRunId, string $message): void;
}
