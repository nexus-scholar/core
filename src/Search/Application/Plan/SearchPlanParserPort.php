<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

interface SearchPlanParserPort
{
    public function parseString(string $contents, string $sourceName = 'inline'): SearchPlan;
}
