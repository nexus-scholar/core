<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

interface TransactionPort
{
    /**
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed;
}
