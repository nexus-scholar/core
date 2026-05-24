<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Shared\Domain\ScholarlyWork;

interface ProviderSearchTask
{
    public function alias(): string;

    /**
     * @return list<ScholarlyWork>
     */
    public function await(): array;
}
