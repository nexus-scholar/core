<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Closure;
use Nexus\Shared\Domain\ScholarlyWork;

final readonly class CallbackProviderSearchTask implements ProviderSearchTask
{
    /**
     * @param  Closure(): list<ScholarlyWork>  $resolver
     */
    public function __construct(
        private string $alias,
        private Closure $resolver,
    ) {}

    public function alias(): string
    {
        return $this->alias;
    }

    public function await(): array
    {
        return ($this->resolver)();
    }
}
