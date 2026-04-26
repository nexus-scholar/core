<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Deduplication;

use Nexus\Deduplication\Application\DeduplicateCorpus;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\DeduplicationPort;

final class DeduplicationAdapter implements DeduplicationPort
{
    public function __construct(
        private readonly DeduplicateCorpusHandler $handler,
    ) {}

    public function deduplicate(CorpusSlice $corpus): CorpusSlice
    {
        $result = $this->handler->handle(new DeduplicateCorpus($corpus));

        return $result->clusters->toCorpusSlice();
    }
}
